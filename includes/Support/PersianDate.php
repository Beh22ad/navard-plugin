<?php

namespace Navard\Support;

if (! defined('ABSPATH')) {
    exit;
}

final class PersianDate
{

    /** Gregorian → Jalali (no external lib). */
    public static function gregorian_to_jalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2   = ($gm > 2) ? ($gy + 1) : $gy;
        $days  = 355666 + (365 * $gy) + ((int) (($gy2 + 3) / 4))
            - ((int) (($gy2 + 99) / 100))
            + ((int) (($gy2 + 399) / 400))
            + $gd + $g_d_m[$gm - 1];
        $jy    = -1595 + (33 * (int) ($days / 12053));
        $days %= 12053;
        $jy   += 4 * (int) ($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy   += (int) (($days - 1) / 365);
            $days  = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int) ($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int) (($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }

    public static function now_tehran(): array
    {
        try {
            $dt = new \DateTime('now', new \DateTimeZone('Asia/Tehran'));
        } catch (\Exception $e) {
            $dt = new \DateTime('now');
        }
        return [
            (int) $dt->format('Y'),
            (int) $dt->format('n'),
            (int) $dt->format('j'),
            (int) $dt->format('G'),
            (int) $dt->format('i')
        ];
    }

    public static function now_shamsi(bool $with_time = true): string
    {
        [$y, $m, $d, $h, $i] = self::now_tehran();
        [$jy, $jm, $jd]      = self::gregorian_to_jalali($y, $m, $d);
        $s = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        if ($with_time) {
            $s .= sprintf(' %02d:%02d', $h, $i);
        }
        return $s;
    }

    /** Convert Persian digits to ASCII. */
    public static function to_ascii_digits(string $s): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $s  = str_replace($fa, $en, $s);
        return str_replace($ar, $en, $s);
    }
}
