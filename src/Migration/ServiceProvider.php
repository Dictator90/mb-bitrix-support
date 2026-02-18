<?php

declare(strict_types=1);

namespace MB\Bitrix\Migration;

use MB\Bitrix\Contracts\Migration\Facade as FacadeContract;
use MB\Bitrix\Foundation\ServiceProvider as BaseServiceProvider;
use MB\Bitrix\Migration\Facade as MigrationFacade;

/**
 * Registers migration facade bindings.
 */
final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->alias('migration.facade', FacadeContract::class);
        $this->app->bind(FacadeContract::class, MigrationFacade::class);
    }
}

