<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Never delete products or user data automatically.
// Remove only plugin-owned options/transients and the log file.

delete_option('navard_settings');
delete_option('navard_log_enabled');
delete_option('navard_cron_secret');
delete_option('navard_import_state');
delete_option('navard_update_state');
delete_option('navard_lock_import');
delete_option('navard_lock_update');

delete_transient('navard_product_list');

global $wpdb;
$like = $wpdb->esc_like('_transient_navard_') . '%';
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
$like_t = $wpdb->esc_like('_transient_timeout_navard_') . '%';
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_t));

$up = wp_upload_dir();
$log_dir = trailingslashit($up['basedir']) . 'navard-logs';
if (is_dir($log_dir)) {
    $files = glob(trailingslashit($log_dir) . '*');
    if (is_array($files)) {
        foreach ($files as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
    @rmdir($log_dir);
}
