<?php

namespace Navard\Shortcode;

use Navard\Config;

if (! defined('ABSPATH')) {
    exit;
}

final class Search
{

    public function hooks(): void
    {
        add_action('pre_get_posts', [$this, 'filter_search'], 20);
    }

    private function enabled(): bool
    {
        return 'yes' === (string) Config::get('hide_product_page', 'no');
    }

    /**
     * On the front-end search, replace product results with their first product category.
     * The main query becomes a product_cat query, so the theme renders category terms
     * (with its own template) instead of products.
     */
    public function filter_search($q): void
    {
        if (is_admin() || ! $q instanceof \WP_Query || ! $q->is_main_query()) {
            return;
        }
        if (! $q->is_search()) {
            return;
        }
        if (! $this->enabled()) {
            return;
        }

        // 1. Find matching product IDs for the search term.
        $term = (string) $q->get('s');
        if ('' === trim($term)) {
            return;
        }

        $matches = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            's'              => $term,
        ]);

        if (empty($matches)) {
            return;
        }

        // 2. For each product, take only the first product category (dedup).
        $cat_ids = [];
        foreach ($matches as $pid) {
            $cats = wp_get_post_terms($pid, 'product_cat', ['fields' => 'ids']);
            if (is_wp_error($cats) || empty($cats)) {
                continue;
            }
            $first = (int) $cats[0];
            if (! in_array($first, $cat_ids, true)) {
                $cat_ids[] = $first;
            }
        }

        if (empty($cat_ids)) {
            return;
        }

        // 3. Rewrite the main query into a product_cat query.
        $q->set('post_type', 'product'); // keep it "product" type so WP loads term templates
        $q->set('s', '');
        $q->set('name', '');
        $q->set('tax_query', [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $cat_ids,
            ],
        ]);

        // Ask WP_Query to return nothing for posts; we filter the archive links in the template below.
        $q->set('posts_per_page', 0);
    }
}
