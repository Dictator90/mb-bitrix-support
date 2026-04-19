<?php

declare(strict_types=1);

namespace MB\Bitrix\Support;

use MB\Bitrix\Foundation\Application;

/**
 * Base class for static facades over the Application container.
 *
 * Facades are thin convenience wrappers and should not be used
 * as a replacement for dependency injection in domain code.
 */
abstract class Facade
{
    /**
     * @var array<class-string, object>
     */
    protected static array $resolvedInstance = [];

    /**
     * Get the service identifier in the container.
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Clear cached facade roots (e.g. between tests).
     */
    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstance = [];
    }

    /**
     * Swap the underlying instance for this facade (testing).
     */
    public static function swap(object $instance): object
    {
        static::$resolvedInstance[static::class] = $instance;

        return $instance;
    }

    /**
     * Resolve the underlying instance from the Application container.
     */
    protected static function resolveInstance(): object
    {
        if (array_key_exists(static::class, static::$resolvedInstance)) {
            return static::$resolvedInstance[static::class];
        }

        $app = Application::getInstance();

        /** @var object $service */
        $service = $app->make(static::getFacadeAccessor());

        return static::$resolvedInstance[static::class] = $service;
    }

    /**
     * Dynamically pass static method calls to the underlying instance.
     *
     * @param array<int,mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $instance = static::resolveInstance();

        if (!method_exists($instance, $method)) {
            throw new \BadMethodCallException(sprintf(
                'Method %s::%s does not exist on the underlying facade root.',
                get_class($instance),
                $method
            ));
        }

        return $instance->{$method}(...$arguments);
    }
}

