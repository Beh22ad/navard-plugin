<?php

namespace Navard\Admin;

use Navard\Product\Meta;

if (! defined('ABSPATH')) {
    exit;
}

final class AjaxChart
{

    public function hooks(): void
    {
        add_action('wp_ajax_navard_price_history', [$this, 'handle']);
        add_action('wp_ajax_nopriv_navard_price_history', [$this, 'handle']);
    }

    public function handle(): void
    {
        // Public endpoint — nonce still required, tied to the visitor's session.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (! wp_verify_nonce($nonce, 'navard_chart')) {
            wp_send_json_error(['message' => 'invalid nonce'], 403);
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if ($product_id <= 0) {
            wp_send_json_error(['message' => 'invalid product'], 400);
        }

        $p = wc_get_product($product_id);
        if (! $p instanceof \WC_Product) {
            wp_send_json_error(['message' => 'product not found'], 404);
        }

        $raw = (string) get_post_meta($product_id, Meta::PRICE_HISTORY, true);
        $decoded = '' !== $raw ? json_decode($raw, true) : null;

        if (! is_array($decoded) || empty($decoded)) {
            wp_send_json_success(['empty' => true]);
        }

        // Normalize to a list of [label, value].
        $points = [];
        foreach ($decoded as $label => $value) {
            if (is_int($label) && is_array($value)) {
                // API sometimes returns a list of objects — handle gracefully.
                $l = (string) ($value['date'] ?? '');
                $v = (int) ($value['price'] ?? 0);
            } else {
                $l = (string) $label;
                $v = (int) $value;
            }
            if ('' === $l) {
                continue;
            }
            $points[] = ['label' => $l, 'value' => $v];
        }

        if (empty($points)) {
            wp_send_json_success(['empty' => true]);
        }

        // Sort by Shamsi label. Format YYYY/MM/DD sorts lexicographically.
        usort($points, static function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        $labels = [];
        $values = [];
        foreach ($points as $pnt) {
            $labels[] = $pnt['label'];
            $values[] = $pnt['value'];
        }

        wp_send_json_success([
            'empty'  => false,
            'labels' => $labels,
            'values' => $values,
        ]);
    }
}
