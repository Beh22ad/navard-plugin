<?php

namespace Navard\Log;

if (! defined('ABSPATH')) {
    exit;
}

final class Logger
{

    public static function debug(string $msg): void
    {
        LogFile::append('DEBUG ' . self::scrub($msg));
    }

    public static function error(string $msg): void
    {
        LogFile::append('ERROR ' . self::scrub($msg));
    }

    private static function scrub(string $msg): string
    {
        // Never log auth tokens.
        return preg_replace('/auth=[^&\s]+/', 'auth=***', $msg) ?? $msg;
    }
}
