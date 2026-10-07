<?php

namespace Navard\Admin;

use Navard\Config;
use Navard\Product\Meta;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductPanel
{
    public function hooks(): void
    {
        add_action('woocommerce_product_data_tabs', [$this, 'tab']);
        add_action('woocommerce_product_data_panels', [$this, 'panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save']);
    }

    public function tab(array $tabs): array
    {
        $tabs['navard'] = [
            'label'    => 'نورد',
            'target'   => 'navard_product_data',
            'class'    => ['show_if_simple', 'show_if_variable', 'navard_options_tab'],
            'priority' => 65,
        ];
        return $tabs;
    }

    public function panel(): void
    {
        global $post;
        $id = (int) $post->ID;

        $auto    = Meta::auto_update($id);
        $api_id  = (string) get_post_meta($id, Meta::API_ID, true);
        $lastup  = (string) get_post_meta($id, Meta::LAST_UPDATE, true);
        $checked = (string) get_post_meta($id, Meta::CHECKED_AT, true);

        $fb    = (string) get_post_meta($id, Meta::FALLBACK, true);
        $modt  = (string) get_post_meta($id, Meta::MOD_TYPE, true);
        $modv  = (string) get_post_meta($id, Meta::MOD_VALUE, true);
        $round = (string) get_post_meta($id, Meta::ROUND_ENABLED, true);
        $runi  = (string) get_post_meta($id, Meta::ROUND_UNIT, true);

        $list_url = "https://mrnargil.ir/products/navard-membership";

        echo '<div id="navard_product_data" class="panel woocommerce_options_panel hidden">';
        wp_nonce_field('navard_save_product', 'navard_product_nonce');

        echo '<div class="options_group">';

        // 1. Auto update
        woocommerce_wp_checkbox([
            'id'          => 'navard_auto',
            'value'       => $auto,
            'label'       => 'آپدیت اتوماتیک',
            'description' => 'قیمت این محصول به طور اتوماتیک آپدیت شود',
            'cbvalue'     => 'yes',
        ]);

        // 2. API ID
        woocommerce_wp_text_input([
            'id'          => 'navard_api_id',
            'value'       => $api_id,
            'label'       => 'کد محصول',
            'placeholder' => 'مثال: profile--french_1805928887-2',
            'style'         => 'direction: ltr; text-align: left;width: 100%;',
            'description' => '<a href="' . esc_url($list_url) . '" target="_blank" rel="noopener">مشاهده لیست محصولات</a>',
            'desc_tip'    => false,
        ]);

        // 3. Read-only dates
        woocommerce_wp_text_input([
            'id'          => 'navard_last_update',
            'value'       => $lastup,
            'label'       => 'قیمت برای',
            'style'         => 'direction: ltr; text-align: left;',
            'custom_attributes' => ['readonly' => 'readonly'],
        ]);

        woocommerce_wp_text_input([
            'id'          => 'navard_checked_at',
            'value'       => $checked,
            'label'       => 'تاریخ چک کردن قیمت',
            'style'         => 'direction: ltr; text-align: left;',
            'custom_attributes' => ['readonly' => 'readonly'],
        ]);

        echo '</div><div class="options_group">';

        // 4. Fallback (Select)
        woocommerce_wp_select([
            'id'      => 'navard_fallback',
            'value'   => $fb ?: '',
            'label'   => 'در صورت مشخص نبودن قیمت روز',
            'options' => [
                ''        => 'پیش‌فرض',
                'keep'    => 'قیمت روزهای قبل را استفاده کن',
                'contact' => 'قیمت را به تماس بگیرید تغییر بده',
            ],
        ]);

        echo '</div><div class="options_group">';

        // 5. Modification (Select)
        woocommerce_wp_select([
            'id'      => 'navard_mod_type',
            'value'   => $modt ?: '',
            'label'   => 'اصلاح قیمت دریافتی',
            'options' => [
                ''        => 'پیش‌فرض',
                'none'    => 'بدون تغییر',
                'percent' => 'تغییر درصدی',
                'fixed'   => 'تغییر با مقدار ثابت',
            ],
        ]);

        woocommerce_wp_text_input([
            'id'          => 'navard_mod_value',
            'value'       => $modv,
            'label'       => 'مقدار اصلاح',
            'placeholder' => '',
            'style'         => 'direction: ltr; text-align: left;',
            'description' => '<span class="navard-help-percent">برای مثال <code>10</code> به معنی افزایش ۱۰ درصدی قیمت و <code dir="ltr">-10</code> به معنی کاهش ۱۰ درصدی قیمت است.</span>' .
                '<span class="navard-help-fixed">برای مثال <code>1000</code> به معنی افزایش قیمت هزار تومانی و <code dir="ltr">-1000</code> به معنی کاهش قیمت هزار تومانی است.</span>',
            'desc_tip'    => false,
            'wrapper_class' => 'navard-mod-value-field',
        ]);

        echo '</div><div class="options_group">';

        // 6. Rounding
        woocommerce_wp_checkbox([
            'id'          => 'navard_round',
            'value'       => $round,
            'label'       => 'گرد کردن قیمت',
            'description' => 'روند کردن قیمت',
            'cbvalue'     => 'yes',
        ]);

        woocommerce_wp_text_input([
            'id'          => 'navard_round_unit',
            'value'       => $runi,
            'label'       => 'واحد گرد کردن',
            'placeholder' => '100 / 1000 / 100000',
            'wrapper_class' => 'navard-round-unit-field',
        ]);

        echo '</div>';
        echo '</div>';
    }

    public function save(int $post_id): void
    {
        if (! isset($_POST['navard_product_nonce'])) {
            return;
        }
        if (! wp_verify_nonce(sanitize_key(wp_unslash($_POST['navard_product_nonce'])), 'navard_save_product')) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $in = wp_unslash($_POST);

        update_post_meta($post_id, Meta::AUTO_UPDATE, ($in['navard_auto'] ?? '') === 'yes' ? 'yes' : 'no');

        $api_id = isset($in['navard_api_id']) ? trim(sanitize_text_field($in['navard_api_id'])) : '';
        if ('' !== $api_id) {
            update_post_meta($post_id, Meta::API_ID, $api_id);
            $existing_sku = (string) get_post_meta($post_id, '_sku', true);
            if ($existing_sku !== $api_id) {
                update_post_meta($post_id, '_sku', $api_id);
                if (function_exists('wc_delete_product_transients')) {
                    wc_delete_product_transients($post_id);
                }
            }
        }

        $fb = (string) ($in['navard_fallback'] ?? '');
        $fb = in_array($fb, ['', 'keep', 'contact'], true) ? $fb : '';
        update_post_meta($post_id, Meta::FALLBACK, $fb);

        $mt = (string) ($in['navard_mod_type'] ?? '');
        $mt = in_array($mt, ['', 'none', 'percent', 'fixed'], true) ? $mt : '';
        update_post_meta($post_id, Meta::MOD_TYPE, $mt);
        update_post_meta($post_id, Meta::MOD_VALUE, sanitize_text_field($in['navard_mod_value'] ?? ''));

        update_post_meta($post_id, Meta::ROUND_ENABLED, ($in['navard_round'] ?? '') === 'yes' ? 'yes' : 'no');
        update_post_meta($post_id, Meta::ROUND_UNIT, sanitize_text_field($in['navard_round_unit'] ?? ''));
    }
}
