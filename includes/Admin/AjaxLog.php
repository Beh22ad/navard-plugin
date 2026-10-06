<?php

namespace Navard\Admin;

use Navard\Config;
use Navard\Log\LogFile;

if (! defined('ABSPATH')) {
    exit;
}

final class AjaxLog
{

    public function hooks(): void
    {
        add_action('admin_post_navard_log_toggle', [$this, 'toggle']);
        add_action('admin_post_navard_log_clear', [$this, 'clear']);
        add_action('admin_post_navard_log_download', [$this, 'download']);
    }

    public function render_page(): void
    {
        $enabled = ('yes' === get_option(Config::LOG_OPTION, 'no'));
        $size    = LogFile::size_human();
?>
        <div class="navard-op" dir="rtl">
            <h2>لاگ</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="navard_log_toggle">
                <?php wp_nonce_field('navard_log_toggle'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="enabled" value="yes" <?php checked($enabled); ?>>
                        عیب یابی: فعالسازی نوشتن لاگ جهت عیب‌یابی عملکرد
                    </label>
                </p>
                <?php submit_button('ذخیره', 'secondary'); ?>
            </form>

            <p>حجم فایل لاگ: <code dir="ltr"><?php echo esc_html($size); ?></code></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <input type="hidden" name="action" value="navard_log_clear">
                <?php wp_nonce_field('navard_log_clear'); ?>
                <button type="submit" class="button">پاک کردن لاگ</button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <input type="hidden" name="action" value="navard_log_download">
                <?php wp_nonce_field('navard_log_download'); ?>
                <button type="submit" class="button">دانلود لاگ</button>
            </form>
        </div>
<?php
    }

    public function toggle(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }
        check_admin_referer('navard_log_toggle');
        $on = (($_POST['enabled'] ?? '') === 'yes') ? 'yes' : 'no';
        update_option(Config::LOG_OPTION, $on);
        wp_safe_redirect(add_query_arg(['page' => Menu::SLUG, 'tab' => 'log'], admin_url('admin.php')));
        exit;
    }

    public function clear(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }
        check_admin_referer('navard_log_clear');
        LogFile::clear();
        wp_safe_redirect(add_query_arg(['page' => Menu::SLUG, 'tab' => 'log'], admin_url('admin.php')));
        exit;
    }

    public function download(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }
        check_admin_referer('navard_log_download');
        $path = LogFile::path();
        if (! is_readable($path)) {
            wp_die('فایل لاگ موجود نیست.');
        }
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="navard-log-' . gmdate('Ymd-His') . '.txt"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
