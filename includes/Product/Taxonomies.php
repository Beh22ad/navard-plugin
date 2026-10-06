<?php

namespace Navard\Product;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Factory is NOT registered as a WooCommerce taxonomy.
 * It is stored only in product meta (_navard_factory).
 * This class only provides helpers for product_cat / product_tag.
 */
final class Taxonomies
{

    public const FACTORY_META = '_navard_factory';

    public function hooks(): void
    {
        // nothing to register
    }

    public function register_all(): void
    {
        // no-op
    }

    public static function find_term(string $taxonomy, string $name, string $slug): int
    {
        if (! taxonomy_exists($taxonomy)) {
            return 0;
        }
        if ('' !== $slug) {
            $t = get_term_by('slug', $slug, $taxonomy);
            if ($t instanceof \WP_Term) {
                return (int) $t->term_id;
            }
        }
        if ('' !== $name) {
            $t = get_term_by('name', $name, $taxonomy);
            if ($t instanceof \WP_Term) {
                return (int) $t->term_id;
            }
        }
        return 0;
    }

    public static function ensure_term(string $taxonomy, string $name, string $slug = '', int $parent_id = 0): int
    {
        if ('' === $name || ! taxonomy_exists($taxonomy)) {
            return 0;
        }
        if ('' === $slug) {
            $slug = sanitize_title($name);
        }
        if ('' === $slug) {
            $slug = 't' . substr(md5($name), 0, 8);
        }

        $existing = self::find_term($taxonomy, $name, $slug);
        if ($existing > 0) {
            if (is_taxonomy_hierarchical($taxonomy) && $parent_id > 0) {
                $term = get_term($existing, $taxonomy);
                if ($term instanceof \WP_Term && (int) $term->parent !== $parent_id) {
                    wp_update_term($existing, $taxonomy, ['parent' => $parent_id]);
                }
            }
            return $existing;
        }

        $args = ['slug' => $slug];
        if ($parent_id > 0 && is_taxonomy_hierarchical($taxonomy)) {
            $args['parent'] = $parent_id;
        }

        $r = wp_insert_term($name, $taxonomy, $args);
        if (is_wp_error($r)) {
            $existing = self::find_term($taxonomy, $name, $slug);
            return $existing > 0 ? $existing : 0;
        }
        return (int) $r['term_id'];
    }
}
