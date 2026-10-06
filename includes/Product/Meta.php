<?php

namespace Navard\Product;

if (! defined('ABSPATH')) {
    exit;
}

final class Meta
{

    public const API_ID          = '_navard_api_id';
    public const AUTO_UPDATE     = '_navard_auto_update';
    public const FACTORY_META    = '_navard_factory';
    public const TAG_META        = '_navard_tag';
    public const LAST_UPDATE     = '_navard_last_update';
    public const CHECKED_AT      = '_navard_checked_at';
    public const PRICE_HISTORY   = '_navard_price_history';
    public const PRICE_CHANGE    = '_navard_price_change';
    public const CONTACT_PRICE   = '_navard_contact_price';
    public const FALLBACK        = '_navard_fallback';
    public const MOD_TYPE        = '_navard_mod_type';
    public const MOD_VALUE       = '_navard_mod_value';
    public const ROUND_ENABLED   = '_navard_round_enabled';
    public const ROUND_UNIT      = '_navard_round_unit';
    public const HAS_TITLE       = '_navard_has_title';
    public const COLUMNS         = '_navard_columns';

    public static function auto_update(int $product_id): string
    {
        $v = (string) get_post_meta($product_id, self::AUTO_UPDATE, true);
        return 'yes' === $v ? 'yes' : 'no';
    }
}
