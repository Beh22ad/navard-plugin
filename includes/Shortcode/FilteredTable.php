<?php

namespace Navard\Shortcode;

use Navard\Product\Meta;
use Navard\Support\PersianDate;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Intercepts URLs like:
 *   /product-category/rebar/12/
 *   /product-category/rebar/size-12/
 *   /product-category/rebar--ajdar/12/
 *   /product-category/beam-h/20/
 *   /product-category/profile/20x40/    (matched as "2040")
 *   /product-category/angel/2/
 *   /product-category/uchannel/12/
 *
 * Debug: append ?navard_debug=1 to any of these URLs to dump JSON diagnostics.
 */
final class FilteredTable
{
    private const FAMILY_ATTR = [
        'rebar'   => 'سایز mm',
        'beam-h'  => 'سایز cm',
        'profile' => 'ابعاد mm',
        'angel'   => 'ضخامت mm',
    ];

    private const FAMILY_SLUG = [
        'rebar'    => 'rebar',
        'beam-h'   => 'beam-h',
        'profile'  => 'profile',
        'angel'    => 'angel',
        'uchannel' => 'uchannel',
    ];

    private const TRANSIENT_PREFIX = 'navard_ft_ids_';
    private const CACHE_TTL        = 30 * DAY_IN_SECONDS;

    public function hooks(): void
    {
        add_action('template_redirect', [$this, 'maybe_render'], 0);
    }

    public function maybe_render(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if (\Navard\Support\Helpers::is_navard_theme()) {
            return;
        }

        if ('no' === (string) \Navard\Config::get('enable_size_pages', 'yes')) {
            return;
        }

        /* ================= DEBUG: ?navard_debug=1 ================= */
        if (isset($_GET['navard_debug'])) {
            $this->debug_dump();
            exit;
        }
        /* ================= /DEBUG ================= */

        $ctx = $this->parse_request();
        if (null === $ctx) {
            return;
        }

        $ids = $this->resolve_product_ids($ctx);
        if (empty($ids)) {
            return; // let WP emit its normal 404
        }

        $html = $this->render_html($ctx, $ids);

        status_header(200);
        nocache_headers();

        $this->output_archive($ctx, $html);
        exit;
    }

    /* ------------------------------------------------------------------ */
    /* DEBUG                                                               */
    /* ------------------------------------------------------------------ */

