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

        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 13.565 10.583"><path fill="#ffffff" d="M-10.382 60.475c-.77.104-1.52.507-2.244.778-1.525.57-3.036 1.178-4.57 1.726-1.126.402-2.282.673-3.04 1.682-1.596 2.124-.598 5.6 2.074 6.271.356.09.746.133 1.114.098 1.017-.097 1.888-.645 2.735-1.176 1.413-.886 2.74-1.902 4.095-2.872q.707-.504 1.392-1.037c.373-.29.727-.545.976-.957.969-1.606.176-4.08-1.762-4.474a2.4 2.4 0 0 0-.77-.04m-3.554 6.791c-.07-.38-.08-.754-.197-1.13-.299-.962-.909-1.814-1.769-2.35-.3-.186-.69-.389-1.048-.418.158-.12.405-.167.59-.236q.663-.252 1.326-.502c1.072-.401 2.141-.811 3.21-1.218.589-.224 1.19-.544 1.835-.5 1.02.07 1.82.906 2.018 1.883.043.208.14.578.053.782-.048.11-.3.203-.4.265-.38.233-.768.453-1.147.69-.856.536-1.729 1.046-2.587 1.578-.623.386-1.238.812-1.884 1.156m-3.915-3.55c.34-.04.706.005 1.032.099 2.306.662 3.229 3.775 1.824 5.662a2.7 2.7 0 0 1-.923.79c-.32.163-.66.275-1.016.32-.333.041-.67.023-.999-.045-2.497-.517-3.434-3.83-1.89-5.733.248-.305.555-.578.907-.757a3.1 3.1 0 0 1 1.065-.335m.066.772a2.4 2.4 0 0 0-.885.3c-1.996 1.141-1.383 4.552.836 5.003q.382.078.77.025c.299-.04.596-.146.851-.308 1.848-1.17 1.418-4.414-.753-4.96a2.2 2.2 0 0 0-.82-.06" transform="translate(21.002 -60.459)"/></svg>'
        );
        add_menu_page(
            'نورد',
            'نورد',
            'manage_woocommerce',
            self::SLUG,
            [$this, 'render'],
            $icon,
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
