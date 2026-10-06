<?php

namespace Navard\Parser;

use Navard\Api\Client;
use Navard\Api\Endpoints;
use Navard\Config;

if (! defined('ABSPATH')) {
    exit;
}

final class ListParser
{

    public function load(): array
    {
        $cached = get_transient(Endpoints::list_key());
        if (is_array($cached)) {
            return ['ok' => true, 'products' => $cached, 'error' => ''];
        }

        $res = (new Client())->list();
        if (! $res->ok) {
            return ['ok' => false, 'products' => [], 'error' => $res->error];
        }

        $products = $this->flatten($res->data);
        set_transient(Endpoints::list_key(), $products, Config::TTL_LIST);

        return ['ok' => true, 'products' => $products, 'error' => ''];
    }

    private function flatten(array $data): array
    {
        $out     = [];
        $parents = isset($data['parents']) && is_array($data['parents']) ? $data['parents'] : [];

        foreach ($parents as $parent) {
            if (! is_array($parent)) {
                continue;
            }
            $p_slug = (string) ($parent['slug'] ?? '');
            $p_name = (string) ($parent['name'] ?? '');
            $cats   = isset($parent['categories']) && is_array($parent['categories']) ? $parent['categories'] : [];

            foreach ($cats as $cat) {
                if (! is_array($cat)) {
                    continue;
                }
                $c_slug = (string) ($cat['slug'] ?? '');
                $c_name = (string) ($cat['name'] ?? '');
                $groups = isset($cat['groups']) && is_array($cat['groups']) ? $cat['groups'] : [];

                foreach ($groups as $group) {
                    if (! is_array($group)) {
                        continue;
                    }
                    $g_name   = (string) ($group['name'] ?? '');
                    $last_up  = (string) ($group['last_update'] ?? '');
                    $last_tim = (string) ($group['last_update_time'] ?? '');
                    $prods    = isset($group['products']) && is_array($group['products']) ? $group['products'] : [];

                    foreach ($prods as $p) {
                        if (! is_array($p) || empty($p['id'])) {
                            continue;
                        }
                        $out[] = [
                            'id'               => (string) $p['id'],
                            'name'             => (string) ($p['name'] ?? ''),
                            'parent_slug'      => $p_slug,
                            'parent_name'      => $p_name,
                            'category_slug'    => $c_slug,
                            'category_name'    => $c_name,
                            'factory'          => '',
                            'tag'              => $g_name,
                            'last_update'      => $last_up,
                            'last_update_time' => $last_tim,
                        ];
                    }
                }
            }
        }

        return $out;
    }
}
