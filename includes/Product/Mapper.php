<?php

namespace Navard\Product;

use Navard\Support\PersianDate;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Turns a raw API product dict + category context into a normalized shape
 * that Importer/Updater can apply uniformly.
 */
final class Mapper
{

    public static function title(array $raw, string $category_name, string $factory, string $tag): string
    {
        if (! empty($raw['عنوان کالا'])) {
            return (string) $raw['عنوان کالا'];
        }

        // First two non-meta values in JSON order.
        $parts = [];
        $skip  = ['price', 'قیمت (تومان)', 'نوسان قیمت', 'id', 'priceHistory'];
        foreach ($raw as $k => $v) {
            if (in_array((string) $k, $skip, true)) {
                continue;
            }
            if (! is_scalar($v)) {
                continue;
            }
            $v = trim((string) $v);
            if ('' === $v) {
                continue;
            }
            $parts[] = $v;
            if (count($parts) >= 2) {
                break;
            }
        }

        $prefix = '';
        if ('' !== $factory) {
            $prefix = $factory;
        } elseif ('' !== $tag) {
            $prefix = $category_name . ' - ' . $tag;
        } else {
            $prefix = $category_name;
        }

        $tail = implode(' - ', $parts);
        return '' === $tail ? $prefix : $prefix . ' - ' . $tail;
    }

    /**
     * Extract Persian-digit/Shamsi "last update" string from a product dict if present.
     * Falls back to the group-level last_update passed in.
     */
    public static function last_update(array $raw, string $fallback): string
    {
        foreach (['last_update', 'آخرین بروزرسانی'] as $k) {
            if (! empty($raw[$k]) && is_scalar($raw[$k])) {
                return (string) $raw[$k];
            }
        }
        return $fallback;
    }

    public static function checked_at(): string
    {
        return PersianDate::now_shamsi(true);
    }
}
