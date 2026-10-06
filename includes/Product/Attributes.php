<?php

namespace Navard\Product;

use Navard\Support\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

final class Attributes
{

    /** Fields that must NOT be turned into attributes. */
    private const SKIP = [
        'عنوان کالا',
        'price',
        'قیمت (تومان)',
        'نوسان قیمت',
        'id',
        'priceHistory',
    ];

    /**
     * Lazily ensure a global WooCommerce attribute exists; return its taxonomy name.
     */
    public static function ensure_global(string $label): string
    {
        $taxonomy = wc_attribute_taxonomy_name(Helpers::prefix_slug($label));

        if (! wc_attribute_taxonomy_id_by_name(Helpers::prefix_slug($label))) {
            $id = wc_create_attribute([
                'name'         => $label,
                'slug'         => Helpers::prefix_slug($label),
                'type'         => 'text',
                'order_by'     => 'menu_order',
                'has_archives' => false,
            ]);
            if (is_wp_error($id)) {
                return '';
            }
            // Flush so wc_get_attribute_taxonomies() sees the new one.
            delete_transient('wc_attribute_taxonomies');
        }

        if (! taxonomy_exists($taxonomy)) {
            register_taxonomy($taxonomy, ['product'], [
                'hierarchical' => false,
                'show_ui'      => false,
                'query_var'    => true,
                'rewrite'      => false,
            ]);
        }
        return $taxonomy;
    }

    /**
     * Build a WooCommerce attribute array from a raw API product dict.
     *
     * @return array<int,array{name:string,options:array<int,string>,visible:bool,variation:bool}>
     */
    public static function map_from_product(array $raw): array
    {
        $attrs = [];
        foreach ($raw as $key => $value) {
            if (! is_string($key) || in_array($key, self::SKIP, true)) {
                continue;
            }
            if (! is_scalar($value)) {
                continue;
            }
            $val = trim((string) $value);
            if ('' === $val) {
                continue;
            }

            $tax = self::ensure_global($key);
            if ('' === $tax) {
                continue;
            }

            $term_id = \Navard\Product\Taxonomies::ensure_term($tax, $val);
            $option  = $term_id ? get_term_field('name', $term_id, $tax) : $val;

            $attrs[] = [
                'name'      => $tax,
                'options'   => [(string) $option],
                'visible'   => true,
                'variation' => false,
            ];
        }
        return $attrs;
    }
}
