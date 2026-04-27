<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Foundation;

use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Foundation\ServiceProvider;
use MB\Bitrix\Tests\Support\MinimalKernel;
use PHPUnit\Framework\TestCase;

final class ApplicationLifecycleTest extends TestCase
{
    protected function tearDown(): void
    {
        Application::setInstance(null);
        DeferredProbeProvider::$bootCount = 0;
    }

    public function test_boot_callbacks_are_executed_once_and_flags_are_set(): void
    {
        $app = new MinimalKernel();
        $timeline = [];

        $app->booting(function (Application $kernel) use (&$timeline): void {
            $timeline[] = 'booting';
            self::assertFalse($kernel->isBooted());
        });
        $app->booted(function (Application $kernel) use (&$timeline): void {
            $timeline[] = 'booted';
            self::assertTrue($kernel->isBooted());
        });

        self::assertFalse($app->isBooted());
        self::assertFalse($app->hasBeenBootstrapped());

        $app->boot();
        $app->boot();

        self::assertTrue($app->isBooted());
        self::assertTrue($app->hasBeenBootstrapped());
        self::assertSame(['booting', 'booted'], $timeline);
    }

    public function test_registered_callback_receives_provider_context(): void
    {
        $app = new MinimalKernel();
        $seenProviders = [];

        $app->registered(function (Application $kernel, ServiceProvider $provider) use (&$seenProviders): void {
            self::assertSame($kernel, Application::getInstance());
            $seenProviders[] = $provider::class;
        });

        $app->register(LifecycleProbeProvider::class);

        self::assertSame([LifecycleProbeProvider::class], $seenProviders);
        self::assertInstanceOf(\stdClass::class, $app->make('probe.registered'));
    }

    public function test_deferred_provider_registers_on_first_resolution_and_boots_during_boot_cycle(): void
    {
        $app = new MinimalKernel();
        $app->registerDeferred(DeferredProbeProvider::class);

        self::assertTrue($app->isDeferredService('probe.deferred'));
        self::assertNull($app->getProvider(DeferredProbeProvider::class));

        self::assertInstanceOf(DeferredPayload::class, $app->make('probe.deferred'));
        self::assertNotNull($app->getProvider(DeferredProbeProvider::class));
        self::assertSame(0, DeferredProbeProvider::$bootCount);

        $app->boot();

        self::assertSame(1, DeferredProbeProvider::$bootCount);
    }

    public function test_make_with_supports_class_and_alias_resolution(): void
    {
        $app = new MinimalKernel();
        $app->bind('probe.target', ProbeTarget::class);

        $fromClass = $app->makeWith(ProbeTarget::class, ['name' => 'class-path']);
        $fromAlias = $app->makeWith('probe.target', ['name' => 'alias-path', 'code' => 99]);

        self::assertSame('class-path', $fromClass->name);
        self::assertSame(7, $fromClass->code);
        self::assertInstanceOf(ProbeDependency::class, $fromClass->dependency);

        self::assertSame('alias-path', $fromAlias->name);
        self::assertSame(99, $fromAlias->code);
        self::assertInstanceOf(ProbeDependency::class, $fromAlias->dependency);
    }
}

final class LifecycleProbeProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->instance('probe.registered', new \stdClass());
    }
}

final class DeferredProbeProvider extends ServiceProvider
{
    public static int $bootCount = 0;

    public function register()
    {
        $this->app->singleton('probe.deferred', fn () => new DeferredPayload('deferred-ready'));
    }

    public function boot(): void
    {
        self::$bootCount++;
    }

    public function provides()
    {
        return ['probe.deferred'];
    }
}

final class ProbeDependency
{
}

final class ProbeTarget
{
    public function __construct(
        public string $name,
        public ProbeDependency $dependency,
        public int $code = 7
    ) {
    }
}

final class DeferredPayload
{
    public function __construct(public string $name)
    {
    }
}
