<?php

namespace Navard\Product;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shows "تماس بگیرید" for products flagged with _navard_contact_price.
 */
final class Frontend
{

    public function hooks(): void
    {
        add_filter('woocommerce_get_price_html', [$this, 'price_html'], 20, 2);
        add_filter('woocommerce_product_get_price', [$this, 'get_price'], 20, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'get_price'], 20, 2);
        add_filter('woocommerce_product_is_purchasable', [$this, 'purchasable'], 20, 2);
        add_filter('woocommerce_is_purchasable', [$this, 'purchasable'], 20, 2);
    }

    public function price_html(string $html, $product): string
    {
        if ($product instanceof \WC_Product && 'yes' === get_post_meta($product->get_id(), Meta::CONTACT_PRICE, true)) {
            return '<span class="navard-contact-price">تماس بگیرید</span>';
        }
        return $html;
    }

    public function get_price($price, $product)
    {
        if ($product instanceof \WC_Product && 'yes' === get_post_meta($product->get_id(), Meta::CONTACT_PRICE, true)) {
            return '';
        }
        return $price;
    }

    public function purchasable($purchasable, $product)
    {
        if ($product instanceof \WC_Product && 'yes' === get_post_meta($product->get_id(), Meta::CONTACT_PRICE, true)) {
            return false;
        }
        return $purchasable;
    }
}
