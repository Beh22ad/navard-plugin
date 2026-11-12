<?php

namespace Navard\Shortcode;

use Navard\Product\Meta;

if (! defined('ABSPATH')) {
    exit;
}

final class CategoryTable
{

    private static bool $modal_printed = false;
    private static bool $assets_done   = false;

    public function hooks(): void
    {
        add_shortcode('navard', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('wp_footer', [$this, 'maybe_print_modal'], 5);
    }

    public function assets(): void
    {
        wp_register_style(
            'navard-shortcode',
            NAVARD_URL . 'assets/css/shortcode.css',
            [],
            NAVARD_VERSION
        );

        wp_register_script(
            'navard-chart',
            NAVARD_URL . 'assets/js/price-chart.js',
            [],
            NAVARD_VERSION,
            true
        );

        wp_localize_script('navard-chart', 'NavardChartCfg', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('navard_chart'),
            'i18n'  => [
                'empty'  => 'تاریخچه قیمت خالی است',
                'title'  => 'نمودار قیمت',
                'xaxis'  => 'تاریخ',
                'yaxis'  => 'تومان',
                'close'  => 'بستن',
                'error'  => 'خطا در دریافت اطلاعات',
            ],
        ]);
    }

    public function maybe_print_modal(): void
    {
        if (! self::$modal_printed) {
            return;
        }
        self::print_modal();
    }

    public static function print_modal(): void
    {
?>
        <div id="navard-chart-modal" class="navard-chart-modal" aria-hidden="true">
            <div class="navard-chart-modal-backdrop" data-navard-close="1"></div>
            <div class="navard-chart-modal-box" role="dialog" aria-modal="true">
                <div class="navard-chart-modal-head">
                    <span class="navard-chart-modal-title">نمودار قیمت</span>
                    <button type="button" class="navard-chart-modal-close" data-navard-close="1" aria-label="بستن">×</button>
                </div>
                <div class="navard-chart-modal-body" id="navard-chart-modal-body"></div>
            </div>
        </div>
<?php
        self::$modal_printed = true;
    }

    public static function enqueue_assets_once(): void
    {
        if (self::$assets_done) {
            return;
        }
        self::$assets_done = true;
        wp_enqueue_style('navard-shortcode');
        wp_enqueue_script('navard-chart');
        self::$modal_printed = true;
    }

