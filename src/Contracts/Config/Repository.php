<?php

declare(strict_types=1);

namespace MB\Bitrix\Contracts\Config;

/**
 * Read/write configuration values using dot notation (e.g. app.name).
 */
interface Repository
{
    public function has(string $key): bool;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;
}
