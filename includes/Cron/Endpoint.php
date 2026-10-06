<?php

namespace Navard\Cron;

use Navard\Config;
use Navard\Log\Logger;
use Navard\Product\Finder;
use Navard\Product\Updater;
use Navard\Support\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Authenticated REST endpoint that drives batched price updates.
 *
 * One HTTP call runs one batch. When the batch is not the last one,
 * the endpoint fires a NON-BLOCKING self-call to itself so the chain
 * continues automatically until the update is complete.
 *
 * This makes cPanel cron, WP-Cron, and manual browser hits all behave
 * the same way: trigger once, finish on its own.
 */
final class Endpoint
{

    /** Non-blocking self-call timeout (seconds). */
    private const SELF_CALL_TIMEOUT = 0.01;

    /** Max wall-clock time a single chain is allowed to run (seconds). */
    private const CHAIN_MAX_AGE = 1800;

    public function hooks(): void
    {
        add_action('rest_api_init', [$this, 'register']);
    }

    public function register(): void
    {
        register_rest_route('navard/v1', '/cron/update', [
            'methods'             => 'GET',
            'permission_callback' => [$this, 'authorize'],
            'callback'            => [$this, 'handle'],
        ]);
    }

    public function authorize(\WP_REST_Request $req): bool
    {
        $given  = (string) $req->get_param('secret');
        $stored = (string) get_option(Config::CRON_SECRET, '');
        return '' !== $stored && hash_equals($stored, $given);
    }

    public function handle(\WP_REST_Request $req): \WP_REST_Response
    {
        $out = $this->run_batch();
        return new \WP_REST_Response($out, 200);
    }

    /** Used by WP-Cron; same engine, no HTTP response. */
    public function run_batch_silent(): void
    {
        $out = $this->run_batch();
        if (! empty($out['next'])) {
            $this->fire_self_call();
        }
    }

    /**
     * Runs one batch. Returns a status array.
     * If the batch is not the last one, schedules the next one.
     */
    public function run_batch(): array
    {
        if (! Lock::acquire(Config::LOCK_UPDATE, 300)) {
            return ['success' => true, 'running' => true, 'message' => 'بروزرسانی در حال اجرا است.'];
        }

        try {
            $state = get_transient(Config::STATE_UPDATE);

            if (! is_array($state)) {
                $state = $this->build_state();
                if ($state['total'] === 0) {
                    Lock::release(Config::LOCK_UPDATE);
                    return [
                        'success'   => true,
                        'running'   => false,
                        'processed' => 0,
                        'total'     => 0,
                        'complete'  => true,
                        'next'      => false,
                    ];
                }
                set_transient(Config::STATE_UPDATE, $state, HOUR_IN_SECONDS);
            }

            // Chain safety valve: if the chain has run too long, stop and let cron restart it.
            $started = (int) ($state['started'] ?? time());
            $too_old = (time() - $started) > self::CHAIN_MAX_AGE;

            if (! $too_old) {
                $this->process_batch($state);
            }

            $complete = (int) $state['g_index'] >= count($state['order']) || $too_old;

            if ($complete) {
                delete_transient(Config::STATE_UPDATE);
            } else {
                set_transient(Config::STATE_UPDATE, $state, HOUR_IN_SECONDS);
            }

            Lock::release(Config::LOCK_UPDATE);

            Logger::debug(sprintf(
                'cron batch: %d/%d complete=%s chain_age=%ds',
                (int) $state['processed'],
                (int) $state['total'],
                $complete ? 'yes' : 'no',
                time() - $started
            ));

            $next = ! $complete;

            // Keep the chain alive.
            if ($next) {
                $this->fire_self_call();
            }

            return [
                'success'   => true,
                'running'   => $next,
                'processed' => (int) $state['processed'],
                'total'     => (int) $state['total'],
                'next'      => $next,
            ];
        } catch (\Throwable $e) {
            Lock::release(Config::LOCK_UPDATE);
            Logger::error('cron exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'خطای داخلی'];
        }
    }

    /**
     * Fire a non-blocking request to ourselves so the next batch starts
     * as soon as this request returns.
     */
    private function fire_self_call(): void
    {
        $secret = (string) get_option(Config::CRON_SECRET, '');
        if ('' === $secret) {
            return;
        }

        $url = rest_url('navard/v1/cron/update');
        $url = add_query_arg('secret', $secret, $url);

        wp_remote_get($url, [
            'timeout'   => self::SELF_CALL_TIMEOUT,
            'blocking'  => false,
            'sslverify' => false,
        ]);

        Logger::debug('chain: self-call fired');
    }

    private function build_state(): array
    {
        $products = Finder::auto_update_products();

        $groups = [];
        foreach ($products as $p) {
            $api_id = (string) $p['api_id'];
            if ('' === $api_id) {
                continue;
            }
            $key = Helpers::group_from_api_id($api_id);
            $groups[$key][] = ['product_id' => (int) $p['product_id'], 'api_id' => $api_id];
        }

        return [
            'started'   => time(),
            'groups'    => $groups,
            'order'     => array_keys($groups),
            'g_index'   => 0,
            'p_offset'  => 0,
            'processed' => 0,
            'total'     => count($products),
            'errors'    => [],
        ];
    }

    private function process_batch(array &$state): void
    {
        $updater = new Updater();
        $order   = $state['order'];
        $gi      = (int) $state['g_index'];
        $po      = (int) $state['p_offset'];
        $done    = 0;
        $limit   = Config::batch_update();

        while ($gi < count($order) && $done < $limit) {
            $slug  = (string) $order[$gi];
            $items = $state['groups'][$slug];

            $response = $updater->get_category_response($slug);
            if (! $response['ok']) {
                $state['errors'][] = "دسته {$slug}: " . $response['error'];
                $gi++;
                $po = 0;
                continue;
            }

            $map = $updater->index_response($response['data']);

            while ($po < count($items) && $done < $limit) {
                $item = $items[$po];
                $r    = $updater->update_one((int) $item['product_id'], (string) $item['api_id'], $map);
                if (! $r['ok']) {
                    $state['errors'][] = $r['error'];
                }
                $po++;
                $done++;
                $state['processed']++;
            }

            if ($po >= count($items)) {
                $gi++;
                $po = 0;
            }
        }

        $state['g_index']  = $gi;
        $state['p_offset'] = $po;
    }
}
