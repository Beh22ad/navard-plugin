<?php

namespace Navard\Api;

use Navard\Config;

if (! defined('ABSPATH')) {
    exit;
}

final class Endpoints
{

    public static function list_key(): string
    {
        return Config::TRANSIENT_LIST;
    }

    public static function category_key(string $slug): string
    {
        return Config::TRANSIENT_CATEGORY . md5($slug);
    }

    public static function group_key(string $slug): string
    {
        return Config::TRANSIENT_GROUP . md5($slug);
    }

    public static function forget_list(): void
    {
        delete_transient(self::list_key());
    }

    public static function forget_category(string $slug): void
    {
        delete_transient(self::category_key($slug));
    }
}
