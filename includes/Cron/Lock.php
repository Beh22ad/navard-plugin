<?php

namespace Navard\Cron;

use Navard\Config;

if (! defined('ABSPATH')) {
    exit;
}

final class Lock
{

    /** Acquire a lock option. Returns true if acquired. */
    public static function acquire(string $key, int $ttl = 600): bool
    {
        $now = time();
        $v   = get_option($key);
        if (is_array($v) && isset($v['until']) && (int) $v['until'] > $now) {
            return false;
        }
        update_option($key, ['until' => $now + $ttl, 'pid' => getmypid() ?: 0], false);
        return true;
    }

    public static function release(string $key): void
    {
        delete_option($key);
    }

    public static function is_locked(string $key): bool
    {
        $v = get_option($key);
        return is_array($v) && isset($v['until']) && (int) $v['until'] > time();
    }
}
