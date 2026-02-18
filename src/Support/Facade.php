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
     * Get the service identifier in the container.
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Resolve the underlying instance from the Application container.
     */
    protected static function resolveInstance(): object
    {
        $app = Application::getInstance();

        /** @var object $service */
        $service = $app->make(static::getFacadeAccessor());

        return $service;
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

