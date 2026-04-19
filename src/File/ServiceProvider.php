<?php

declare(strict_types=1);

namespace MB\Bitrix\File;

use MB\Bitrix\Contracts\File\FileServiceContract;
use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Foundation\ServiceProvider as BaseServiceProvider;

final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('file.service', static fn () => new FileService());
        $this->app->singleton(
            FileServiceContract::class,
            static fn (Application $app) => $app->make('file.service')
        );
    }
}
