<?php

namespace Navard\Admin;

use Navard\Config;
use Navard\Parser\ListParser;
use Navard\Product\Importer;

if (! defined('ABSPATH')) {
    exit;
}

final class AjaxImport
{

    public function hooks(): void
    {
        add_action('wp_ajax_navard_import_start', [$this, 'start']);
        add_action('wp_ajax_navard_import_batch', [$this, 'batch']);
    }

    public function render_page(): void
    {
?>
        <div class="navard-op" dir="rtl">
            <h2>درون‌ریزی محصولات</h2>
            <p>محصولات از API خوانده شده و به صورت دسته‌ای (هر بار ۵ محصول) به ووکامرس اضافه یا بروزرسانی می‌شوند.</p>
            <p>
                <button type="button" class="button button-primary" id="navard-import-start">شروع درون‌ریزی</button>
                <span class="navard-status" id="navard-import-status"></span>
            </p>
            <div class="navard-progress">
                <div class="navard-progress-bar" id="navard-import-bar"></div>
            </div>
            <p id="navard-import-count"></p>
            <div id="navard-import-log" class="navard-log"></div>
        </div>
<?php
    }

    public function start(): void
    {
        $this->guard();

        $state = [
            'started'   => time(),
            'products'  => [],
            'offset'    => 0,
            'total'     => 0,
            'errors'    => [],
        ];

        $parser = new ListParser();
        $list   = $parser->load();
        if (! $list['ok']) {
            wp_send_json_error(['message' => 'خطا در دریافت لیست: ' . $list['error']]);
        }
        $state['products'] = $list['products'];
        $state['total']    = count($list['products']);

        set_transient(Config::STATE_IMPORT, $state, HOUR_IN_SECONDS);

        wp_send_json_success([
            'total'     => $state['total'],
            'processed' => 0,
            'next'      => $state['total'] > 0,
        ]);
    }

    public function batch(): void
    {
        $this->guard();
        $state = get_transient(Config::STATE_IMPORT);
        if (! is_array($state)) {
            wp_send_json_error(['message' => 'وضعیت درون‌ریزی یافت نشد.']);
        }

        $offset = (int) $state['offset'];
        $total  = (int) $state['total'];
        $items  = array_slice($state['products'], $offset, Config::BATCH_IMPORT);

        $importer = new Importer();
        foreach ($items as $item) {
            $r = $importer->import($item);
            if (! $r['ok']) {
                $state['errors'][] = $r['error'];
            }
        }

        $state['offset'] = $offset + count($items);
        $complete        = $state['offset'] >= $total;

        if ($complete) {
            delete_transient(Config::STATE_IMPORT);
        } else {
            set_transient(Config::STATE_IMPORT, $state, HOUR_IN_SECONDS);
        }

        wp_send_json_success([
            'processed' => $state['offset'],
            'total'     => $total,
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
