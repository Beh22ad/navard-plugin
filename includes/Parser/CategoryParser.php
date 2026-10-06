<?php

namespace Navard\Parser;

use Navard\Api\Client;
use Navard\Api\Endpoints;
use Navard\Config;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Fetches and parses a single category response (e.g. /industrial-wire--fence).
 * Cached for 30 minutes.
 *
 * Output:
 *   [
 *     'ok'   => true,
 *     'data' => [
 *        'parent_slug' => '...',
 *        'category_slug' => '...',
 *        'category_name' => '...',
 *        'groups' => [
 *           [ 'factory' => '...', 'tag' => '...', 'last_update' => '...',
 *             'last_update_time' => '...', 'products' => [ ...full product dict... ] ],
 *        ],
 *     ],
 *     'error' => '',
 *   ]
 */
final class CategoryParser
{

    public function load(string $slug): array
    {
        $key    = Endpoints::category_key($slug);
        $cached = get_transient($key);
        if (is_array($cached)) {
            return ['ok' => true, 'data' => $cached, 'error' => ''];
        }

        $res = (new Client())->category($slug);
        if (! $res->ok) {
            return ['ok' => false, 'data' => [], 'error' => $res->error];
        }

        $parsed = $this->parse($slug, $res->data);
        set_transient($key, $parsed, Config::TTL_GROUP);

        return ['ok' => true, 'data' => $parsed, 'error' => ''];
    }

    public function forget(string $slug): void
    {
        Endpoints::forget_category($slug);
    }

    private function parse(string $slug, array $data): array
    {
        $parent_slug = (string) ($data['parent-category-slug'] ?? '');
        $cat_slug    = (string) ($data['category-slug'] ?? $slug);
        $cat_name    = (string) ($data['category'] ?? '');

        $groups = [];
        $raw_groups = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];

        foreach ($raw_groups as $g) {
            if (! is_array($g)) {
                continue;
            }
            $factory = (string) ($g['factory'] ?? '');
            $tag     = (string) ($g['tag'] ?? '');
            $last    = (string) ($g['last_update'] ?? '');
            $lastt   = (string) ($g['last_update_time'] ?? '');
            $prods   = isset($g['products']) && is_array($g['products']) ? $g['products'] : [];

            $groups[] = [
                'factory'          => $factory,
                'tag'              => $tag,
                'last_update'      => $last,
                'last_update_time' => $lastt,
                'products'         => $prods,
            ];
        }

        return [
            'parent_slug'   => $parent_slug,
            'category_slug' => $cat_slug,
            'category_name' => $cat_name,
            'groups'        => $groups,
        ];
    }
}
