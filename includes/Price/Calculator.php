<?php

namespace Navard\Price;

use Navard\Support\Validator;

if (! defined('ABSPATH')) {
    exit;
}

final class Calculator
{

    /**
     * Apply modification + rounding to a numeric price.
     *
     * @param int    $price
     * @param string $mod_type   none|percent|fixed
     * @param string $mod_value  e.g. "+10", "-10", "100"
     * @param bool   $round_on
     * @param string $round_unit positive integer as string
     */
    public static function apply(int $price, string $mod_type, string $mod_value, bool $round_on, string $round_unit): int
    {
        $out = $price;

        if ('percent' === $mod_type) {
            $pct = Validator::signed_number($mod_value);
            if (null !== $pct) {
                $out = (int) round($price * (1 + $pct / 100));
            }
        } elseif ('fixed' === $mod_type) {
            $delta = Validator::signed_number($mod_value);
            if (null !== $delta) {
                $out = (int) round($price + $delta);
            }
        }

        if ($round_on) {
            $unit = Validator::positive_int($round_unit);
            if (null !== $unit && $unit > 1) {
                $out = (int) (round($out / $unit) * $unit);
            }
        }

        return max(0, $out);
    }
}
