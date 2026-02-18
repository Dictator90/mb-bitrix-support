<?php

namespace MB\Bitrix\Page;

use MB\Bitrix\Foundation\ServiceProvider as BaseServiceProvider;

/**
 * Registers the Bitrix asset manager bindings.
 */
final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->alias('asset', Asset::class);
        $this->app->singleton(Asset::class, static fn () => Asset::getInstance());
    }
}

