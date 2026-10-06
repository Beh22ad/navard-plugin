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
        // Late priority so we win over the theme / WooCommerce template loaders
        add_filter('template_include', [$this, 'render_archive'], 9999);
    }

    /**
     * Which archive type is being requested, and its config key / shortcode attribute.
     * Returns null when the current page is not a replaceable archive.
     */
    private function target(): ?array
    {
        if (is_admin()) {
            return null;
        }

        if (
            function_exists('is_product_category') && is_product_category()
            && 'yes' === (string) Config::get('replace_cat_archive', 'no')
        ) {
            return ['attr' => 'cat'];
        }

        if (
            function_exists('is_product_tag') && is_product_tag()
            && 'yes' === (string) Config::get('replace_cat_archive', 'no')
        ) {
            return ['attr' => 'tag'];
        }

        return null;
    }

    /**
     * Replace the whole archive output.
     * - Block themes: render theme header/footer template parts + our shortcode
     *   through WordPress' block template canvas (no header.php needed).
     * - Classic themes: get_header() / get_footer().
     */
    public function render_archive($template)
    {
        $target = $this->target();
        if (! $target) {
            return $template;
        }

        $term = get_queried_object();
        if (! $term instanceof \WP_Term) {
            return $template;
        }

        $html = '<div class="navard-archive-wrap" dir="rtl">'
            . do_shortcode('[navard ' . $target['attr'] . '_id="' . (int) $term->term_id . '"]')
            . '</div>';

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

        // Classic theme
        get_header();
        do_action('woocommerce_before_main_content');
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
        do_action('woocommerce_after_main_content');
        get_footer();
        exit;
    }
}
