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
        $this->app->singleton(LoggerFactoryInterface::class, LoggerFactory::class);
        $this->app->singleton('logger', LoggerFactoryInterface::class);
        $this->app->singleton('logger', LoggerFactoryInterface::class);
    }

    /**
     * @return array<int, string>
     */
    public function provides()
    {
        return [
            LoggerFactoryInterface::class,
            'logger',
        ];
    }
}

