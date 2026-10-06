<?php

namespace Navard\Api;

use Navard\Config;
use Navard\Log\Logger;

if (! defined('ABSPATH')) {
    exit;
}

final class Client
{

    /**
     * Slug that intentionally does not exist on the API.
     * Used by ping() to test the key without pulling real data.
     */
    private const PING_SLUG = 'navard__ping__nonexistent';

    private string $base;
    private string $key;

    public function __construct(?string $base = null, ?string $key = null)
    {
        $this->base = $base ?: Config::endpoint();
        $this->key  = $key  ?: (string) Config::get('api_key', '');
    }

    public function list(): Response
    {
        return $this->get('/list');
    }

    public function category(string $slug): Response
    {
        $slug = sanitize_text_field($slug);
        return $this->get('/' . rawurlencode($slug));
    }

    /**
     * Tests the API key.
     *
     * Calls a category slug that does not exist.
     * The API distinguishes two cases:
     *   - Invalid key    → {"success":false,"error":"Invalid API key"}
     *   - Valid key      → {"success":false,"error":"Category not found"}
     *
     * If the key is valid, we treat ping as successful.
     */
    public function ping(): Response
    {
        $url = $this->base . '/' . rawurlencode(self::PING_SLUG);
        if ('' !== $this->key) {
            $url = add_query_arg('auth', $this->key, $url);
        }

        Logger::debug('PING ' . preg_replace('/auth=[^&]+/', 'auth=***', $url));

        $res = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($res)) {
            Logger::error('ping transport: ' . $res->get_error_message());
            return Response::failed($res->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);

        // Some APIs return 4xx for "category not found" — we must still read body.
        $json = json_decode($body, true);
        if (! is_array($json)) {
            Logger::error("ping HTTP {$code} invalid json");
            return Response::failed("HTTP {$code}");
        }

        $err = isset($json['error']) ? (string) $json['error'] : '';
        $err_l = strtolower($err);

        // Key is valid: the server knows the key but the category does not exist.
        if (false !== strpos($err_l, 'category not found')) {
            return Response::ok(['message' => 'کلید معتبر است.']);
        }

        // Key is invalid.
        if (false !== strpos($err_l, 'invalid api key')) {
            return Response::failed('کلید نامعتبر است.');
        }

        // Unexpected response shape.
        Logger::error("ping unexpected: {$err}");
        return Response::failed($err !== '' ? $err : "HTTP {$code}");
    }

    private function get(string $path): Response
    {
        $url = $this->base . $path;
        if ('' !== $this->key) {
            $url = add_query_arg('auth', $this->key, $url);
        }

        Logger::debug('GET ' . preg_replace('/auth=[^&]+/', 'auth=***', $url));

        $res = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($res)) {
            Logger::error('API transport: ' . $res->get_error_message());
            return Response::failed($res->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);

        $json = json_decode($body, true);
        if (! is_array($json)) {
            Logger::error("API HTTP {$code} invalid json");
            return Response::failed("HTTP {$code}");
        }

        if (empty($json['success']) || ! isset($json['data'])) {
            $err = isset($json['error']) ? (string) $json['error'] : 'api returned unsuccessful payload';
            Logger::error('API error: ' . $err);
            return Response::failed($err);
        }

        return Response::ok($json['data']);
    }
}
