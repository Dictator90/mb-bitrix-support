<?php

declare(strict_types=1);

require dirname(__DIR__) . '/scripts/dev/bitrix-app-minimal.php';
require dirname(__DIR__) . '/scripts/dev/bitrix-db-minimal.php';
require dirname(__DIR__) . '/scripts/dev/bitrix-phpstan-stubs.php';
require dirname(__DIR__) . '/vendor/autoload.php';

if (! function_exists('module')) {
    function module(string $id): object
    {
        return new class {
            public function getLang(string $code, ?array $replaces = null, ?string $fallback = null, ?string $lang = null): string
            {
                return $fallback ?? $code;
            }
        };
    }
}
