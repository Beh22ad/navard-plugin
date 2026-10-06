<?php

namespace Navard;

if (! defined('ABSPATH')) {
    exit;
}

final class Autoloader
{

    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'load']);
    }

    public static function load(string $class): void
    {
        $prefix = 'Navard\\';
        if (0 !== strpos($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $relative = str_replace('\\', '/', $relative);
        $file     = NAVARD_DIR . 'includes/' . $relative . '.php';
        if (is_readable($file)) {
            require_once $file;
        }
    }
}
