<?php

declare(strict_types=1);

namespace MB\Bitrix;

use Bitrix\Main\Application as BitrixApplication;
use MB\Bitrix\Bitrix\Adapters\ApplicationAdapter as ApplicationAdapterImpl;
use MB\Bitrix\Bitrix\Adapters\LocalizationAdapter as LocalizationAdapterImpl;
use MB\Bitrix\Bitrix\Adapters\QuotaAdapter as QuotaAdapterImpl;
use MB\Bitrix\Contracts\Bitrix\ApplicationAdapter as ApplicationAdapterContract;
use MB\Bitrix\Contracts\Bitrix\LocalizationAdapter as LocalizationAdapterContract;
use MB\Bitrix\Contracts\Bitrix\QuotaAdapter as QuotaAdapterContract;
use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Foundation\ServiceProvider as BaseServiceProvider;

/**
 * Registers core Bitrix services (CMain, Application, context, request, cache).
 */
final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        global $APPLICATION;

        $this->app->instance('bitrix.cmain', $APPLICATION);
        $this->app->instance('bitrix.application', BitrixApplication::getInstance());

        $this->app->bind(
            'bitrix.context',
            static fn (Application $app) => $app->make('bitrix.application')->getContext()
        );

        $this->app->bind(
            'bitrix.request',
            static fn (Application $app) => $app->make('bitrix.context')->getRequest()
        );

        $this->app->bind(
            'bitrix.cache',
            static fn (Application $app) => $app->make('bitrix.application')->getCache()
        );

        $this->app->singleton(ApplicationAdapterContract::class, static fn () => new ApplicationAdapterImpl());
        $this->app->singleton(QuotaAdapterContract::class, static fn () => new QuotaAdapterImpl());
        $this->app->singleton(LocalizationAdapterContract::class, static fn () => new LocalizationAdapterImpl());
    }
}

