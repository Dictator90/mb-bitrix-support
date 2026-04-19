<?php

declare(strict_types=1);

namespace MB\Bitrix\Support\Facades;

use MB\Bitrix\Support\Facade;

/**
 * @method static bool has(string $key)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static array<string, mixed> all()
 *
 * @see \MB\Bitrix\Contracts\Config\Repository
 */
class Config extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'config';
    }
}
