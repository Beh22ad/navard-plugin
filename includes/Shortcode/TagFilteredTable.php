<?php

namespace Navard\Shortcode;

use Navard\Product\Meta;
use Navard\Support\Helpers;
use Navard\Support\PersianDate;

if (! defined('ABSPATH')) {
    exit;
}

final class TagFilteredTable
{
    private const FAMILY_SLUG = [
        'rebar'    => 'rebar',
        'beam-h'   => 'beam-h',
        'profile'  => 'profile',
        'angel'    => 'angel',
        'uchannel' => 'uchannel',
    ];

    private const FACTORY_KEYS = [
        Meta::FACTORY_META,
        '_navard_factory',
        'navard_factory',
        '_navard_factory_',
        'navard_factory_',
        '_navard_factory__',
        'navard_factory__',
    ];

    private const TAG_KEYS = [
        Meta::TAG_META,
        '_navard_tag',
        'navard_tag',
        '_navard_tag_',
        'navard_tag_',
        '_navard_tag__',
        'navard_tag__',
    ];

    private const TRANSIENT_PREFIX = 'navard_tf_ids_';
    private const CACHE_TTL        = 30 * DAY_IN_SECONDS;

    private ?array $pending_ctx = null;
    private bool   $done        = false;

    public function hooks(): void
    {
        add_action('wp_loaded', [$this, 'on_wp_loaded'], 0);
        add_action('template_redirect', [$this, 'on_template_redirect'], 0);
        add_filter('template_include', [$this, 'on_template_include'], 999);
        add_filter('redirect_canonical', [$this, 'block_canonical'], 10, 2);

        // Navard theme: turn /product-category/rebar/tg-foo into a normal
        // product_cat archive (no 404) so the theme template + [navard] run.
        if (Helpers::is_navard_theme()) {
            add_filter('request', [$this, 'rescue_tag_request'], 1);
            add_filter('redirect_canonical', [$this, 'block_tag_canonical'], 10, 2);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Navard theme request rescue                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Rewrites /product-category/rebar/tg-<value>/ so WP resolves the
     * `rebar` product_cat archive (is_404 = false) and the theme's
     * taxonomy-product_cat template runs; [navard] then renders the
     * filtered table via render_shortcode_if_match().
     */
    public function rescue_tag_request(array $qv): array
    {
        if (is_admin() || empty($qv['product_cat']) || ! is_string($qv['product_cat'])) {
            return $qv;
        }
        if ('no' === (string) \Navard\Config::get('enable_tag_pages', 'yes')) {
            return $qv;
        }

        $raw = rawurldecode($qv['product_cat']);
        $segments = array_values(array_filter(explode('/', trim($raw, '/')), static fn($s) => '' !== $s));
        if (count($segments) < 2) {
            return $qv;
        }

        $last = (string) end($segments);

        // Support both /rebar/tg-foo and /rebar/tg/foo forms.
        if ('tg' === $last || 'tag' === $last) {
            return $qv; // malformed, let WP handle it
        }

        if (0 === stripos($last, 'tg/') || 0 === stripos($last, 'tag/')) {
            // handled by the "rebar/tg/foo" segment form below
            return $qv;
        }

        if (! preg_match('/^(tg|tag)-/i', $last)) {
            return $qv;
        }

        // If a real category with this slug exists, leave it alone.
        if (get_term_by('slug', $last, 'product_cat')) {
            return $qv;
        }

        // The remaining path must be a real category (e.g., "rebar").
        array_pop($segments);
        $parent_slug = (string) end($segments);
        if ('' === $parent_slug) {
            return $qv;
        }
        if (! get_term_by('slug', $parent_slug, 'product_cat')) {
            return $qv;
        }

        $qv['product_cat'] = implode('/', $segments);
        return $qv;
    }

    /**
     * Prevents WP from canonical-redirecting /rebar/tg-… back to /rebar/.
     */
    public function block_tag_canonical($redirect, $requested)
    {
        $path = (string) wp_parse_url((string) $requested, PHP_URL_PATH);
        if (preg_match('#/product-category/.+/(tg-|tag-)#iu', $path)) {
            return false;
        }
        return $redirect;
    }

    /* ------------------------------------------------------------------ */
    /* Hooks                                                               */
    /* ------------------------------------------------------------------ */

    public function on_wp_loaded(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        if ('no' === (string) \Navard\Config::get('enable_tag_pages', 'yes')) {
            return;
        }
        $ctx = $this->parse_request();
        if (null === $ctx) {
            return;
        }
        $ids = $this->resolve_product_ids($ctx);
        if (empty($ids)) {
            return;
        }
        $this->pending_ctx = $ctx + ['ids' => $ids];
    }

    public function on_template_redirect(): void
    {
        $this->debug_log('template_redirect');
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        if (isset($_GET['navard_tag_debug'])) {
            $this->debug_dump();
            exit;
        }

        // Navard theme: the theme template renders [navard], which picks up
        // the tg- URL via render_shortcode_if_match(). Do not render-and-exit.
        if (Helpers::is_navard_theme()) {
            return;
        }

        if ($this->done || null === $this->pending_ctx) {
            return;
        }
        $this->render_and_exit();
    }

    public function on_template_include($template)
    {
        $this->debug_log('template_include');
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return $template;
        }
        if (isset($_GET['navard_tag_debug'])) {
            $this->debug_dump();
            exit;
        }
        if (Helpers::is_navard_theme()) {
            return $template;
        }
        if ($this->done || null === $this->pending_ctx) {
            return $template;
        }
        $this->render_and_exit();
        return $template;
    }

    public function block_canonical($redirect, $requested)
    {
        $path = (string) wp_parse_url((string) $requested, PHP_URL_PATH);
        if (preg_match('#/product-category/[^/]+/(tg-|tag-|tg/|tag/)#u', $path)) {
            return false;
        }
        return $redirect;
    }

    /* ------------------------------------------------------------------ */
    /* Shortcode helper (used by CategoryTable::detect_filtered_request)  */
    /* ------------------------------------------------------------------ */

    public function render_shortcode_if_match(): string
    {
        if ('no' === (string) \Navard\Config::get('enable_tag_pages', 'yes')) {
            return '';
        }

        $ctx = $this->parse_request();
        if (null === $ctx) {
            return '';
        }

        $ids = $this->resolve_product_ids($ctx);
        if (empty($ids)) {
            return '';
        }

        $renderer = new FilteredTable();
        $title    = $ctx['term']->name . ' - ' . $ctx['value'];

        return $renderer->render_family_table(
            $ctx['family'],
            $ctx['term'],
            $title,
            $ids,
            false
        );
    }

    /* ------------------------------------------------------------------ */
    /* Render (non-Navard themes)                                          */
    /* ------------------------------------------------------------------ */

    private function render_and_exit(): void
    {
        if ($this->done) {
            return;
        }
        $this->done = true;
        $ctx = $this->pending_ctx;
        if (! is_array($ctx) || empty($ctx['ids'])) {
            return;
        }

        global $wp_query;
        if (is_object($wp_query)) {
            $wp_query->is_404            = false;
            $wp_query->is_tax            = true;
            $wp_query->is_archive        = true;
            $wp_query->queried_object    = $ctx['term'];
            $wp_query->queried_object_id = $ctx['term']->term_id;
        }

        $renderer = new FilteredTable();
        $title    = $ctx['term']->name . ' - ' . $ctx['value'];

        $html = $renderer->render_family_table($ctx['family'], $ctx['term'], $title, $ctx['ids'], false);

        status_header(200);
        nocache_headers();

        $renderer->output_archive_public($ctx['term'], $html);
        exit;
    }

    /* ------------------------------------------------------------------ */
    /* Debug & Parsing                                                     */
    /* ------------------------------------------------------------------ */

    private function debug_log(string $msg): void
    {
        if (isset($_GET['navard_tag_debug'])) {
            error_log('NAVARD TagFilteredTable: ' . $msg);
        }
    }

    private function parse_request(): ?array
    {
        $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = trim($path, '/');
        if ('' === $path) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn($s) => '' !== $s));
        if (count($segments) < 3 || 'product-category' !== $segments[0]) {
            return null;
        }

