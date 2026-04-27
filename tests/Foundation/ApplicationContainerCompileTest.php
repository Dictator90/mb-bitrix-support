<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Foundation;

use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Tests\Support\MinimalKernel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Поведение контейнера после отказа от eager {@see \MB\Container\Container::compile()} в конструкторе {@see Application}.
 */
final class ApplicationContainerCompileTest extends TestCase
{
    protected function tearDown(): void
    {
        Application::setInstance(null);
    }

    public function test_minimal_kernel_starts_with_lazy_container_and_make_works(): void
    {
        $app = new MinimalKernel();

        self::assertNull($this->readCompiledRepository($app));
        self::assertInstanceOf(\MB\Bitrix\Config\ArrayRepository::class, $app->make('config'));
        self::assertSame($app, $app->make('app'));
    }

    public function test_optional_compile_does_not_break_resolution(): void
    {
        $app = new MinimalKernel();
        $app->compile();

        self::assertNotNull($this->readCompiledRepository($app));
        self::assertInstanceOf(\MB\Bitrix\Config\ArrayRepository::class, $app->make('config'));
    }

    private function readCompiledRepository(Application $app): ?object
    {
        $p = (new ReflectionClass(\MB\Container\Container::class))->getProperty('compiled');
        $p->setAccessible(true);

        return $p->getValue($app);
    }
}
