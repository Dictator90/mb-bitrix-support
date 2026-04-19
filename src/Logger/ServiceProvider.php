<?php

declare(strict_types=1);

namespace MB\Bitrix\Logger;

use MB\Bitrix\Contracts\Log\LoggerFactoryInterface;
use MB\Bitrix\Foundation\ServiceProvider as BaseServiceProvider;

/**
 * Registers logger factory and default logger in the container.
 */
final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('logger', LoggerFactory::class);
        $this->app->singleton(
            LoggerFactoryInterface::class,
            static fn ($app) => $app->make('logger')
        );
        $this->app->singleton(
            ModuleLoggerFactory::class,
            static fn () => new ModuleLoggerFactory()
        );
    }

    /**
     * @return array<int, string>
     */
    public function provides()
    {
        return [
            LoggerFactoryInterface::class,
            ModuleLoggerFactory::class,
            'logger',
        ];
    }
}

