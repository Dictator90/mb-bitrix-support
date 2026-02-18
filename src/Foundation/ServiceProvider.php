<?php

namespace MB\Bitrix\Foundation;

use Closure;
use MB\Container\Container;
use MB\Container\Exceptions\ContainerException;

/**
 * @property array<string, string> $bindings All of the container bindings that should be registered.
 * @property array<array-key, string> $singletons All of the singletons that should be registered.
 */
abstract class ServiceProvider
{
    /**
     * The application instance.
     *
     * @var Container
     */
    protected $app;

    /**
     * All of the registered booting callbacks.
     *
     * @var array
     */
    protected $bootingCallbacks = [];

    /**
     * All of the registered booted callbacks.
     *
     * @var array
     */
    protected $bootedCallbacks = [];

    /**
     * The paths that should be published.
     *
     * Format: ['providerClass' => ['from' => 'to', ...], ...].
     *
     * @var array<string, array<string, string>>
     */
    public static $publishes = [];

    /**
     * The paths that should be published by group.
     *
     * Format: ['groupName' => ['from' => 'to', ...], ...].
     *
     * @var array<string, array<string, string>>
     */
    public static $publishGroups = [];

    /**
     * Create a new service provider instance.
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Register a booting callback to be run before the "boot" method is called.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function booting(Closure $callback)
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Register a booted callback to be run after the "boot" method is called.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function booted(Closure $callback)
    {
        $this->bootedCallbacks[] = $callback;
    }

    /**
     * Call the registered booting callbacks.
     *
     * @return void
     */
    public function callBootingCallbacks()
    {
        $index = 0;

        while ($index < count($this->bootingCallbacks)) {
            $this->app->call($this->bootingCallbacks[$index]);

            $index++;
        }
    }

    /**
     * Call the registered booted callbacks.
     *
     * @return void
     */
    public function callBootedCallbacks()
    {
        $index = 0;

        while ($index < count($this->bootedCallbacks)) {
            $this->app->call($this->bootedCallbacks[$index]);

            $index++;
        }
    }

    /**
     * Setup an after resolving listener, or fire immediately if already resolved.
     *
     * @param string $name
     * @param callable $callback
     * @return void
     * @throws ContainerException
     */
    protected function callAfterResolving($name, $callback)
    {
        $this->app->afterResolving($name, $callback);

        if ($this->app->resolved($name)) {
            $callback($this->app->make($name), $this->app);
        }
    }

    /**
     * Add a publish group / tag to the service provider.
     *
     * @param  string  $group
     * @param  array<string, string>  $paths
     * @return static
     */
    protected function addPublishGroup($group, $paths): static
    {
        if (! array_key_exists($group, static::$publishGroups)) {
            static::$publishGroups[$group] = [];
        }

        static::$publishGroups[$group] = array_merge(
            static::$publishGroups[$group], $paths
        );

        return $this;
    }

    /**
     * Get the paths to publish.
     *
     * @param  string|null  $provider
     * @param  string|null  $group
     * @return array<string, string>
     */
    public static function pathsToPublish($provider = null, $group = null): array
    {
        if (!is_null($paths = static::pathsForProviderOrGroup($provider, $group))) {
            return $paths;
        }

        return (collect(static::$publishes))->reduce(function ($paths, $p) {
            return array_merge($paths, $p);
        }, []);
    }

    /**
     * Get the paths for the provider or group (or both).
     *
     * @param  string|null  $provider
     * @param  string|null  $group
     * @return array<string, string>
     */
    protected static function pathsForProviderOrGroup($provider, $group): array
    {
        if ($provider && $group) {
            return static::pathsForProviderAndGroup($provider, $group);
        } elseif ($group && array_key_exists($group, static::$publishGroups)) {
            return static::$publishGroups[$group];
        } elseif ($provider && array_key_exists($provider, static::$publishes)) {
            return static::$publishes[$provider];
        } elseif ($group || $provider) {
            return [];
        }

        return [];
    }

    /**
     * Get the paths for the provider and group.
     *
     * @param  string  $provider
     * @param  string  $group
     * @return array<string, string>
     */
    protected static function pathsForProviderAndGroup($provider, $group): array
    {
        if (! empty(static::$publishes[$provider]) && ! empty(static::$publishGroups[$group])) {
            return array_intersect_key(static::$publishes[$provider], static::$publishGroups[$group]);
        }

        return [];
    }

    /**
     * Get the service providers available for publishing.
     *
     * @return array
     */
    public static function publishableProviders()
    {
        return array_keys(static::$publishes);
    }


    /**
     * Get the groups available for publishing.
     *
     * @return array
     */
    public static function publishableGroups()
    {
        return array_keys(static::$publishGroups);
    }

    /**
     * Get the services provided by the provider.
     *
     * These identifiers are used by {@see Application::registerDeferred()}
     * to build the application's deferred service map.
     *
     * @return array<int, string>
     */
    public function provides()
    {
        return [];
    }

    /**
     * Get the events that trigger this service provider to register.
     *
     * This can be used by higher-level infrastructure to attach the provider
     * to framework- or application-specific events for lazy loading.
     *
     * @return array<int, string>
     */
    public function when()
    {
        return [];
    }
}
