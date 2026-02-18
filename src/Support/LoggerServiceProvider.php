<?php

declare(strict_types=1);

namespace MB\Bitrix\Support;

use MB\Bitrix\Contracts\Log\LoggerFactoryInterface;
use MB\Bitrix\Foundation\ServiceProvider;
use MB\Bitrix\Logger\LoggerFactory;
use MB\Bitrix\Logger\UniversalLogger;

/**
 * Registers logger factory and default logger in the container.
 */
final class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind factory interface to default implementation.
        $this->app->singleton(LoggerFactoryInterface::class, LoggerFactory::class);

        // Optional shortcut for a shared project-wide logger instance.
        $this->app->singleton('logger', function () {
            /** @var LoggerFactoryInterface $factory */
            $factory = $this->app->make(LoggerFactoryInterface::class);

            return $factory->universal('mb_core/default');
        });
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

