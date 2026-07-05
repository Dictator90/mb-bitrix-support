<?php

declare(strict_types=1);

namespace MB\Bitrix\Module;

use MB\Bitrix\AdminKit\Manager\AdminKitManager;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Migration\Facade as MigrationFacade;

/**
 * Per-module bindings resolved from {@see Application} (module id → services).
 */
final class ModuleContainer
{
    public function __construct(
        private Application $app,
        private string $moduleId
    ) {}

    public function module(): ModuleEntityContract
    {
        return $this->app->make($this->moduleId . ':module');
    }

    public function adminKit(): AdminKitManager
    {
        return new AdminKitManager($this->module());
    }

    public function migrationFacade(): MigrationFacade
    {
        return new MigrationFacade($this->module());
    }
}
