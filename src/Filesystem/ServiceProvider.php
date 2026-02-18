<?php

declare(strict_types=1);

namespace MB\Bitrix\Filesystem;

use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Foundation\ServiceProvider as BaseServiceProvider;
use MB\Bitrix\Module\Entity as ModuleEntity;
use MB\Filesystem\Contracts\Filesystem as FilesystemContract;

/**
 * Registers filesystem bridge and module-related bindings.
 */
final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        // Filesystem bridge (variant A: static MB\Bitrix\Filesystem singleton).
        $this->app->alias('filesystem', FilesystemContract::class);
        $this->app->singleton(
            FilesystemContract::class,
            static fn () => Filesystem::instance()
        );
    }
}

