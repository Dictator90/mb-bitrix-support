<?php

declare(strict_types=1);

namespace MB\Bitrix\Support;

use MB\Bitrix\Contracts\Log\LoggerFactoryInterface;
use MB\Bitrix\Foundation\ServiceProvider;
use MB\Bitrix\Logger\LoggerFactory;

/**
 * Registers logger factory and default logger in the container.
 */
final class LoggerServiceProvider extends ServiceProvider
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

