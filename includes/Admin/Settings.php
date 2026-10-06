<?php

namespace Navard\Admin;

use Navard\Config;
use Navard\Cron\Scheduler;

if (! defined('ABSPATH')) {
    exit;
}

final class Settings
{

    public function hooks(): void
    {
        add_action('admin_post_navard_save_settings', [$this, 'save']);
    }

    public function render_page(): void
    {
        $s      = Config::all();
        $eps    = Config::endpoints();
        $ep     = $s['endpoint'];
        $secret = (string) get_option(Config::CRON_SECRET, '');
        $cron   = rest_url('navard/v1/cron/update');
?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="navard-form">
            <input type="hidden" name="action" value="navard_save_settings">
            <?php wp_nonce_field('navard_save_settings'); ?>

            <h2>عمومی</h2>
            <table class="form-table">
                <tr>
                    <th>نقطه اتصال</th>
                    <td>
                        <label><input type="radio" name="endpoint" value="main" <?php checked($ep, 'main'); ?>> سرور
                            اصلی</label>
                        <?php if (! empty($eps['emergency'])) : ?>
                            <label style="margin-right:16px"><input type="radio" name="endpoint" value="emergency"
                                    <?php checked($ep, 'emergency'); ?>> سرور اضطراری</label>
                        <?php else : ?>
                            <em style="margin-right:16px;color:#888">سرور اضطراری تنظیم نشده است</em>
                        <?php endif; ?>
                        <p class="description">آدرس فعلی: <code dir="ltr"><?php echo esc_html(Config::endpoint($ep)); ?></code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>کلید دسترسی</th>
                    <td>
                        <input type="text" dir="ltr" class="regular-text navard-ltr" id="navard-api-key" name="api_key"
                            value="<?php echo esc_attr((string) $s['api_key']); ?>">
                        <button type="button" class="button" id="navard-check-key">بررسی کلید</button>
                        <span id="navard-key-status" class="navard-status"></span>
                    </td>
                </tr>
            </table>

            <h2>قیمت</h2>
            <table class="form-table">
                <tr>
                    <th>در صورت مشخص نبودن قیمت روز</th>
                    <td>
                        <select name="fallback">
                            <option value="keep" <?php selected($s['fallback'], 'keep'); ?>>قیمت روزهای قبل را استفاده کن
                            </option>
                            <option value="contact" <?php selected($s['fallback'], 'contact'); ?>>قیمت را به تماس بگیرید تغییر
                                بده</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>اصلاح قیمت دریافتی</th>
                    <td>
                        <select name="mod_type" id="navard-mod-type">
                            <option value="none" <?php selected($s['mod_type'], 'none'); ?>>بدون تغییر</option>
                            <option value="percent" <?php selected($s['mod_type'], 'percent'); ?>>تغییر درصدی</option>
                            <option value="fixed" <?php selected($s['mod_type'], 'fixed'); ?>>تغییر ثابت</option>
                        </select>
                        <p class="navard-mod-value-wrap">
                            <input type="text" dir="ltr" class="navard-ltr" name="mod_value"
                                value="<?php echo esc_attr((string) $s['mod_value']); ?>" placeholder="+10 / -10 / 100">
                        </p>
                        <p class="description navard-help-percent">برای مثال <code>10</code> به معنی افزایش ۱۰ درصدی قیمت و
                            <code dir="ltr">-10</code> به معنی کاهش ۱۰ درصدی قیمت است.
                        </p>
                        <p class="description navard-help-fixed">برای مثال <code>1000</code> به معنی افزایش قیمت هزار تومانی و
                            <code dir="ltr">-1000</code> به معنی کاهش قیمت هزار تومانی است.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>گرد کردن قیمت</th>
                    <td>
                        <label><input type="checkbox" id="navard-round-enabled" name="round_enabled" value="yes"
                                <?php checked($s['round_enabled'], 'yes'); ?>> روند کردن قیمت</label>
                        <p class="navard-round-unit-wrap">
                            <input type="text" dir="ltr" class="navard-ltr" name="round_unit"
                                value="<?php echo esc_attr((string) $s['round_unit']); ?>" placeholder="100 / 1000 / 100000">
                        </p>
                    </td>
                </tr>
            </table>

            <h2>کرون خودکار</h2>
            <p>برای اجرای خودکار بروزرسانی از طریق کرون سی‌پنل، این دستور را در Cron Jobs قرار دهید (هر ۸ ساعت یا ۳ بار در روز):
            </p>
            <textarea dir="ltr" rows="3" class="large-text code"
                readonly>curl -s "<?php echo esc_url($cron); ?>?secret=<?php echo esc_attr($secret); ?>" > /dev/null 2>&1</textarea>
            <p class="description">برنامه داخلی وردپرس: <?php echo esc_html(Scheduler::describe()); ?></p>

            <?php submit_button('ذخیره تنظیمات'); ?>
        </form>
<?php
    }

    public function save(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }
        check_admin_referer('navard_save_settings');

        $in = wp_unslash($_POST);
        $data = [
            'endpoint'      => in_array($in['endpoint'] ?? 'main', ['main', 'emergency'], true) ? $in['endpoint'] : 'main',
            'api_key'       => sanitize_text_field($in['api_key'] ?? ''),
            'fallback'      => in_array($in['fallback'] ?? 'keep', ['keep', 'contact'], true) ? $in['fallback'] : 'keep',
            'mod_type'      => in_array($in['mod_type'] ?? 'none', ['none', 'percent', 'fixed'], true) ? $in['mod_type'] : 'none',
            'mod_value'     => sanitize_text_field($in['mod_value'] ?? ''),
            'round_enabled' => (($in['round_enabled'] ?? '') === 'yes') ? 'yes' : 'no',
            'round_unit'    => sanitize_text_field($in['round_unit'] ?? ''),
        ];

        update_option(Config::OPTION_KEY, $data);
        wp_safe_redirect(add_query_arg(['page' => Menu::SLUG, 'tab' => 'general', 'saved' => 1], admin_url('admin.php')));
        exit;
    }
}