    private function debug_dump(): void
    {
        $req  = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = trim((string) wp_parse_url($req, PHP_URL_PATH), '/');
        $seg  = array_values(array_filter(explode('/', $path)));

        $out = [
            'REQUEST_URI' => $req,
            'segments'    => $seg,
            'seg_count'   => count($seg),
            'first_seg'   => $seg[0] ?? '',
            'last_seg'    => end($seg),
            'parse_value' => $this->parse_value((string) end($seg)),
        ];

        if (count($seg) >= 3 && 'product-category' === ($seg[0] ?? '')) {
            $tslug = implode('/', array_slice($seg, 1, -1));
            $out['term_slug_guess'] = $tslug;

            $term = get_term_by('slug', $tslug, 'product_cat');
            $out['term_found'] = $term instanceof \WP_Term;

            if ($term instanceof \WP_Term) {
                $out['term'] = [
                    'term_id' => $term->term_id,
                    'name'    => $term->name,
                    'slug'    => $term->slug,
                    'parent'  => $term->parent,
                ];
                $out['ancestors'] = get_ancestors($term->term_id, 'product_cat', 'taxonomy');
                $out['family']    = $this->resolve_family($term);

                if ('uchannel' === $out['family']) {
                    $ctx = [
                        'family' => 'uchannel',
                        'term'   => $term,
                        'value'  => (string) (($this->parse_value((string) end($seg)))['attr_value'] ?? ''),
                    ];
                    $q = new \WP_Query([
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'fields'         => 'ids',
                        'no_found_rows'  => true,
                        'tax_query'      => [
                            [
                                'taxonomy'         => 'product_cat',
                                'field'            => 'term_id',
                                'terms'            => [$term->term_id],
                                'include_children' => true,
                            ],
                        ],
                    ]);
                    $in_cat = array_map('intval', $q->posts);
                    $out['products_in_cat'] = count($in_cat);

                    $matched = [];
                    foreach ($in_cat as $pid) {
                        $name = $this->normalize_for_match((string) get_the_title($pid));
                        $pat  = '/عرض\s*' . preg_quote($ctx['value'], '/') . '(?!\d)/u';
                        if (preg_match($pat, $name)) {
                            $matched[] = [
                                'id'      => $pid,
                                'title'   => get_the_title($pid),
                                'factory' => (string) get_post_meta($pid, Meta::FACTORY_META, true),
                            ];
                        }
                    }
                    $out['matched_by_name'] = $matched;

                    $sample = [];
                    foreach (array_slice($in_cat, 0, 10) as $pid) {
                        $sample[] = [
                            'id'    => $pid,
                            'title' => get_the_title($pid),
                            'norm'  => $this->normalize_for_match((string) get_the_title($pid)),
                        ];
                    }
                    $out['sample_titles'] = $sample;

                    $inspect = [];
                    foreach (array_slice($in_cat, 0, 3) as $pid) {
                        $p = wc_get_product((int) $pid);
                        if (! $p instanceof \WC_Product) {
                            continue;
                        }
                        $attrs = [];
                        foreach ($p->get_attributes() as $a) {
                            if (! $a instanceof \WC_Product_Attribute) {
                                continue;
                            }
                            $parts = [];
                            foreach ($a->get_options() as $v) {
                                if ($a->is_taxonomy()) {
                                    $t = get_term((int) $v, $a->get_name());
                                    if ($t instanceof \WP_Term) {
                                        $parts[] = $t->name;
                                    }
                                } else {
                                    $parts[] = (string) $v;
                                }
                            }
                            $attrs[] = [
                                'name'    => $a->get_name(),
                                'label'   => wc_attribute_label($a->get_name()),
                                'options' => $parts,
                            ];
                        }
                        $inspect[] = [
                            'id'         => $p->get_id(),
                            'title'      => $p->get_name(),
                            'factory'    => (string) get_post_meta($p->get_id(), Meta::FACTORY_META, true),
                            'attributes' => $attrs,
                        ];
                    }
                    $out['family_sample'] = $inspect;
                }

                $inspect_families = ['rebar', 'beam-h', 'profile', 'angel'];
                if (in_array($out['family'], $inspect_families, true)) {
                    $q = new \WP_Query([
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'posts_per_page' => 3,
                        'fields'         => 'ids',
                        'no_found_rows'  => true,
                        'tax_query'      => [
                            [
                                'taxonomy'         => 'product_cat',
                                'field'            => 'term_id',
                                'terms'            => [$term->term_id],
                                'include_children' => true,
                            ],
                        ],
                    ]);
                    $sample = [];
                    foreach ($q->posts as $pid) {
                        $p = wc_get_product((int) $pid);
                        if (! $p instanceof \WC_Product) {
                            continue;
                        }
                        $attrs = [];
                        foreach ($p->get_attributes() as $a) {
                            if (! $a instanceof \WC_Product_Attribute) {
                                continue;
                            }
                            $parts = [];
                            foreach ($a->get_options() as $v) {
                                if ($a->is_taxonomy()) {
                                    $t = get_term((int) $v, $a->get_name());
                                    if ($t instanceof \WP_Term) {
                                        $parts[] = $t->name;
                                    }
                                } else {
                                    $parts[] = (string) $v;
                                }
                            }
                            $attrs[] = [
                                'name'    => $a->get_name(),
                                'label'   => wc_attribute_label($a->get_name()),
                                'options' => $parts,
                            ];
                        }
                        $sample[] = [
                            'id'         => $p->get_id(),
                            'title'      => $p->get_name(),
                            'factory'    => (string) get_post_meta($p->get_id(), Meta::FACTORY_META, true),
                            'attributes' => $attrs,
                        ];
                    }
                    $out['family_sample'] = $sample;
                }
            } else {
                $like = get_terms([
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => false,
                    'search'     => end(explode('/', $tslug)),
                ]);
                $out['similar_terms'] = is_wp_error($like) ? [] : array_map(static function ($t) {
                    return ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'parent' => $t->parent];
                }, $like);
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /* ------------------------------------------------------------------ */
    /* URL parsing                                                         */
    /* ------------------------------------------------------------------ */

    private function parse_request(): ?array
    {
        $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = trim($path, '/');
        if ('' === $path) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn($s) => '' !== $s));

        if (count($segments) < 3 || 'product-category' !== $segments[0]) {
            return null;
        }

        $value     = (string) end($segments);
        $term_slug = implode('/', array_slice($segments, 1, -1));
        if ('' === $term_slug) {
            return null;
        }

        $parsed = $this->parse_value($value);
        if (null === $parsed) {
            return null;
        }

        $term = get_term_by('slug', $term_slug, 'product_cat');
        if (! $term instanceof \WP_Term) {
            return null;
        }

        $family = $this->resolve_family($term);
        if (null === $family) {
            return null;
        }

        if ('uchannel' !== $family && '' === $parsed['attr_value']) {
            return null;
        }

        return [
            'family' => $family,
            'term'   => $term,
            'value'  => $parsed['attr_value'],
            'raw'    => $parsed['raw'],
        ];
    }

