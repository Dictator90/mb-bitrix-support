<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Logger;

use MB\Bitrix\Contracts\Log\LoggerFactoryInterface;
use MB\Bitrix\Logger\LoggerFactory;
use MB\Bitrix\Logger\ModuleLoggerFactory;
use MB\Bitrix\Logger\ServiceProvider;
use MB\Bitrix\Tests\Support\MinimalKernel;
use PHPUnit\Framework\TestCase;

final class LoggerServiceProviderTest extends TestCase
{
    public function test_logger_binding_resolves_concrete_factory(): void
    {
        $app = new MinimalKernel();
        $app->register(new ServiceProvider($app));

        $logger = $app->make('logger');

        self::assertInstanceOf(LoggerFactory::class, $logger);
        self::assertInstanceOf(LoggerFactoryInterface::class, $app->make(LoggerFactoryInterface::class));
    }

    public function test_module_logger_binding_resolves_factory_with_module_id(): void
    {
        $app = new MinimalKernel();
        $app->register(new ServiceProvider($app));
        $app->registerModule('vendor.test');

        $logger = $app->make('vendor.test:logger');

        self::assertInstanceOf(LoggerFactoryInterface::class, $logger);
        self::assertSame('vendor.test', $this->readModuleId($logger));
        self::assertInstanceOf(ModuleLoggerFactory::class, $app->make(ModuleLoggerFactory::class));
    }

    private function readModuleId(LoggerFactoryInterface $logger): ?string
    {
        $reflection = new \ReflectionProperty($logger, 'moduleId');
        $reflection->setAccessible(true);

        $value = $reflection->getValue($logger);

        return is_string($value) ? $value : null;
    }
}
