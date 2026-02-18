<?php

declare(strict_types=1);

namespace MB\Bitrix;

use Bitrix\Main\Application as BitrixApplication;
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
    }
}

