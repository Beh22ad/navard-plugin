<?php

namespace Navard\Support;

if (! defined('ABSPATH')) {
    exit;
}

final class Validator
{

    public static function positive_int($v): ?int
    {
        $v = PersianDate::to_ascii_digits((string) $v);
        $v = preg_replace('/[^\d]/', '', $v);
        if ('' === $v) {
            return null;
        }
        $i = (int) $v;
        return $i > 0 ? $i : null;
    }

    /** "+10" / "10" / "-10" → 10.0 / 10.0 / -10.0 ; null if invalid. */
    public static function signed_number($v): ?float
    {
        $v = PersianDate::to_ascii_digits((string) $v);
        $v = trim($v);
        if ('' === $v) {
            return null;
        }
        if (! preg_match('/^[+-]?\d+(\.\d+)?$/', $v)) {
            return null;
        }
        return (float) $v;
    }
}
