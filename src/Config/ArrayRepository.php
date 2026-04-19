<?php

declare(strict_types=1);

namespace MB\Bitrix\Config;

use MB\Bitrix\Contracts\Config\Repository;
use MB\Support\Arr;

/**
 * In-memory config repository; optional load from a directory of PHP files (filename → top-level key).
 */
final class ArrayRepository implements Repository
{
    /** @var array<string, mixed> */
    private array $items = [];

    /**
     * @param array<string, mixed> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Merge configuration from each *.php file in $path (each file must return array).
     */
    public function loadFromDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $pattern = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php';
        foreach (glob($pattern) ?: [] as $file) {
            $key = basename($file, '.php');
            $data = require $file;
            if (is_array($data)) {
                $this->items[$key] = isset($this->items[$key]) && is_array($this->items[$key])
                    ? array_replace_recursive($this->items[$key], $data)
                    : $data;
            }
        }
    }

    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        Arr::set($this->items, $key, $value);
    }

    public function all(): array
    {
        return $this->items;
    }
}
