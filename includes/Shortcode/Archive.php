<?php

namespace Navard\Shortcode;

use Navard\Config;

if (! defined('ABSPATH')) {
    exit;
}

final class Archive
{

    public function hooks(): void
    {
        add_filter('template_include', [$this, 'render_archive'], 9999);
        add_action('template_redirect', [$this, 'maybe_redirect_product']);
    }

    private function replace_enabled(): bool
    {
        return 'yes' === (string) Config::get('replace_cat_archive', 'no');
    }

    private function hide_product_enabled(): bool
    {
        return 'yes' === (string) Config::get('hide_product_page', 'no');
    }

    /**
     * Redirect single product URLs to their first product category when the
     * "hide product page" option is on.
     */
    public function maybe_redirect_product(): void
    {
        if (is_admin() || ! $this->hide_product_enabled()) {
            return;
        }
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        $id   = get_queried_object_id();
        $cats = wp_get_post_terms($id, 'product_cat', ['fields' => 'ids']);

        if (! is_wp_error($cats) && ! empty($cats)) {
            // Pick the deepest category (child if present).
            $deepest = 0;
            $depth   = -1;
            foreach ($cats as $cid) {
                $ancestors = get_ancestors($cid, 'product_cat');
                $d         = count($ancestors);
                if ($d > $depth) {
                    $depth   = $d;
                    $deepest = (int) $cid;
                }
            }
            if ($deepest > 0) {
                $link = get_term_link($deepest, 'product_cat');
                if (! is_wp_error($link)) {
                    wp_safe_redirect($link, 301);
                    exit;
                }
            }
        }

        // Fallback: shop page.
        if (function_exists('wc_get_page_id')) {
            $shop = get_permalink(wc_get_page_id('shop'));
            if ($shop) {
                wp_safe_redirect($shop, 301);
                exit;
            }
        }
    }

    private function target(): ?array
    {
        if (is_admin() || ! $this->replace_enabled()) {
            return null;
        }

        if (function_exists('is_product_category') && is_product_category()) {
            return ['type' => 'term', 'attr' => 'cat'];
        }
        if (function_exists('is_product_tag') && is_product_tag()) {
            return ['type' => 'term', 'attr' => 'tag'];
        }
        if (function_exists('is_shop') && is_shop()) {
            return ['type' => 'shop'];
        }

        return null;
    }

    public function render_archive($template)
    {
        $target = $this->target();
        if (! $target) {
            return $template;
        }

        if ('shop' === $target['type']) {
            $html = $this->shop_html();
        } else {
            $term = get_queried_object();
            if (! $term instanceof \WP_Term) {
                return $template;
            }
            $html = '<div class="navard-archive-wrap" dir="rtl">'
                . do_shortcode('[navard ' . $target['attr'] . '_id="' . (int) $term->term_id . '"]')
                . '</div>';
        }

        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            global $_wp_current_template_content, $_wp_current_template_id;

            $_wp_current_template_id = get_stylesheet() . '//navard-archive';

            $_wp_current_template_content =
                '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
                . '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->'
                . '<main class="wp-block-group">'
                . '<!-- wp:html -->' . $html . '<!-- /wp:html -->'
                . '</main>'
                . '<!-- /wp:group -->'
                . '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';

            return ABSPATH . WPINC . '/template-canvas.php';
        }

        get_header();
        do_action('woocommerce_before_main_content');
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
        do_action('woocommerce_after_main_content');
        get_footer();
        exit;
    }

    /** Category link grid for the shop archive. */
    private function shop_html(): string
    {
        $parents = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => 0,
        ]);

        if (is_wp_error($parents) || empty($parents)) {
            return '';
        }

        $out  = '<div class="navard-archive-wrap navard-cat-grid" dir="rtl">';
        $out .= '<h3 class="navard-sc-title">دسته‌بندی محصولات</h3>';
        $out .= '<ul class="navard-cat-list">';

        foreach ($parents as $parent) {
            $link = get_term_link($parent);
            if (is_wp_error($link)) {
                continue;
            }

            $out .= '<li class="navard-cat-item">';
            $out .= '<a class="navard-cat-link" href="' . esc_url($link) . '">' . esc_html($parent->name) . '</a>';

            $children = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'parent'     => (int) $parent->term_id,
            ]);

            if (! is_wp_error($children) && ! empty($children)) {
                $out .= '<ul class="navard-cat-sublist">';
                foreach ($children as $child) {
                    $clink = get_term_link($child);
                    if (is_wp_error($clink)) {
                        continue;
                    }
                    $out .= '<li class="navard-cat-subitem">'
                        . '<a class="navard-cat-link" href="' . esc_url($clink) . '">' . esc_html($child->name) . '</a>'
                        . '</li>';
                }
                $out .= '</ul>';
            }

            $out .= '</li>';
        }

        $out .= '</ul>';
        $out .= '</div>';

        return $out;
    }
}
