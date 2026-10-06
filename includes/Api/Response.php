<?php

namespace Navard\Api;

if (! defined('ABSPATH')) {
    exit;
}

final class Response
{

    public function __construct(
        public bool $ok,
        public array $data = [],
        public string $error = ''
    ) {}

    public static function ok(array $data): self
    {
        return new self(true, $data);
    }

    public static function failed(string $error): self
    {
        return new self(false, [], $error);
    }
}
