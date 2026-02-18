<?php

declare(strict_types=1);

namespace MB\Bitrix\Support\Facades;

use MB\Bitrix\Page\Asset as AssetService;
use MB\Bitrix\Support\Facade;

/**
 * Static facade for the Bitrix asset manager.
 *
 * This is a thin wrapper over the container-bound `asset` service
 * (alias of {@see AssetService}).
 *
 * @method static AssetService getInstance()
 * @method static void addCss(string $path, bool $bAdditional = false, int $priority = 100)
 * @method static void addJs(string $path, bool $bAdditional = false, int $priority = 100)
 * @method static void addString(string $content, bool $bUnique = false, bool $bAdditional = false, string $location = 'HEAD')
 */
final class Asset extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return 'asset';
    }
}