    public static function chart_placeholder_public(int $product_id): string
    {
        return '<span class="navard-sc-chart" role="button" tabindex="0" aria-label="نمودار قیمت" data-product-id="' . $product_id . '">'
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

    public function render($atts): string
    {
        $atts = shortcode_atts(
            [
                'cat'    => '',
                'tag'    => '',
                'cat_id' => '',
                'tag_id' => '',
                'num'    => '',
            ],
            $atts,
            'navard'
        );

        $cat    = sanitize_text_field((string) $atts['cat']);
        $tag    = sanitize_text_field((string) $atts['tag']);
        $cat_id = (int) $atts['cat_id'];
        $tag_id = (int) $atts['tag_id'];
        $num    = (int) $atts['num'];

        // -----------------------------------------------------------------
        // 1) Auto-detect size-… / tg-… URLs first
        // -----------------------------------------------------------------
        if ('' === $cat && '' === $tag && $cat_id <= 0 && $tag_id <= 0) {
            $auto = $this->detect_filtered_request();
            if (null !== $auto) {
                return $auto;
            }
        }

        // -----------------------------------------------------------------
        // 2) Resolve the term
        // -----------------------------------------------------------------
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

        // Fallback to auto-detecting the current archive page
        if (! $term instanceof \WP_Term) {
            $detected = $this->detect_current_term();
            if ($detected) {
                $term     = $detected['term'];
                $taxonomy = $detected['taxonomy'];
            }
        }

        if (! $term instanceof \WP_Term || '' === $taxonomy) {
            return '';
        }

        // -----------------------------------------------------------------
        // 3) Query products and render as tables
        // -----------------------------------------------------------------
        $products = $this->query((int) $term->term_id, $taxonomy);
        if (empty($products)) {
            return '';
        }

        wp_enqueue_style('navard-shortcode');
        wp_enqueue_script('navard-chart');
        self::$modal_printed = true;
        self::$assets_done   = true;

        $groups = $this->group($products);

        $limited = false;
        if ($num > 0 && count($groups) > $num) {
            $groups  = array_slice($groups, 0, $num, true);
            $limited = true;
        }

        $view_all_url = '';
        if ($limited) {
            $link = get_term_link($term, $taxonomy);
            if (! is_wp_error($link)) {
                $view_all_url = $link;
            }
        }

        ob_start();
        echo '<div class="navard-shortcode" dir="rtl">';
        echo '<h3 class="navard-sc-title">' . esc_html($term->name) . '</h3>';

        foreach ($groups as $label => $group) {
            echo '<div class="navard-sc-group">';
            echo '<div class="navard-sc-group-head">';

            echo '<h4 class="navard-sc-group-title">' . ('' !== $label ? esc_html($label) : '&nbsp;') . '</h4>';

            if ('' !== $group['last_update']) {
                echo '<div class="navard-date-parent"><span class="navard-sc-updated">'
                    . '<svg class="navard-sc-cal" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">'
                    . '<path fill="currentColor" d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm12 8v9H5v-9h14zM5 8V6h14v2H5z"/>'
                    . '</svg>'
                    . 'آخرین بروزرسانی: ' . esc_html($group['last_update'])
                    . '</span></div>';
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
                $pid = (int) ($row['__pid'] ?? 0);

                echo '<tr>';
                foreach ($columns as $col) {
                    $val = isset($row[$col]) ? (string) $row[$col] : '';
                    $cls = ('قیمت (تومان)' === $col) ? ' class="navard-sc-td-price"' : '';
                    echo '<td' . $cls . '>' . esc_html($val) . '</td>';
                }
                echo '<td class="navard-sc-td-chart">' . self::chart_placeholder_public($pid) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
            echo '</div>';
        }

        if ('' !== $view_all_url) {
            echo '<div class="navard-sc-viewall">';
            echo '<a class="navard-sc-viewall-link" href="' . esc_url($view_all_url) . '">'
                . 'مشاهده لیست قیمت کامل'
                . '</a>';
            echo '</div>';
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    private function detect_filtered_request(): ?string
    {
        if (
            'no' === (string) \Navard\Config::get('enable_size_pages', 'yes')
            && 'no' === (string) \Navard\Config::get('enable_tag_pages', 'yes')
        ) {
            return null;
        }

        $req  = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = trim((string) wp_parse_url($req, PHP_URL_PATH), '/');
        if ('' === $path) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn($s) => '' !== $s));
        if (count($segments) < 3 || 'product-category' !== $segments[0]) {
            return null;
        }

        $last = strtolower((string) end($segments));

        if (str_starts_with($last, 'tg-') || str_starts_with($last, 'tag-')) {
            if ('no' === (string) \Navard\Config::get('enable_tag_pages', 'yes')) {
                return null;
            }
            $tagger = new TagFilteredTable();
            if (method_exists($tagger, 'render_shortcode_if_match')) {
                return (string) $tagger->render_shortcode_if_match();
            }
            return null;
        }

        if (str_starts_with($last, 'size-')) {
            if ('no' === (string) \Navard\Config::get('enable_size_pages', 'yes')) {
                return null;
            }
            $filter = new FilteredTable();
            if (method_exists($filter, 'render_shortcode_if_match')) {
                return (string) $filter->render_shortcode_if_match();
            }
            return null;
        }

        return null;
    }

    private function detect_current_term(): ?array
    {
        $obj = get_queried_object();
        if ($obj instanceof \WP_Term && in_array($obj->taxonomy, ['product_cat', 'product_tag'], true)) {
            return ['term' => $obj, 'taxonomy' => $obj->taxonomy];
        }
        return null;
    }

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

    private function row(\WC_Product $product): array
    {
        $row = [];
        $id  = $product->get_id();

        $row['__pid'] = (string) $id;

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

    private function columns(array $group): array
    {
        $order = $group['order'];

        foreach ($group['rows'] as $row) {
            foreach (array_keys($row) as $key) {
                if ('__pid' === $key) {
                    continue;
                }
                if (! in_array($key, $order, true)) {
                    $order[] = $key;
                }
            }
        }

        return $order;
    }
}
