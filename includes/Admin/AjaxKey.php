<?php

namespace Navard\Admin;

use Navard\Api\Client;
use Navard\Config;

if (! defined('ABSPATH')) {
    exit;
}

final class AjaxKey
{

    public function hooks(): void
    {
        add_action('wp_ajax_navard_check_key', [$this, 'check']);
    }

    public function check(): void
    {
        check_ajax_referer('navard_ajax', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز'], 403);
        }

        $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
        if ('' === $key) {
            wp_send_json_error(['message' => 'کلید وارد نشده است.']);
        }

        // Validate against the currently selected endpoint so the saved key matches it.
        $endpoint = isset($_POST['endpoint']) ? sanitize_key(wp_unslash($_POST['endpoint'])) : '';
        $endpoints = Config::endpoints();
        if (! isset($endpoints[$endpoint])) {
            $endpoint = (string) Config::get('endpoint', 'main');
        }
        $base = Config::endpoint($endpoint);

        $res = (new Client($base, $key))->ping();

        if (! $res->ok) {
            wp_send_json_error(['message' => 'کلید نامعتبر است یا سرور پاسخ نداد: ' . $res->error]);
        }

        // Key is valid → persist it along with the endpoint used for the test.
        $settings              = Config::all();
        $settings['api_key']   = $key;
        $settings['endpoint']  = $endpoint;
        update_option(Config::OPTION_KEY, $settings);

        // Bust cached /list so the next operation uses the new key.
        delete_transient(Config::TRANSIENT_LIST);

        wp_send_json_success([
            'message'  => 'کلید معتبر است و ذخیره شد.',
            'endpoint' => $endpoint,
        ]);
    }
}