    private function parse_value(string $seg): ?array
    {
        $s = strtolower(trim($seg));
        if (str_starts_with($s, 'size-')) {
            $s = substr($s, 5);
        }
        if ('' === $s) {
            return null;
        }

        if (preg_match('/^\d+$/', $s)) {
            return ['attr_value' => $s, 'raw' => $seg];
        }

        if (preg_match('/^(\d+)x(\d+)$/', $s, $m)) {
            return ['attr_value' => $m[1] . $m[2], 'raw' => $seg];
        }

        return null;
    }

    private function resolve_family(\WP_Term $term): ?string
    {
        $families = self::FAMILY_SLUG;

        if (isset($families[$term->slug])) {
            return $families[$term->slug];
        }

        $ancestors = get_ancestors($term->term_id, 'product_cat', 'taxonomy');
        if (empty($ancestors)) {
            return null;
        }
        foreach ($ancestors as $aid) {
            $a = get_term($aid, 'product_cat');
            if ($a instanceof \WP_Term && isset($families[$a->slug])) {
                return $families[$a->slug];
            }
        }
        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Product lookup                                                      */
    /* ------------------------------------------------------------------ */

    private function resolve_product_ids(array $ctx): array
    {
        $cache_key = self::TRANSIENT_PREFIX . md5($ctx['family'] . '|' . $ctx['term']->term_id . '|' . $ctx['value']);
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return array_map('intval', $cached);
        }

        $ids = ('uchannel' === $ctx['family'])
            ? $this->query_uchannel($ctx)
            : $this->query_by_attribute($ctx);

        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (! empty($ids)) {
            set_transient($cache_key, $ids, self::CACHE_TTL);
        }

        return $ids;
    }

