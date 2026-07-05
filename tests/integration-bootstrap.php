<?php

declare(strict_types=1);

/**
 * Integration tests bootstrap — delegates to mb4it/bitrix-core-test.
 *
 * Full prolog: BITRIX_BOOTSTRAP_MODE=full or BITRIX_INTEGRATION_USE_PROLOG=1
 * Minimal D7 stubs only: BITRIX_BOOTSTRAP_MODE=minimal
 */

$projectRoot = dirname(__DIR__);

$autoload = $projectRoot . '/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "Run composer install in bitrix-support first.\n");
    exit(1);
}

require $autoload;

$coreBootstrap = $projectRoot . '/vendor/mb4it/bitrix-core-test/bootstrap_prolog.php';
if (! is_file($coreBootstrap)) {
    fwrite(STDERR, "mb4it/bitrix-core-test is not installed. Run: composer require --dev mb4it/bitrix-core-test\n");
    exit(1);
}

putenv('BITRIX_TEST_PROJECT_ROOT=' . $projectRoot);

$useProlog = getenv('BITRIX_INTEGRATION_USE_PROLOG');
if ($useProlog === '1' || strtolower((string) $useProlog) === 'true') {
    putenv('BITRIX_BOOTSTRAP_MODE=full');
}

require $coreBootstrap;

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
