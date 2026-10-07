<?php

namespace Navard\Admin;

if (! defined('ABSPATH')) {
    exit;
}

final class UpdateChecker
{

    private const API_URL    = 'https://mrnargil-updater.spaindoh.workers.dev/';
    private const TTL        = HOUR_IN_SECONDS;
    private const TIMEOUT    = 3;

    private static string $plugin_file = '';

    public function hooks(): void
    {
        add_action('admin_notices', [$this, 'maybe_notice']);
    }

    /** Call once on activation so cache clears after updates. */
    public static function registerCacheCleaner(string $plugin_file): void
    {
        self::$plugin_file = $plugin_file;
        add_action('upgrader_process_complete', [__CLASS__, 'clear_cache'], 10, 2);
    }

    public function maybe_notice(): void
    {
        if (! $this->is_plugin_page()) {
            return;
        }

        $html = self::run(NAVARD_FILE, (string) \Navard\Config::get('api_key', ''));
        if ('' === $html) {
            return;
        }

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput — escaped inside run()
    }

    private function is_plugin_page(): bool
    {
        if (! is_admin() || ! current_user_can('manage_woocommerce')) {
            return false;
        }
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        return 'navard' === $page;
    }

    public static function run(string $plugin_file, string $license_key): string
    {
        self::$plugin_file = $plugin_file;

        $plugin_data = get_file_data($plugin_file, [
            'Version'    => 'Version',
            'TextDomain' => 'Text Domain',
        ]);

        $namespace = (string) ($plugin_data['TextDomain'] ?? '');
        $version   = (string) ($plugin_data['Version'] ?? '');

        if ('' === $namespace || '' === $version) {
            return '';
        }

        $transient_key = $namespace . '_update_response';
        $data          = get_transient($transient_key);

        if (false === $data) {
            $api_url = add_query_arg(
                [
                    'namespace'   => $namespace,
                    'version'     => $version,
                    'license_key' => $license_key,
                    'site'        => site_url(),
                ],
                self::API_URL
            );

            $response = wp_remote_get($api_url, ['timeout' => self::TIMEOUT]);

            $data = [];

            if (! is_wp_error($response) && 200 === (int) wp_remote_retrieve_response_code($response)) {
                $body = (string) wp_remote_retrieve_body($response);
                $json = json_decode($body, true);
                if (is_array($json)) {
                    $data = $json;
                }
            }

            // Cache the result (empty array on failure) so we never hammer the server.
            set_transient($transient_key, $data, self::TTL);
        }

        if (empty($data) || empty($data['update_available'])) {
            return '';
        }

        $message  = isset($data['message']) ? (string) $data['message'] : '';
        $url      = isset($data['download_url']) ? (string) $data['download_url'] : '';
        $latest   = isset($data['latest_version']) ? (string) $data['latest_version'] : '';

        $out  = '<div class="notice notice-error is-dismissible"><p>';
        $out .= esc_html($message);

        if ('' !== $url && '' !== $latest) {
            $out .= ' <a href="' . esc_url($url) . '" target="_blank" rel="noopener">دانلود نسخه ' . esc_html($latest) . '</a>';
        }

        $out .= '</p></div>';

        return $out;
    }

    public static function clear_cache($upgrader, $options): void
    {
        if ('' === self::$plugin_file) {
            return;
        }

        $plugin_data = get_file_data(self::$plugin_file, ['TextDomain' => 'Text Domain']);
        $namespace   = (string) ($plugin_data['TextDomain'] ?? '');
        if ('' === $namespace) {
            return;
        }

        delete_transient($namespace . '_update_response');
    }
}
