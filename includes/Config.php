<?php

namespace Navard;

if (! defined('ABSPATH')) {
    exit;
}

final class Config
{

    public const OPTION_KEY   = 'navard_settings';
    public const LOG_OPTION   = 'navard_log_enabled';
    public const CRON_SECRET  = 'navard_cron_secret';
    public const STATE_IMPORT = 'navard_import_state';
    public const STATE_UPDATE = 'navard_update_state';
    public const LOCK_IMPORT  = 'navard_lock_import';
    public const LOCK_UPDATE  = 'navard_lock_update';

    public const TRANSIENT_LIST     = 'navard_product_list';
    public const TRANSIENT_CATEGORY = 'navard_cat_';
    public const TRANSIENT_GROUP    = 'navard_group_';

    public const TTL_LIST     = DAY_IN_SECONDS;
    public const TTL_CATEGORY = 15 * MINUTE_IN_SECONDS;
    public const TTL_GROUP    = 30 * MINUTE_IN_SECONDS;

    public const BATCH_IMPORT = 20;
    public const BATCH_UPDATE = 20;

    public static function endpoints(): array
    {
        return [
            'main'      => untrailingslashit((string) NAVARD_API_MAIN),
            'emergency' => untrailingslashit((string) NAVARD_API_EMERGENCY),
        ];
    }

    public static function available_endpoints(): array
    {
        $all = self::endpoints();
        $out = [];
        foreach ($all as $key => $url) {
            if ('' !== $url) {
                $out[$key] = $url;
            }
        }
        return $out;
    }

    public static function endpoint(?string $which = null): string
    {
        $which = $which ?: (string) self::get('endpoint', 'main');
        $all   = self::endpoints();
        return $all[$which] ?? $all['main'];
    }

    public static function defaults(): array
    {
        return [
            'endpoint'            => 'main',
            'api_key'             => '',
            'fallback'            => 'keep',
            'mod_type'            => 'none',
            'mod_value'           => '',
            'round_enabled'       => 'no',
            'round_unit'          => '',
            'replace_cat_archive' => 'no',
            'hide_product_page'   => 'no',
        ];
    }

    public static function all(): array
    {
        $saved = get_option(self::OPTION_KEY, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    public static function batch_import(): int
    {
        return max(1, (int) self::BATCH_IMPORT);
    }

    public static function batch_update(): int
    {
        return max(1, (int) self::BATCH_UPDATE);
    }
}
