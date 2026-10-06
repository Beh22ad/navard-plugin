<?php

namespace Navard\Product;

use Navard\Parser\CategoryParser;
use Navard\Price\Calculator;
use Navard\Price\Rules;
use Navard\Support\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

final class Importer
{

    public function import(array $row): array
    {
        $api_id = (string) ($row['id'] ?? '');
        if ('' === $api_id) {
            return ['ok' => false, 'error' => 'شناسه خالی'];
        }

        $cat_slug = (string) ($row['category_slug'] ?? '');
        if ('' === $cat_slug) {
            return ['ok' => false, 'error' => "دسته یافت نشد برای {$api_id}"];
        }

        $cat = (new CategoryParser())->load($cat_slug);
        if (! $cat['ok']) {
            return ['ok' => false, 'error' => "API {$cat_slug}: " . $cat['error']];
        }

        $entry = $this->find_entry($cat['data'], $api_id);
        if (null === $entry) {
            return ['ok' => false, 'error' => "محصول {$api_id} در پاسخ دسته یافت نشد"];
        }

        [$group, $raw] = $entry;

        $ctx = [
            'parent_slug'   => (string) ($row['parent_slug'] ?? ''),
            'parent_name'   => (string) ($row['parent_name'] ?? ''),
            'category_slug' => (string) ($cat['data']['category_slug'] ?? $cat_slug),
            'category_name' => (string) ($cat['data']['category_name'] ?? ''),
            'factory'       => (string) ($group['factory'] ?? ''),
            'tag'           => (string) ($group['tag'] ?? ''),
            'group_last'    => (string) ($group['last_update'] ?? ''),
        ];

        $product_id = Finder::by_api_id($api_id);
        $is_new     = (0 === $product_id);

        $has_title = isset($raw['عنوان کالا']) && '' !== trim((string) $raw['عنوان کالا']);

        $title = Mapper::title($raw, $ctx['category_name'], $ctx['factory'], $ctx['tag']);

        // Column order = the API JSON key order, minus the fields we handle separately.
        $columns = $this->collect_columns($raw);

        $product = $is_new ? new \WC_Product_Simple() : wc_get_product($product_id);
        if (! $product instanceof \WC_Product) {
            return ['ok' => false, 'error' => "خطا در ساخت محصول {$api_id}"];
        }

        $product->set_name($title);
        $product->set_status('publish');
        $product->set_sku($api_id);
        $product->set_catalog_visibility('visible');

        $attrs = [];
        foreach (Attributes::map_from_product($raw) as $a) {
            $attr = new \WC_Product_Attribute();
            $attr->set_id(wc_attribute_taxonomy_id_by_name(str_replace('pa_', '', $a['name'])));
            $attr->set_name($a['name']);
            $attr->set_options($a['options']);
            $attr->set_visible(true);
            $attr->set_variation(false);
            $attrs[] = $attr;
        }
        $product->set_attributes($attrs);

        $product->save();
        $product_id = $product->get_id();

        $this->assign_categories($product_id, $ctx);

        if ('' !== $ctx['tag']) {
            wp_set_object_terms($product_id, [$ctx['tag']], 'product_tag', false);
        }

        update_post_meta($product_id, Meta::API_ID, $api_id);
        update_post_meta($product_id, Meta::AUTO_UPDATE, 'yes');
        update_post_meta($product_id, Meta::FACTORY_META, $ctx['factory']);
        update_post_meta($product_id, Meta::TAG_META, $ctx['tag']);
        update_post_meta($product_id, Meta::LAST_UPDATE, Mapper::last_update($raw, $ctx['group_last']));
        update_post_meta($product_id, Meta::CHECKED_AT, Mapper::checked_at());
        update_post_meta($product_id, Meta::HAS_TITLE, $has_title ? 'yes' : 'no');
        update_post_meta($product_id, Meta::COLUMNS, wp_json_encode($columns, JSON_UNESCAPED_UNICODE));

        if (! empty($raw['priceHistory']) && is_array($raw['priceHistory'])) {
            update_post_meta($product_id, Meta::PRICE_HISTORY, wp_json_encode(array_slice($raw['priceHistory'], -50), JSON_UNESCAPED_UNICODE));
        }
        if (isset($raw['نوسان قیمت'])) {
            update_post_meta($product_id, Meta::PRICE_CHANGE, sanitize_text_field((string) $raw['نوسان قیمت']));
        }

        $this->apply_price($product_id, $raw);

        return ['ok' => true, 'product_id' => $product_id, 'created' => $is_new];
    }

    private function find_entry(array $data, string $api_id): ?array
    {
        foreach ($data['groups'] ?? [] as $g) {
            foreach ($g['products'] ?? [] as $p) {
                if (isset($p['id']) && (string) $p['id'] === $api_id) {
                    return [$g, $p];
                }
            }
        }
        return null;
    }

    /**
     * Return the ordered list of API field names we want to show as columns.
     * Excludes fields handled separately.
     *
     * @return array<int,string>
     */
    private function collect_columns(array $raw): array
    {
        $skip = ['price', 'id', 'priceHistory'];
        $out  = [];

        foreach ($raw as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if (in_array($key, $skip, true)) {
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            $out[] = $key;
        }

        return $out;
    }

    private function assign_categories(int $product_id, array $ctx): void
    {
        $parent_id = 0;

        if ('' !== $ctx['parent_name']) {
            $parent_id = Taxonomies::ensure_term('product_cat', $ctx['parent_name'], $ctx['parent_slug']);
        }

        if ('' !== $ctx['category_name']) {
            $child_id = Taxonomies::ensure_term('product_cat', $ctx['category_name'], $ctx['category_slug'], $parent_id);
            if ($child_id > 0) {
                wp_set_object_terms($product_id, [$child_id], 'product_cat', false);
                return;
            }
        }

        if ($parent_id > 0) {
            wp_set_object_terms($product_id, [$parent_id], 'product_cat', false);
        }
    }

    private function apply_price(int $product_id, array $raw): void
    {
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
            return;
        }

        $fallback = Rules::fallback($product_id);
        if ('contact' === $fallback) {
            $this->set_contact_state($product_id);
        }
    }

    private function write_price(int $product_id, int $price): void
    {
        $p = wc_get_product($product_id);
        if (! $p instanceof \WC_Product) {
            return;
        }
        if ((int) $p->get_regular_price() !== $price) {
            $p->set_regular_price((string) $price);
            $p->set_price((string) $price);
            $p->save();
        }
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