    private function query_by_attribute(array $ctx): array
    {
        $attr_label = self::FAMILY_ATTR[$ctx['family']] ?? '';
        if ('' === $attr_label) {
            return [];
        }

        $taxonomy = wc_attribute_taxonomy_name(\Navard\Support\Helpers::prefix_slug($attr_label));

        $q = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'tax_query'      => [
                'relation' => 'AND',
                [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => [$ctx['term']->term_id],
                    'include_children' => true,
                ],
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => [$ctx['value']],
                ],
            ],
        ]);

        return array_map('intval', $q->posts);
    }

    private function query_uchannel(array $ctx): array
    {
        $q = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => [$ctx['term']->term_id],
                    'include_children' => true,
                ],
            ],
        ]);

        $value   = (string) $ctx['value'];
        $pattern = '/عرض\s*' . preg_quote($value, '/') . '(?!\d)/u';
        $out     = [];

        foreach ($q->posts as $pid) {
            $pid  = (int) $pid;
            $name = $this->normalize_for_match((string) get_the_title($pid));
            if (preg_match($pattern, $name)) {
                $out[] = $pid;
            }
        }
        return $out;
    }

    private function normalize_for_match(string $s): string
    {
        $s = PersianDate::to_ascii_digits($s);
        $s = str_replace(["\xC2\xA0", "\xE2\x80\x8C", "\xE2\x80\x8F", "\xE2\x80\x8E"], ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return mb_strtolower(trim($s));
    }

    /* ------------------------------------------------------------------ */
    /* Rendering                                                           */
    /* ------------------------------------------------------------------ */

    private function render_html(array $ctx, array $ids): string
    {
        $family = $ctx['family'];
        $term   = $ctx['term'];
        $value  = $ctx['value'];

        $tables = [];

        if ('uchannel' === $family) {
            $light = [];
            $heavy = [];
            foreach ($ids as $pid) {
                $name = $this->normalize_for_match((string) get_the_title($pid));
                if (false !== mb_strpos($name, 'سنگین')) {
                    $heavy[] = $pid;
                } elseif (false !== mb_strpos($name, 'سبک')) {
                    $light[] = $pid;
                }
            }
            if (! empty($light)) {
                $tables[] = [
                    'title' => 'ناودانی سبک عرض ' . $value,
                    'ids'   => $light,
                    'kind'  => 'uchannel',
                ];
            }
            if (! empty($heavy)) {
                $tables[] = [
                    'title' => 'ناودانی سنگین عرض ' . $value,
                    'ids'   => $heavy,
                    'kind'  => 'uchannel',
                ];
            }
        } else {
            $tables[] = [
                'title' => $term->name . ' - ' . $this->value_label($family, $value, (string) ($ctx['raw'] ?? '')),
                'ids'   => $ids,
                'kind'  => $family,
            ];
        }

        ob_start();
        echo '<div class="navard-shortcode" dir="rtl">';
        echo '<h3 class="navard-sc-title">' . esc_html($term->name) . '</h3>';

        foreach ($tables as $t) {
            echo $this->render_table_block($t['title'], $t['ids'], $t['kind'], $value);
        }

        echo '</div>';

        CategoryTable::enqueue_assets_once();
        add_action('wp_footer', [CategoryTable::class, 'print_modal'], 5);

        return (string) ob_get_clean();
    }

    private function value_label(string $family, string $value, string $raw = ''): string
    {
        $show = ('' !== $raw) ? $raw : $value;

        if (str_starts_with(strtolower($show), 'size-')) {
            $show = substr($show, 5);
        }

        switch ($family) {
            case 'rebar':
            case 'beam-h':
                return 'سایز ' . $show;
            case 'angel':
                return 'ضخامت ' . $show;
            case 'profile':
                return 'ابعاد ' . $show;
        }
        return $show;
    }

    private function render_table_block(string $title, array $ids, string $kind, string $size_value = '', bool $dedupe_factory = true): string
    {
        $products  = [];
        $seen_fact = [];
        foreach ($ids as $pid) {
            $p = wc_get_product((int) $pid);
            if (! $p instanceof \WC_Product) {
                continue;
            }

            if ($dedupe_factory) {
                $factory = trim((string) get_post_meta($p->get_id(), Meta::FACTORY_META, true));

                if ('' === $factory && 'uchannel' === $kind) {
                    $factory = $this->factory_from_title($p->get_name());
                }

                if ('' === $factory) {
                    continue;
                }

                $key = mb_strtolower($factory);
                if (isset($seen_fact[$key])) {
                    continue;
                }
                $seen_fact[$key] = true;
            }

            $products[] = $p;
        }
        if (empty($products)) {
            return '';
        }

        $last_update = (string) get_post_meta($products[0]->get_id(), Meta::LAST_UPDATE, true);
        $columns     = $this->build_columns($kind, $products);

        ob_start();
        echo '<div class="navard-sc-group">';
        echo '<div class="navard-sc-group-head">';
        echo '<h4 class="navard-sc-group-title">' . esc_html($title) . '</h4>';
        if ('' !== $last_update) {
            echo '<span class="navard-sc-updated">'
                . '<svg class="navard-sc-cal" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">'
                . '<path fill="currentColor" d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm12 8v9H5v-9h14zM5 8V6h14v2H5z"/>'
                . '</svg>'
                . 'آخرین بروزرسانی: ' . esc_html($last_update)
                . '</span>';
        }
        echo '</div>';

        echo '<div class="navard-sc-scroll">';
        echo '<table class="navard-sc-table">';
        echo '<thead><tr>';
        foreach ($columns as $col) {
            echo '<th>' . esc_html($col) . '</th>';
        }
        echo '<th class="navard-sc-th-chart">نمودار قیمت</th>';
        echo '</tr></thead><tbody>';

        foreach ($products as $p) {
            $row = $this->build_row_with_fallback($p, $kind, $columns, $size_value);
            echo '<tr>';
            foreach ($columns as $col) {
                $val = isset($row[$col]) ? (string) $row[$col] : '';
                $cls = ('قیمت (تومان)' === $col) ? ' class="navard-sc-td-price"' : '';
                echo '<td' . $cls . '>' . esc_html($val) . '</td>';
            }
            echo '<td class="navard-sc-td-chart">' . CategoryTable::chart_placeholder_public($p->get_id()) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    private function factory_from_title(string $title): string
    {
        $t = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $parts = preg_split('/\s*[\x{2013}\x{2014}\-]\s*/u', $t);
        if (! is_array($parts)) {
            return '';
        }

        $parts = array_values(array_filter(array_map('trim', $parts), static fn($s) => '' !== $s));
        if (empty($parts)) {
            return '';
        }

        while (! empty($parts) && preg_match('/^\d+$/', (string) end($parts))) {
            array_pop($parts);
        }

        if (empty($parts)) {
            return '';
        }

        return (string) end($parts);
    }

    private function build_row_with_fallback(\WC_Product $p, string $kind, array $columns, string $size_value = ''): array
    {
        $row = $this->build_row($p, $kind, $columns, $size_value);

        if ('uchannel' === $kind) {
            if ('' === ($row['کارخانه'] ?? '')) {
                $row['کارخانه'] = $this->factory_from_title($p->get_name());
            }
        }

        return $row;
    }

    private function build_columns(string $kind, array $products): array
    {
        switch ($kind) {
            case 'rebar':
                return [
                    'سایز (mm)',
                    'استاندارد',
                    'محل تحویل',
                    'کارخانه',
                    'قیمت (تومان)',
                    'نوسان قیمت',
                ];

            case 'beam-h':
                return [
                    'سایز (cm)',
                    'کارخانه',
                    'محل تحویل',
                    'واحد',
                    'وزن تئوری (kg)',
                    'قیمت (تومان)',
                    'نوسان قیمت',
                ];

            case 'profile':
                return [
                    'عنوان کالا',
                    'کارخانه',
                    'ضخامت (mm)',
                    'ابعاد (mm)',
                    'محل تحویل',
                    'قیمت (تومان)',
                ];

            case 'angel':
                return [
                    'سایز (mm)',
                    'ضخامت (mm)',
                    'برند',
                    'محل تحویل',
                    'وزن واحد (Kg)',
                    'قیمت (تومان)',
                    'نوسان قیمت',
                ];

            case 'uchannel':
                return [
                    'سایز (cm)',
                    'طول (m)',
                    'کارخانه',
                    'محل تحویل',
                    'واحد',
                    'قیمت (تومان)',
                    'نوسان قیمت',
                ];
        }

        return ['قیمت (تومان)'];
    }

    private function build_row(\WC_Product $p, string $kind, array $columns, string $size_value = ''): array
    {
        $id      = $p->get_id();
        $row     = [];
        $factory = (string) get_post_meta($id, Meta::FACTORY_META, true);

        $price = 'yes' === get_post_meta($id, Meta::CONTACT_PRICE, true)
            ? 'تماس بگیرید'
            : ('' !== (string) $p->get_regular_price() ? number_format_i18n((int) $p->get_regular_price()) : '');

        $change = (string) get_post_meta($id, Meta::PRICE_CHANGE, true);

        switch ($kind) {
            case 'rebar':
                $row['سایز (mm)']    = $this->first_attr($p, ['سایز mm', 'سایز (mm)', 'سایز']);
                $row['استاندارد']    = $this->first_attr($p, ['استاندارد', 'استاندارد (mm)']);
                $row['محل تحویل']    = $this->first_attr($p, ['محل تحویل']);
                $row['کارخانه']      = $factory;
                $row['قیمت (تومان)'] = $price;
                $row['نوسان قیمت']   = $change;
                break;

            case 'beam-h':
                $row['سایز (cm)']      = $this->first_attr($p, ['سایز cm', 'سایز (cm)', 'سایز']);
                $row['کارخانه']        = $factory;
                $row['محل تحویل']      = $this->first_attr($p, ['محل تحویل']);
                $row['واحد']           = $this->matched_unit_value($p);
                $row['وزن تئوری (kg)'] = $this->first_attr($p, [
                    'وزن تئوری kg',
                    'وزن تئوری (kg)',
                    'وزن تئوری',
                    'وزن تئوري',
                    'وزن (kg)',
                    'وزن',
                ]);
                $row['قیمت (تومان)']   = $price;
                $row['نوسان قیمت']     = $change;
                break;

            case 'profile':
                $row['عنوان کالا']   = $p->get_name();
                $row['کارخانه']      = $factory;
                $row['ضخامت (mm)']   = $this->first_attr($p, ['ضخامت mm', 'ضخامت (mm)', 'ضخامت']);
                $row['ابعاد (mm)']   = $this->first_attr($p, ['ابعاد mm', 'ابعاد (mm)', 'ابعاد']);
                $row['محل تحویل']    = $this->first_attr($p, ['محل تحویل']);
                $row['قیمت (تومان)'] = $price;
                break;

            case 'angel':
                $row['سایز (mm)']     = $this->first_attr($p, ['سایز mm', 'سایز (mm)', 'سایز']);
                $row['ضخامت (mm)']    = $this->first_attr($p, ['ضخامت mm', 'ضخامت (mm)', 'ضخامت']);
                $row['برند']          = $factory;
                $row['محل تحویل']     = $this->first_attr($p, ['محل تحویل']);
                $row['وزن واحد (Kg)'] = $this->first_attr($p, [
                    'وزن واحد kg',
                    'وزن واحد (kg)',
                    'وزن واحد',
                    'وزن kg',
                    'وزن (kg)',
                    'وزن تئوری kg',
                    'وزن تئوری (kg)',
                    'وزن تئوری',
                    'وزن تئوري',
                    'وزن',
                ]);
                $row['قیمت (تومان)']  = $price;
                $row['نوسان قیمت']    = $change;
                break;

            case 'uchannel':
                $row['سایز (cm)']    = $size_value;
                $row['طول (m)']      = $this->first_attr($p, ['طول m', 'طول (m)', 'طول']);
                $row['کارخانه']      = $factory;
                $row['محل تحویل']    = $this->first_attr($p, ['محل تحویل']);
                $row['واحد']         = $this->matched_unit_value($p);
                $row['قیمت (تومان)'] = $price;
                $row['نوسان قیمت']   = $change;
                break;
        }

        return $row;
    }

    private function matched_attr_value(\WC_Product $p, string $attr_label): string
    {
        $tax = wc_attribute_taxonomy_name(\Navard\Support\Helpers::prefix_slug($attr_label));
        foreach ($p->get_attributes() as $attr) {
            if (! $attr instanceof \WC_Product_Attribute) {
                continue;
            }
            if ($attr->get_name() !== $tax) {
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
            return implode('، ', $parts);
        }
        return '';
    }

    private function matched_named_attr(\WC_Product $p, string $attr_label): string
    {
        return $this->matched_attr_value($p, $attr_label);
    }

    private function first_attr(\WC_Product $p, array $labels): string
    {
        foreach ($labels as $label) {
            $val = $this->matched_attr_value($p, $label);
            if ('' !== $val) {
                return $val;
            }
        }
        return '';
    }

    private function matched_unit_value(\WC_Product $p): string
    {
        $labels = ['واحد', 'واحد kg', 'واحد برگ', 'واحد رول', 'واحد عدد'];

        foreach ($labels as $label) {
            $val = $this->matched_attr_value($p, $label);
            if ('' !== $val) {
                return $val;
            }
        }
        return '';
    }

    /* ------------------------------------------------------------------ */
    /* Output                                                              */
    /* ------------------------------------------------------------------ */

    private function output_archive(array $ctx, string $html): void
    {
        global $wp_query;

        $wp_query->is_404            = false;
        $wp_query->is_tax            = true;
        $wp_query->is_archive        = true;
        $wp_query->queried_object    = $ctx['term'];
        $wp_query->queried_object_id = $ctx['term']->term_id;

        add_filter('pre_get_document_title', static function () use ($ctx) {
            return $ctx['term']->name . ' - ' . get_bloginfo('name');
        });

        $canvas         = ABSPATH . WPINC . '/template-canvas.php';
        $is_block_theme = function_exists('wp_is_block_theme') && wp_is_block_theme();
        $has_header     = (locate_template(['header.php']) !== '');

        if ($is_block_theme || ! $has_header) {
            global $_wp_current_template_content, $_wp_current_template_id;

            $_wp_current_template_id = get_stylesheet() . '//navard-filtered';

            $_wp_current_template_content =
                '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
                . '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->'
                . '<main class="wp-block-group">'
                . '<!-- wp:html -->' . $html . '<!-- /wp:html -->'
                . '</main>'
                . '<!-- /wp:group -->'
                . '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';

            require $canvas;
            return;
        }

        get_header();
        do_action('woocommerce_before_main_content');
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
        do_action('woocommerce_after_main_content');
        get_footer();
    }

    /* ------------------------------------------------------------------ */
    /* Public helpers used by TagFilteredTable and CategoryTable           */
    /* ------------------------------------------------------------------ */

    /**
     * Render a family table. Exposed so TagFilteredTable can reuse the layout
     * without deduplicating by factory.
     */
    public function render_family_table(string $family, \WP_Term $term, string $title, array $product_ids, bool $dedupe_factory = true): string
    {
        $html = '<div class="navard-shortcode" dir="rtl">';
        $html .= '<h3 class="navard-sc-title">' . esc_html($term->name) . '</h3>';
        $html .= $this->render_table_block($title, $product_ids, $family, '', $dedupe_factory);
        $html .= '</div>';

        CategoryTable::enqueue_assets_once();
        add_action('wp_footer', [CategoryTable::class, 'print_modal'], 5);

        return $html;
    }

    /** Used by CategoryTable to render a filtered table when the URL matches. */
    public function render_shortcode_if_match(): string
    {
        $ctx = $this->parse_request();
        if (null === $ctx) {
            return '';
        }
        $ids = $this->resolve_product_ids($ctx);
        if (empty($ids)) {
            return '';
        }
        return $this->render_family_table(
            $ctx['family'],
            $ctx['term'],
            $ctx['term']->name . ' - ' . $ctx['value'],
            $ids,
            false
        );
    }

    /** Public wrapper for the archive page output. */
    public function output_archive_public(\WP_Term $term, string $html): void
    {
        $this->output_archive(['term' => $term], $html);
    }
}
