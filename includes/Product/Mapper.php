<?php

namespace Navard\Product;

use Navard\Support\PersianDate;

if (! defined('ABSPATH')) {
    exit;
}

final class Mapper
{

    public static function title(array $raw, string $category_name, string $factory, string $tag): string
    {
        if (! empty($raw['عنوان کالا'])) {
            return (string) $raw['عنوان کالا'];
        }

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
     * Returns the group-level last_update. Never looks inside the product.
     * If a product dict carries its own last_update, prefer it; otherwise use the group value.
     */
    public static function last_update(array $raw, string $group_last_update): string
    {
        if (isset($raw['last_update']) && is_scalar($raw['last_update'])) {
            $v = trim((string) $raw['last_update']);
            if ('' !== $v) {
                return $v;
            }
        }
        return trim($group_last_update);
    }

    public static function checked_at(): string
    {
        return PersianDate::now_shamsi(true);
    }
}
