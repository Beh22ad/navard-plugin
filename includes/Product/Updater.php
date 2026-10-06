<?php

namespace Navard\Product;

use Navard\Parser\CategoryParser;
use Navard\Price\Calculator;
use Navard\Price\Rules;
use Navard\Support\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

final class Updater
{

    /** Fetch + cache the category response, return ok/data/error. */
    public function get_category_response(string $slug): array
    {
        $cat = (new CategoryParser())->load($slug);
        if (! $cat['ok']) {
            return ['ok' => false, 'data' => [], 'error' => $cat['error']];
        }
        return ['ok' => true, 'data' => $cat['data'], 'error' => ''];
    }

    /** Build a lookup: api_id => [ group, raw product ]. */
    public function index_response(array $data): array
    {
        $map = [];
        foreach ($data['groups'] ?? [] as $g) {
            foreach ($g['products'] ?? [] as $p) {
                if (isset($p['id'])) {
                    $map[(string) $p['id']] = ['group' => $g, 'raw' => $p];
                }
            }
        }
        return $map;
    }

    public function update_one(int $product_id, string $api_id, array $map): array
    {
        if (! isset($map[$api_id])) {
            // Product no longer exists in the API under this category.
            // Not an error — just skip it.
            return ['ok' => true, 'product_id' => $product_id, 'skipped' => true];
        }

        $raw   = $map[$api_id]['raw'];
        $group = $map[$api_id]['group'];

        $parsed = Helpers::parse_price($raw['price'] ?? ($raw['قیمت (تومان)'] ?? null));

        if ('ok' === $parsed['state']) {
            $new = Calculator::apply(
                (int) $parsed['value'],
                Rules::mod_type($product_id),
                Rules::mod_value($product_id),
                Rules::round_enabled($product_id),
                Rules::round_unit($product_id)
            );
            $this->write_price($product_id, $new);
            delete_post_meta($product_id, Meta::CONTACT_PRICE);
        } else {
            $fallback = Rules::fallback($product_id);
            if ('contact' === $fallback) {
                $this->set_contact_state($product_id);
            }
        }

        update_post_meta($product_id, Meta::LAST_UPDATE, Mapper::last_update($raw, (string) ($group['last_update'] ?? '')));
        update_post_meta($product_id, Meta::CHECKED_AT, Mapper::checked_at());

        if (isset($raw['نوسان قیمت'])) {
            update_post_meta($product_id, Meta::PRICE_CHANGE, sanitize_text_field((string) $raw['نوسان قیمت']));
        }
        if (! empty($raw['priceHistory']) && is_array($raw['priceHistory'])) {
            update_post_meta($product_id, Meta::PRICE_HISTORY, wp_json_encode(array_slice($raw['priceHistory'], -50), JSON_UNESCAPED_UNICODE));
        }

        return ['ok' => true, 'product_id' => $product_id];
    }

    private function write_price(int $product_id, int $price): void
    {
        $p = wc_get_product($product_id);
        if (! $p instanceof \WC_Product) {
            return;
        }
        $current = (int) $p->get_regular_price();
        if ($current === $price) {
            return;
        }
        $p->set_regular_price((string) $price);
        $p->set_price((string) $price);
        $p->save();
    }

    private function set_contact_state(int $product_id): void
    {
        $p = wc_get_product($product_id);
        if (! $p instanceof \WC_Product) {
            return;
        }
        $p->set_regular_price('');
        $p->set_sale_price('');
        $p->set_price('');
        $p->save();
        update_post_meta($product_id, Meta::CONTACT_PRICE, 'yes');
    }
}
