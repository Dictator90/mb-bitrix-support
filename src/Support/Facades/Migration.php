<?php

declare(strict_types=1);

namespace MB\Bitrix\Support\Facades;

use MB\Bitrix\Migration\Facade as MigrationFacade;
use MB\Bitrix\Support\Facade;

/**
 * Static facade for the migration facade service.
 *
 * This wraps the container-bound `migration.facade` service
 * (alias of {@see MigrationFacade}).
 */
final class Migration extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return 'migration.facade';
    }
}