        $raw_value = '';
        $term_slug = '';

        if (count($segments) >= 4 && in_array($segments[count($segments) - 2], ['tg', 'tag'], true)) {
            $raw_value = $segments[count($segments) - 2] . '-' . end($segments);
            $term_slug = implode('/', array_slice($segments, 1, -2));
        } else {
            $raw_value = (string) end($segments);
            $term_slug = implode('/', array_slice($segments, 1, -1));
            if (! preg_match('/^(tg|tag)-/i', rawurldecode($raw_value))) {
                return null;
            }
        }

        if ('' === $term_slug) {
            return null;
        }

        $decoded = $this->decode_tag_value($raw_value);
        if ('' === $decoded) {
            return null;
        }

        $needle = trim(preg_replace('/^(tg|tag)-/i', '', $decoded));
        if ('' === $needle) {
            return null;
        }

        $term = get_term_by('slug', $term_slug, 'product_cat');
        if (! $term instanceof \WP_Term) {
            return null;
        }

        $family = $this->resolve_family($term);
        if (null === $family) {
            return null;
        }

        return [
            'family' => $family,
            'term'   => $term,
            'value'  => $needle,
        ];
    }

    private function decode_tag_value(string $raw): string
    {
        $s = rawurldecode($raw);
        $s = str_replace('+', ' ', $s);
        $s = PersianDate::to_ascii_digits($s);
        $s = str_replace(["\xC2\xA0", "\xE2\x80\x8C", "\xE2\x80\x8F", "\xE2\x80\x8E"], ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    private function resolve_family(\WP_Term $term): ?string
    {
        $families = self::FAMILY_SLUG;
        if (isset($families[$term->slug])) {
            return $families[$term->slug];
        }
        $ancestors = get_ancestors($term->term_id, 'product_cat', 'taxonomy');
        foreach ($ancestors as $aid) {
            $a = get_term($aid, 'product_cat');
            if ($a instanceof \WP_Term && isset($families[$a->slug])) {
                return $families[$a->slug];
            }
        }
        return null;
    }

    private function resolve_product_ids(array $ctx): array
    {
        $cache_key = self::TRANSIENT_PREFIX . md5($ctx['family'] . '|' . $ctx['term']->term_id . '|' . $this->norm((string) $ctx['value']));
        $cached    = get_transient($cache_key);

        if (is_array($cached) && ! empty($cached)) {
            return array_map('intval', $cached);
        }

        $ids = $this->scan_by_meta($ctx);
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (! empty($ids)) {
            set_transient($cache_key, $ids, self::CACHE_TTL);
        }
        return $ids;
    }

    private function scan_by_meta(array $ctx): array
    {
        $needle = (string) $ctx['value'];

        $q = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => [$ctx['term']->term_id],
                    'include_children' => true,
                ],
            ],
        ]);

        $keys = array_values(array_unique(array_merge(self::FACTORY_KEYS, self::TAG_KEYS)));
        $out  = [];

        foreach ($q->posts as $pid) {
            $pid = (int) $pid;
            foreach ($keys as $k) {
                $v = get_post_meta($pid, $k, true);
                if (is_string($v) && $this->eq($v, $needle)) {
                    $out[] = $pid;
                    continue 2;
                }
            }
        }

        return $out;
    }

    private function eq(string $a, string $b): bool
    {
        $a = $this->norm($a);
        $b = $this->norm($b);
        if ('' === $a || '' === $b) {
            return false;
        }
        return $a === $b;
    }

    private function norm(string $s): string
    {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = PersianDate::to_ascii_digits($s);
        $s = str_replace(['ي', 'ك'], ['ی', 'ک'], $s);
        $s = str_replace(["\xC2\xA0", "\xE2\x80\x8C", "\xE2\x80\x8F", "\xE2\x80\x8E"], ' ', $s);
        $s = str_replace(['-', '_', "\xE2\x80\x93", "\xE2\x80\x94"], ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = rtrim($s, "_ \t\n\r\0\x0B");
        return mb_strtolower(trim($s));
    }

    private function debug_dump(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    }
}
