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
        $this->app->singleton('asset', static fn () => Asset::getInstance());
    }
}

