<?php

declare(strict_types=1);

namespace MB\Bitrix\File;

use MB\Bitrix\Contracts\File\DuplicateResolver as DuplicateResolverContract;
use MB\Bitrix\Contracts\File\FileServiceContract;
use MB\Bitrix\Contracts\File\FileRepository as FileRepositoryContract;
use MB\Bitrix\Contracts\File\MetadataReader as MetadataReaderContract;
use MB\Bitrix\Contracts\File\Uploader as UploaderContract;
use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Foundation\ServiceProvider as BaseServiceProvider;
use MB\Bitrix\File\Services\DuplicateResolver;
use MB\Bitrix\File\Services\FileRepository;
use MB\Bitrix\File\Services\MetadataReader;
use MB\Bitrix\File\Services\Uploader;

final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UploaderContract::class, static fn (Application $app) => new Uploader($app->make(\MB\Bitrix\Contracts\Bitrix\ApplicationAdapter::class)));
        $this->app->singleton(DuplicateResolverContract::class, static fn () => new DuplicateResolver());
        $this->app->singleton(MetadataReaderContract::class, static fn () => new MetadataReader());
        $this->app->singleton(FileRepositoryContract::class, static fn () => new FileRepository());

        $this->app->singleton(
            'file.service',
            static fn (Application $app) => new FileService(
                $app->make(UploaderContract::class),
                $app->make(DuplicateResolverContract::class),
                $app->make(MetadataReaderContract::class),
                $app->make(FileRepositoryContract::class),
                $app->make(\MB\Bitrix\Contracts\Bitrix\ApplicationAdapter::class),
                $app->make(\MB\Bitrix\Contracts\Bitrix\QuotaAdapter::class),
                $app->make(\MB\Bitrix\Contracts\Bitrix\LocalizationAdapter::class),
            )
        );
        $this->app->singleton(
            FileServiceContract::class,
            static fn (Application $app) => $app->make('file.service')
        );
    }
}
