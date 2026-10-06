<?php

namespace Navard;

use Navard\Admin\AjaxImport;
use Navard\Admin\AjaxKey;
use Navard\Admin\AjaxLog;
use Navard\Admin\AjaxUpdate;
use Navard\Admin\Menu;
use Navard\Admin\ProductPanel;
use Navard\Admin\Settings;
use Navard\Cron\Endpoint;
use Navard\Cron\Scheduler;
use Navard\Product\Frontend;
use Navard\Product\Taxonomies;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{

    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        if (! $this->woocommerce_active()) {
            add_action('admin_notices', [$this, 'notice_missing_wc']);
            return;
        }

        (new Taxonomies())->hooks();

        (new Menu())->hooks();
        (new Settings())->hooks();
        (new ProductPanel())->hooks();
        (new AjaxKey())->hooks();
        (new AjaxImport())->hooks();
        (new AjaxUpdate())->hooks();
        (new AjaxLog())->hooks();
        (new Scheduler())->hooks();
        (new Endpoint())->hooks();
        (new Frontend())->hooks();
    }

    public function woocommerce_active(): bool
    {
        return class_exists('WooCommerce') && function_exists('wc_get_product');
    }

    public function notice_missing_wc(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        echo '<div class="notice notice-error"><p>'
            . esc_html__('افزونه نورد برای کار کردن به ووکامرس نیاز دارد.', 'navard')
            . '</p></div>';
    }

    public static function activate(): void
    {
        if (! get_option(Config::OPTION_KEY)) {
            update_option(Config::OPTION_KEY, Config::defaults());
        }
        if (! get_option(Config::CRON_SECRET)) {
            update_option(Config::CRON_SECRET, wp_generate_password(40, false, false));
        }
        // Register taxonomy immediately so it exists right after activation.
        (new Taxonomies())->register_all();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        Scheduler::unschedule();
        delete_option(Config::LOCK_IMPORT);
        delete_option(Config::LOCK_UPDATE);
    }
}
