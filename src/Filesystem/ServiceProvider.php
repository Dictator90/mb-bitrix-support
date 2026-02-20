<?php

namespace MB\Bitrix\Filesystem;

use MB\Bitrix\Foundation\ServiceProvider as BaseServiceProvider;
use MB\Filesystem\Contracts\Filesystem as FilesystemContract;

/**
 * Registers filesystem bridge and module-related bindings.
 */
final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->alias('filesystem', FilesystemContract::class);
        $this->app->singleton(FilesystemContract::class, fn () => Filesystem::instance());
    }
}

