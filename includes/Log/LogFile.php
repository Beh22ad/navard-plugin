<?php

namespace Navard\Log;

use Navard\Config;

if (! defined('ABSPATH')) {
    exit;
}

final class LogFile
{

    private const DIR  = 'navard-logs';
    private const FILE = 'navard.log';
    private const MAX  = 2097152; // 2 MB

    public static function dir(): string
    {
        $up = wp_upload_dir();
        $d  = trailingslashit($up['basedir']) . self::DIR;
        if (! file_exists($d)) {
            wp_mkdir_p($d);
        }
        self::protect($d);
        return $d;
    }

    public static function path(): string
    {
        return trailingslashit(self::dir()) . self::FILE;
    }

    public static function enabled(): bool
    {
        return 'yes' === get_option(Config::LOG_OPTION, 'no');
    }

    public static function append(string $line): void
    {
        if (! self::enabled()) {
            return;
        }
        $path = self::path();
        if (file_exists($path) && filesize($path) > self::MAX) {
            // Rotate by truncating the older half (keep last ~1MB).
            $data = file_get_contents($path);
            if (false !== $data) {
                file_put_contents($path, substr($data, -1048576));
            }
        }
        $ts  = gmdate('Y-m-d H:i:s');
        $row = '[' . $ts . '] ' . $line . PHP_EOL;
        @file_put_contents($path, $row, FILE_APPEND | LOCK_EX);
    }

    public static function clear(): void
    {
        $path = self::path();
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public static function size_human(): string
    {
        $path = self::path();
        if (! file_exists($path)) {
            return '0 B';
        }
        $b = (int) filesize($path);
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($b >= 1024 && $i < 3) {
            $b /= 1024;
            $i++;
        }
        return round($b, 2) . ' ' . $u[$i];
    }

    private static function protect(string $dir): void
    {
        $ht = trailingslashit($dir) . '.htaccess';
        if (! file_exists($ht)) {
            @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        $ix = trailingslashit($dir) . 'index.html';
        if (! file_exists($ix)) {
            @file_put_contents($ix, '');
        }
    }
}
