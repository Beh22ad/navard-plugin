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
        add_action('admin_post_navard_save_table_settings', [$this, 'save_table']);
    }

    public function render_page(): void
    {
        $s         = Config::all();
        $available = Config::available_endpoints();
        $ep        = (string) $s['endpoint'];
        $secret    = (string) get_option(Config::CRON_SECRET, '');
        $cron      = rest_url('navard/v1/cron/update');
?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="navard-form">
            <input type="hidden" name="action" value="navard_save_settings">
            <?php wp_nonce_field('navard_save_settings'); ?>

            <h2>عمومی</h2>
            <table class="form-table">
                <tr>
                    <th>نقطه اتصال</th>
                    <td>
                        <select name="endpoint">
                            <?php if (isset($available['main'])) : ?>
                                <option value="main" <?php selected($ep, 'main'); ?>>سرور اصلی</option>
                            <?php endif; ?>
                            <?php if (isset($available['emergency'])) : ?>
                                <option value="emergency" <?php selected($ep, 'emergency'); ?>>سرور اضطراری</option>
                            <?php endif; ?>
                        </select>
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
                    <th>روند کردن قیمت</th>
                    <td>
                        <label><input type="checkbox" id="navard-round-enabled" name="round_enabled" value="yes"
                                <?php checked($s['round_enabled'], 'yes'); ?>> برای روند کردن قیمت دریافتی از api این گزینه را
                            روشن کنید</label>
                        <p class="navard-round-unit-wrap">
                            <input type="text" dir="ltr" class="navard-ltr" name="round_unit"
                                value="<?php echo esc_attr((string) $s['round_unit']); ?>" placeholder="100 / 1000 / 100000">
                        </p>
                    </td>
                </tr>
            </table>

            <h2>تنظیمات cpanel</h2>
            <p>افزونه روزانه سه بار قیمت‌ها را بروز رسانی می‌کند. اگر به هر دلیلی کرون جاب وردپرس به خوبی کار نکرد، دستور زیر را
                در کرون جاب cpanel هاست خود تنظیم کنید. روزانه ۲ یا ۳ بار.</p>
            <textarea dir="ltr" rows="3" class="large-text code"
                readonly>curl -s "<?php echo esc_url($cron); ?>?secret=<?php echo esc_attr($secret); ?>" > /dev/null 2>&1</textarea>
            <p class="description">آپدیت بعدی: <?php echo esc_html(Scheduler::describe()); ?></p>

            <?php submit_button('ذخیره تنظیمات'); ?>
        </form>
    <?php
    }

    public function render_table_page(): void
    {
        $s       = Config::all();
        $replace = ('yes' === (string) ($s['replace_cat_archive'] ?? 'no'));
        $hide    = ('yes' === (string) ($s['hide_product_page'] ?? 'no'));
    ?>
        <div class="navard-op" dir="rtl">
            <h2>جدول محصولات</h2>

            <p>برای نمایش محصولات یک دسته‌بندی یا برچسب به‌صورت جدول، از شورت‌کد زیر در هر برگه یا نوشته استفاده کنید:</p>

            <p><code dir="ltr">[navard cat="angel--aluminium"]</code></p>
            <p><code dir="ltr">[navard tag="ورق-st52-ضخامت-80"]</code></p>

            <p class="description">
                مقدار <code>cat</code> یا <code>tag</code> همان slug (نامک) دسته یا برچسب است. برای پیدا کردن آن، به
                <strong>محصولات ← دسته‌ها</strong> یا <strong>محصولات ← برچسب‌ها</strong> بروید و روی نام مورد نظر کلیک کنید؛
                فیلد «نامک» همان slug است.
            </p>

            <hr>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="navard_save_table_settings">
                <?php wp_nonce_field('navard_save_table_settings'); ?>

                <h3>تنظیمات نمایش</h3>
                <p class="description">در این بخش می‌توانید نحوه نمایش صفحات پیش‌فرض ووکامرس را تغییر دهید.</p>

                <p class="description" style="color:#b32d2e;">
                    ⚠ این گزینه‌ها برای تم نورد طراحی شده‌اند و ممکن است در تم‌های دیگر به درستی کار نکنند.
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="replace_cat_archive" value="yes" <?php checked($replace); ?>>
                        نمایش صفحه دسته‌بندی و تگ ووکامرس به صورت جدول محصولات
                    </label>
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="hide_product_page" value="yes" <?php checked($hide); ?>>
                        ریدایرکت صفحه محصول به صفحه دسته‌بندی
                    </label>
                </p>

                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
<?php
    }

    public function save(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }
        check_admin_referer('navard_save_settings');

        $in   = wp_unslash($_POST);
        $prev = Config::all();
        $available = Config::available_endpoints();

        $ep = (string) ($in['endpoint'] ?? 'main');
        if (! isset($available[$ep])) {
            $ep = 'main';
        }

        $data = [
            'endpoint'            => $ep,
            'api_key'             => sanitize_text_field($in['api_key'] ?? ''),
            'fallback'            => in_array($in['fallback'] ?? 'keep', ['keep', 'contact'], true) ? $in['fallback'] : 'keep',
            'mod_type'            => in_array($in['mod_type'] ?? 'none', ['none', 'percent', 'fixed'], true) ? $in['mod_type'] : 'none',
            'mod_value'           => sanitize_text_field($in['mod_value'] ?? ''),
            'round_enabled'       => (($in['round_enabled'] ?? '') === 'yes') ? 'yes' : 'no',
            'round_unit'          => sanitize_text_field($in['round_unit'] ?? ''),
            'replace_cat_archive' => (string) ($prev['replace_cat_archive'] ?? 'no'),
            'hide_product_page'   => (string) ($prev['hide_product_page'] ?? 'no'),
        ];

        update_option(Config::OPTION_KEY, $data);
        wp_safe_redirect(add_query_arg(['page' => Menu::SLUG, 'tab' => 'general', 'saved' => 1], admin_url('admin.php')));
        exit;
    }

    public function save_table(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }
        check_admin_referer('navard_save_table_settings');

        $in   = wp_unslash($_POST);
        $data = Config::all();

        $data['replace_cat_archive'] = (($in['replace_cat_archive'] ?? '') === 'yes') ? 'yes' : 'no';
        $data['hide_product_page']   = (($in['hide_product_page'] ?? '') === 'yes') ? 'yes' : 'no';

        update_option(Config::OPTION_KEY, $data);
        wp_safe_redirect(add_query_arg(['page' => Menu::SLUG, 'tab' => 'table', 'saved' => 1], admin_url('admin.php')));
        exit;
    }
}
