<?php

namespace Navard\Product;

if (! defined('ABSPATH')) {
    exit;
}

final class Finder
{

    /**
     * Find a product by Navard API ID (meta first, then SKU fallback).
     * Returns product ID or 0.
     */
    public static function by_api_id(string $api_id): int
    {
        if ('' === $api_id) {
            return 0;
        }

        $q = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => Meta::API_ID,
                    'value' => $api_id,
                ],
            ],
        ]);
        if (! empty($q->posts)) {
            return (int) $q->posts[0];
        }

        $pid = (int) wc_get_product_id_by_sku($api_id);
        return $pid > 0 ? $pid : 0;
    }

    /**
     * Return all products flagged for auto-update.
     * Each row: [ 'product_id' => int, 'api_id' => string ]
     */
    public static function auto_update_products(): array
    {
        $q = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => Meta::AUTO_UPDATE,
                    'value' => 'yes',
                ],
            ],
        ]);

        $out = [];
        foreach ($q->posts as $pid) {
            $pid    = (int) $pid;
            $api_id = (string) get_post_meta($pid, Meta::API_ID, true);
            if ('' === $api_id) {
                $sku = (string) get_post_meta($pid, '_sku', true);
                if ('' !== $sku) {
                    $api_id = $sku;
                }
            }
            if ('' === $api_id) {
                continue;
            }
            $out[] = ['product_id' => $pid, 'api_id' => $api_id];
        }
        return $out;
    }
}
