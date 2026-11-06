<?php

namespace Navard\Support;

if (! defined('ABSPATH')) {
    exit;
}

final class Helpers
{

    /** Extract category slug from a full API id (split at FIRST "_"). */
    public static function group_from_api_id(string $api_id): string
    {
        $pos = strpos($api_id, '_');
        return false === $pos ? $api_id : substr($api_id, 0, $pos);
    }

    /**
     * Parse a price string coming from the API.
     * Returns:
     *   ['state' => 'ok',      'value' => 350000]
     *   ['state' => 'contact']                     ← "تماس بگیرید" / null
     *   ['state' => 'invalid']                     ← malformed
     */
    public static function parse_price($raw): array
    {
        if (null === $raw) {
            return ['state' => 'contact'];
        }

        if (is_int($raw) || is_float($raw)) {
            $n = (float) $raw;
            return $n > 0 ? ['state' => 'ok', 'value' => (int) round($n)] : ['state' => 'contact'];
        }

        $s = trim((string) $raw);
        if ('' === $s) {
            return ['state' => 'contact'];
        }

        $s_norm = PersianDate::to_ascii_digits($s);
        if (false !== mb_strpos($s_norm, 'تماس')) {
            return ['state' => 'contact'];
        }

        $clean = preg_replace('/[^\d]/', '', $s_norm);
        if ('' === $clean) {
            return ['state' => 'invalid'];
        }

        $n = (int) $clean;
        return $n > 0 ? ['state' => 'ok', 'value' => $n] : ['state' => 'invalid'];
    }

    public static function prefix_slug(string $fa): string
    {
        $slug = sanitize_title($fa);
        if ('' === $slug) {
            $slug = 'a' . substr(md5($fa), 0, 8);
        }
        return '_navard-' . $slug;
    }

    /**
     * Detect if the active theme is "Navard" (or a child of "Navard").
     * Checks stylesheet slug, template slug, text domain, and display name.
     */
    public static function is_navard_theme(): bool
    {
        static $cache = null;
        if (null !== $cache) {
            return $cache;
        }

        $stylesheet = strtolower((string) get_option('stylesheet'));
        $template   = strtolower((string) get_option('template'));
        if ('navard' === $stylesheet || 'navard' === $template) {
            return $cache = true;
        }

        $theme = wp_get_theme();
        return $cache = (
            'navard' === strtolower((string) $theme->get('TextDomain'))
            || 'navard' === strtolower((string) $theme->get('Name'))
        );
    }
}
