<?php

namespace Navard\Admin;

if (! defined('ABSPATH')) {
    exit;
}

final class Menu
{

    public const SLUG = 'navard';

    public function hooks(): void
    {
        add_action('admin_menu', [$this, 'register']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function register(): void
    {
        add_menu_page(
            'نورد',
            'نورد',
            'manage_woocommerce',
            self::SLUG,
            [$this, 'render'],
            'dashicons-update',
            56
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        $tabs = [
            'general' => 'عمومی',
            'import'  => 'درون‌ریزی',
            'update'  => 'بروز رسانی قیمت‌ها',
            'table'   => 'جدول محصولات',
            'log'     => 'لاگ',
        ];
        if (! isset($tabs[$tab])) {
            $tab = 'general';
        }

        echo '<div class="wrap navard-wrap" dir="rtl">';
        echo '<h1>نورد</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $k => $label) {
            $url = add_query_arg(['page' => self::SLUG, 'tab' => $k], admin_url('admin.php'));
            $cls = 'nav-tab' . ($k === $tab ? ' nav-tab-active' : '');
            echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        switch ($tab) {
            case 'import':
                (new AjaxImport())->render_page();
                break;
            case 'update':
                (new AjaxUpdate())->render_page();
                break;
            case 'table':
                (new Settings())->render_table_page();
                break;
            case 'log':
                (new AjaxLog())->render_page();
                break;
            default:
                (new Settings())->render_page();
        }

        echo '</div>';
    }

    public function assets(string $hook): void
    {
        $is_plugin = false !== strpos($hook, self::SLUG);
        $is_post   = ('post.php' === $hook || 'post-new.php' === $hook);

        if (! $is_plugin && ! $is_post) {
            return;
        }

        wp_enqueue_style('navard-admin', NAVARD_URL . 'assets/css/admin.css', [], NAVARD_VERSION);
        wp_enqueue_style('navard-product', NAVARD_URL . 'assets/css/product-panel.css', [], NAVARD_VERSION);

        wp_register_script('navard-common', '', [], NAVARD_VERSION, true);
        wp_enqueue_script('navard-common');
        wp_add_inline_script('navard-common', 'window.NavardCfg = ' . wp_json_encode([
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('navard_ajax'),
        ]) . ';');

        if (! $is_plugin) {
            wp_enqueue_script('navard-panel', NAVARD_URL . 'assets/js/product-panel.js', ['navard-common'], NAVARD_VERSION, true);
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        if ('general' === $tab || 'table' === $tab) {
            wp_enqueue_script('navard-settings', NAVARD_URL . 'assets/js/settings.js', ['navard-common'], NAVARD_VERSION, true);
        } elseif ('import' === $tab) {
            wp_enqueue_script('navard-import', NAVARD_URL . 'assets/js/import.js', ['navard-common'], NAVARD_VERSION, true);
        } elseif ('update' === $tab) {
            wp_enqueue_script('navard-update', NAVARD_URL . 'assets/js/update.js', ['navard-common'], NAVARD_VERSION, true);
        }
    }
}
