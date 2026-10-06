<?php

namespace Navard\Price;

use Navard\Config;
use Navard\Product\Meta;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves per-product overrides vs global defaults.
 * Per-product value overrides the global setting only if it is non-empty.
 */
final class Rules
{

    public static function fallback(int $product_id): string
    {
        $v = (string) get_post_meta($product_id, Meta::FALLBACK, true);
        if (in_array($v, ['keep', 'contact'], true)) {
            return $v;
        }
        $g = (string) Config::get('fallback', 'keep');
        return in_array($g, ['keep', 'contact'], true) ? $g : 'keep';
    }

    public static function mod_type(int $product_id): string
    {
        $v = (string) get_post_meta($product_id, Meta::MOD_TYPE, true);
        if (in_array($v, ['none', 'percent', 'fixed'], true)) {
            return $v;
        }
        $g = (string) Config::get('mod_type', 'none');
        return in_array($g, ['none', 'percent', 'fixed'], true) ? $g : 'none';
    }

    public static function mod_value(int $product_id): string
    {
        $v = (string) get_post_meta($product_id, Meta::MOD_VALUE, true);
        if ('' !== $v) {
            return $v;
        }
        return (string) Config::get('mod_value', '');
    }

    public static function round_enabled(int $product_id): bool
    {
        $v = (string) get_post_meta($product_id, Meta::ROUND_ENABLED, true);
        if ('yes' === $v || 'no' === $v) {
            return 'yes' === $v;
        }
        return 'yes' === (string) Config::get('round_enabled', 'no');
    }

    public static function round_unit(int $product_id): string
    {
        $v = (string) get_post_meta($product_id, Meta::ROUND_UNIT, true);
        if ('' !== $v) {
            return $v;
        }
        return (string) Config::get('round_unit', '');
    }
}
