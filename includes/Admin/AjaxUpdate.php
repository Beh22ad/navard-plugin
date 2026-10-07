<?php

namespace Navard\Admin;

use Navard\Config;
use Navard\Product\Finder;
use Navard\Product\Updater;
use Navard\Support\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

final class AjaxUpdate
{

    public function hooks(): void
    {
        add_action('wp_ajax_navard_update_start', [$this, 'start']);
        add_action('wp_ajax_navard_update_batch', [$this, 'batch']);
    }

    public function render_page(): void
    {
?>
        <div class="navard-op" dir="rtl">
            <h2>بروز رسانی قیمت‌ها</h2>
            <p>از اینجا می‌توانید قیمت‌ها را همین الان بروز رسانی کنید. توجه کنید که بروز رسانی به صورت روزانه و اتوماتیک اجرا
                می‌شود و نیاز نیست که به صورت دستی این کار را انجام دهید.</p>
            <p>
                <button type="button" class="button button-primary" id="navard-update-start">شروع بروزرسانی قیمت‌ها</button>
                <span class="navard-status" id="navard-update-status"></span>
            </p>
            <div class="navard-progress">
                <div class="navard-progress-bar" id="navard-update-bar"></div>
            </div>
            <p id="navard-update-count"></p>
            <div id="navard-update-log" class="navard-log"></div>
        </div>
<?php
    }

    public function start(): void
    {
        $this->guard();

        $products = Finder::auto_update_products();
        $groups   = [];
        foreach ($products as $p) {
            $api_id = (string) $p['api_id'];
            if ('' === $api_id) {
                continue;
            }
            $key = Helpers::group_from_api_id($api_id);
            $groups[$key][] = ['product_id' => (int) $p['product_id'], 'api_id' => $api_id];
        }

        $state = [
            'started'   => time(),
            'groups'    => $groups,
            'order'     => array_keys($groups),
            'g_index'   => 0,
            'p_offset'  => 0,
            'processed' => 0,
            'total'     => count($products),
            'errors'    => [],
        ];

        set_transient(Config::STATE_UPDATE, $state, HOUR_IN_SECONDS);

        wp_send_json_success([
            'total'     => $state['total'],
            'processed' => 0,
            'next'      => $state['total'] > 0,
        ]);
    }

    public function batch(): void
    {
        $this->guard();
        $state = get_transient(Config::STATE_UPDATE);
        if (! is_array($state)) {
            wp_send_json_error(['message' => 'وضعیت بروزرسانی یافت نشد.']);
        }

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
        $complete          = $gi >= count($order);

        if ($complete) {
            delete_transient(Config::STATE_UPDATE);
        } else {
            set_transient(Config::STATE_UPDATE, $state, HOUR_IN_SECONDS);
        }

        wp_send_json_success([
            'processed' => $state['processed'],
            'total'     => $state['total'],
            'complete'  => $complete,
            'errors'    => array_slice($state['errors'], -5),
        ]);
    }

    private function guard(): void
    {
        check_ajax_referer('navard_ajax', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز'], 403);
        }
    }
}
