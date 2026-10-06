<?php

namespace Navard\Shortcode;

use Navard\Product\Meta;

if (! defined('ABSPATH')) {
    exit;
}

final class CategoryTable
{

    public function hooks(): void
    {
        add_shortcode('navard', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function assets(): void
    {
        wp_register_style(
            'navard-shortcode',
            NAVARD_URL . 'assets/css/shortcode.css',
            [],
            NAVARD_VERSION
        );
    }

    public function render($atts): string
    {
        $atts = shortcode_atts(
            [
                'cat'    => '',
                'tag'    => '',
                'cat_id' => '',
                'tag_id' => '',
            ],
            $atts,
            'navard'
        );

        $cat    = sanitize_text_field((string) $atts['cat']);
        $tag    = sanitize_text_field((string) $atts['tag']);
        $cat_id = (int) $atts['cat_id'];
        $tag_id = (int) $atts['tag_id'];

        $term     = null;
        $taxonomy = '';

        if ($cat_id > 0) {
            $t = get_term($cat_id, 'product_cat');
            if ($t instanceof \WP_Term) {
                $term     = $t;
                $taxonomy = 'product_cat';
            }
        } elseif ($tag_id > 0) {
            $t = get_term($tag_id, 'product_tag');
            if ($t instanceof \WP_Term) {
                $term     = $t;
                $taxonomy = 'product_tag';
            }
        } elseif ('' !== $cat) {
            $t = get_term_by('slug', $cat, 'product_cat');
            if ($t instanceof \WP_Term) {
                $term     = $t;
                $taxonomy = 'product_cat';
            }
        } elseif ('' !== $tag) {
            $t = get_term_by('slug', $tag, 'product_tag');
            if ($t instanceof \WP_Term) {
                $term     = $t;
                $taxonomy = 'product_tag';
            }
        }

        if (! $term instanceof \WP_Term || '' === $taxonomy) {
            return '';
        }

        $products = $this->query((int) $term->term_id, $taxonomy);
        if (empty($products)) {
            return '';
        }

        wp_enqueue_style('navard-shortcode');

        $groups = $this->group($products);

        ob_start();
        echo '<div class="navard-shortcode" dir="rtl">';
        echo '<h3 class="navard-sc-title">' . esc_html($term->name) . '</h3>';

        foreach ($groups as $label => $group) {
            echo '<div class="navard-sc-group">';
            echo '<div class="navard-sc-group-head">';

            echo '<h4 class="navard-sc-group-title">' . ('' !== $label ? esc_html($label) : '&nbsp;') . '</h4>';

            if ('' !== $group['last_update']) {
                echo '<span class="navard-sc-updated">'
                    . '<svg class="navard-sc-cal" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">'
                    . '<path fill="currentColor" d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm12 8v9H5v-9h14zM5 8V6h14v2H5z"/>'
                    . '</svg>'
                    . 'آخرین بروزرسانی: ' . esc_html($group['last_update'])
                    . '</span>';
            }
            echo '</div>';

            $columns = $this->columns($group);

            echo '<div class="navard-sc-scroll">';
            echo '<table class="navard-sc-table">';
            echo '<thead><tr>';
            foreach ($columns as $col) {
                echo '<th>' . esc_html($col) . '</th>';
            }
            echo '<th class="navard-sc-th-chart">نمودار قیمت</th>';
            echo '</tr></thead><tbody>';

            foreach ($group['rows'] as $row) {
                echo '<tr>';
                foreach ($columns as $col) {
                    $val = isset($row[$col]) ? (string) $row[$col] : '';
                    $cls = ('قیمت (تومان)' === $col) ? ' class="navard-sc-td-price"' : '';
                    echo '<td' . $cls . '>' . esc_html($val) . '</td>';
                }
                echo '<td class="navard-sc-td-chart">' . $this->chart_placeholder() . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    private function chart_placeholder(): string
    {
        return '<span class="navard-sc-chart" role="img" aria-label="نمودار قیمت">'
            . '<svg viewBox="0 0 32 32" width="26" height="26" aria-hidden="true" focusable="false">'
            . '<circle cx="16" cy="16" r="15" fill="#c62828"/>'
            . '<rect x="8"  y="17" width="3" height="7"  fill="#fff"/>'
            . '<rect x="12.5" y="13" width="3" height="11" fill="#fff"/>'
            . '<rect x="17" y="10" width="3" height="14" fill="#fff"/>'
            . '<rect x="21.5" y="15" width="3" height="9"  fill="#fff"/>'
            . '<rect x="7" y="25" width="18" height="1.2" fill="#fff"/>'
            . '</svg>'
            . '</span>';
    }

    /**
     * @return array<int,\WC_Product>
     */
    private function query(int $term_id, string $taxonomy): array
    {
        $q = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'tax_query'      => [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => [$term_id],
                ],
            ],
        ]);

        $out = [];
        foreach ($q->posts as $post) {
            $p = wc_get_product($post->ID);
            if ($p instanceof \WC_Product) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /**
     * @param array<int,\WC_Product> $products
     * @return array<string,array{last_update:string,rows:array<int,array<string,string>>,order:array<int,string>}>
     */
    private function group(array $products): array
    {
        $groups = [];

        foreach ($products as $product) {
            $id      = $product->get_id();
            $factory = (string) get_post_meta($id, Meta::FACTORY_META, true);
            $tag     = (string) get_post_meta($id, Meta::TAG_META, true);

            $label = '' !== $factory ? $factory : $tag;

            if (! isset($groups[$label])) {
                $groups[$label] = [
                    'last_update' => (string) get_post_meta($id, Meta::LAST_UPDATE, true),
                    'rows'        => [],
                    'order'       => [],
                ];
            }

            $groups[$label]['rows'][] = $this->row($product);

            $own = json_decode((string) get_post_meta($id, Meta::COLUMNS, true), true);
            if (is_array($own)) {
                foreach ($own as $col) {
                    if (! is_string($col) || '' === $col) {
                        continue;
                    }
                    if (! in_array($col, $groups[$label]['order'], true)) {
                        $groups[$label]['order'][] = $col;
                    }
                }
            }
        }

        return $groups;
    }

    /**
     * @return array<string,string>
     */
    private function row(\WC_Product $product): array
    {
        $row = [];
        $id  = $product->get_id();

        if ('yes' === get_post_meta($id, Meta::HAS_TITLE, true)) {
            $title = $product->get_name();
            if ('' !== $title) {
                $row['عنوان کالا'] = $title;
            }
        }

        foreach ($product->get_attributes() as $attr) {
            if (! $attr instanceof \WC_Product_Attribute) {
                continue;
            }
            $name = wc_attribute_label($attr->get_name());
            if ('' === $name || 'عنوان کالا' === $name) {
                continue;
            }
            $parts = [];
            foreach ($attr->get_options() as $v) {
                if ($attr->is_taxonomy()) {
                    $term = get_term((int) $v, $attr->get_name());
                    if ($term instanceof \WP_Term) {
                        $parts[] = $term->name;
                    }
                } else {
                    $parts[] = (string) $v;
                }
            }
            $row[$name] = implode('، ', $parts);
        }

        if ('yes' === get_post_meta($id, Meta::CONTACT_PRICE, true)) {
            $row['قیمت (تومان)'] = 'تماس بگیرید';
        } else {
            $regular = (string) $product->get_regular_price();
            $row['قیمت (تومان)'] = '' !== $regular ? number_format_i18n((int) $regular) : '';
        }

        $change = (string) get_post_meta($id, Meta::PRICE_CHANGE, true);
        if ('' !== $change) {
            $row['نوسان قیمت'] = $change;
        }

        return $row;
    }

    /**
     * @param array{last_update:string,rows:array<int,array<string,string>>,order:array<int,string>} $group
     * @return array<int,string>
     */
    private function columns(array $group): array
    {
        $order = $group['order'];

        foreach ($group['rows'] as $row) {
            foreach (array_keys($row) as $key) {
                if (! in_array($key, $order, true)) {
                    $order[] = $key;
                }
            }
        }

        return $order;
    }
}
