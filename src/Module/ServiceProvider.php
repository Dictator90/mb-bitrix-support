<?php
namespace MB\Bitrix\Module;

use Bitrix\Main\ModuleManager;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Foundation\ServiceProvider as BaseServiceProvider;
use MB\Bitrix\Module\Entity as ModuleEntity;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->app->bind(ModuleEntityContract::class, ModuleEntity::class);

        foreach ($this->getInstalledModules() as $moduleId) {
            $this->app->registerModule($moduleId);
        }
    }

    protected function getInstalledModules()
    {
        return ModuleManager::getInstalledModules();
    }
}