<?php
namespace MB\Bitrix\Foundation;

use Bitrix\Main\Application as BitrixApplication;
use MB\Bitrix\ServiceProvider as BitrixServiceProvider;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Filesystem\ServiceProvider as FilesystemServiceProvider;
use MB\Bitrix\Migration\ServiceProvider as MigrationServiceProvider;
use MB\Bitrix\Logger\ServiceProvider as LoggerServiceProvider;
use MB\Bitrix\Module\Entity as ModuleEntity;
use MB\Bitrix\Module\ServiceProvider as ModuleServiceProvider;
use MB\Bitrix\Page\Asset;
use MB\Bitrix\Page\ServiceProvider as AssetServiceProvider;
use MB\Bitrix\Traits\BitrixEventsObservableTrait;
use MB\Container\Container;
use MB\Container\Exceptions\ContainerException;
use MB\Container\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

class Application extends Container
{
    use BitrixEventsObservableTrait;

    protected static ?self $instance = null;

    /**
     * Track resolved bindings (for resolved() and loadDeferredProviderIfNeeded).
     *
     * @var array<string, true>
     */
    protected array $resolved = [];

    /**
     * Parameter override stack for makeWith.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $withStack = [];

    protected ?string $basePath = null;

    const ON_BUILD_KERNEL_APPLICATION_EVENT = 'onBuildKernelApplication';
    const ON_BEFORE_BOOT_KERNEL_APPLICATION_EVENT = 'onBeforeBootKernelApplication';
    const ON_AFTER_BOOT_KERNEL_APPLICATION_EVENT = 'onAfterBootKernelApplication';

    /**
     * The array of registered callbacks.
     *
     * @var callable[]
     */
    protected array $registeredCallbacks = [];

    /**
     * Indicates if the application has been bootstrapped before.
     *
     * @var bool
     */
    protected bool $hasBeenBootstrapped = false;

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected bool $booted = false;

    /**
     * The array of booting callbacks.
     *
     * @var callable[]
     */
    protected array $bootingCallbacks = [];

    /**
     * The array of booted callbacks.
     *
     * @var callable[]
     */
    protected array $bootedCallbacks = [];

    /**
     * The deferred services and their providers.
     *
     * @var array
     */
    protected array $deferredServices = [];

    /**
     * All of the registered service providers.
     *
     * @var array<string, ServiceProvider>
     */
    protected array $serviceProviders = [];

    /**
     * The names of the loaded service providers.
     *
     * @var array
     */
    protected array $loadedProviders = [];

    /**
     * Create a new Kernel Application instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerEvents();

        $this->registerBaseBindings();
        $this->bindPathsInContainer();
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();

        $this->compile();

        $this->attachEvents();
    }

    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance('app', $this);
    }

    /**
     * Register all of the base service providers.
     *
     * @return void
     */
    protected function registerBaseServiceProviders(): void
    {
        $this->register(AssetServiceProvider::class);
        $this->register(FilesystemServiceProvider::class);
        $this->register(MigrationServiceProvider::class);
        $this->register(BitrixServiceProvider::class);
        $this->register(LoggerServiceProvider::class);
        $this->register(ModuleServiceProvider::class);
    }

    protected function registerEvents(): void
    {
        /**
         * Kernel-level lifecycle events.
         *
         *  - ON_BUILD_KERNEL_APPLICATION_EVENT: dispatched from the constructor after base bindings
         *    and providers are registered, but before boot.
         *  - ON_BEFORE_BOOT_KERNEL_APPLICATION_EVENT: dispatched at the beginning of boot().
         *  - ON_AFTER_BOOT_KERNEL_APPLICATION_EVENT: dispatched at the end of boot().
         */
        $this->attach('mb.core', self::ON_BUILD_KERNEL_APPLICATION_EVENT);
        $this->attach('mb.core', self::ON_BEFORE_BOOT_KERNEL_APPLICATION_EVENT);
        $this->attach('mb.core', self::ON_AFTER_BOOT_KERNEL_APPLICATION_EVENT);
    }

    protected function attachEvents(): void
    {
        $this->notify(self::ON_BUILD_KERNEL_APPLICATION_EVENT, ['app' => $this]);
    }


    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped(): bool
    {
        return $this->hasBeenBootstrapped;
    }

    /**
     * Bind all of the application paths in the container.
     * Paths are registered as singletons (mb4it instance() accepts only object).
     *
     * @return void
     */
    protected function bindPathsInContainer(): void
    {
        $this->singleton('path.root', fn () => BitrixApplication::getDocumentRoot());
        $this->singleton('path.local', fn (Application $app) => $app->get('path.root') . '/local');
        $this->singleton('path.bitrix', fn (Application $app) => $app->get('path.root') . '/bitrix');
        $this->singleton('path.template', fn () => defined('SITE_TEMPLATE_PATH') ? SITE_TEMPLATE_PATH : '');

    }

    public function registerModule($moduleId): void
    {
        $this->singleton("$moduleId:module", fn (Application $app) => $app->makeWith(ModuleEntityContract::class, ['moduleId' => $moduleId]));
        $this->bind("$moduleId:config", fn (Application $app) => $app->make("$moduleId:module")->getConfig(''));
        $this->singleton("$moduleId:migration", fn (Application $app) => $app->makeWith(ModuleEntityContract::class, ['module' => "$moduleId:module"]));
        $this->singleton("$moduleId:logger", fn (Application $app) => $app->makeWith('logger', ['moduleId' => $moduleId]));
        //$this->singleton("$moduleId:admin.page", fn (Application $app) => new PageManager($app->make("$moduleId:module")));
    }

    /**
     * @template T of object
     * @param class-string<T>|string $abstract
     * @param array<string, mixed> $parameters
     * @return T
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $this->loadDeferredProviderIfNeeded($abstract);

        if ($parameters !== []) {
            $this->withStack[] = $parameters;
            try {
                $instance = $this->buildWithParameters($abstract, $parameters);
                $this->resolved[$abstract] = true;
                return $instance;
            } finally {
                array_pop($this->withStack);
            }
        }

        $instance = parent::make($abstract);
        $this->resolved[$abstract] = true;
        return $instance;
    }

    /**
     * Create an instance with constructor parameters (bypasses parent singleton cache).
     */
    protected function buildWithParameters(string $abstract, array $parameters): object
    {
        $resolved = $this->resolveAliasName($abstract);
        $concrete = $this->getConcreteBinding($resolved);
        $class = (is_string($concrete) && !is_callable($concrete) && class_exists($concrete))
            ? $concrete
            : (class_exists($resolved) ? $resolved : null);

        if ($class === null) {
            throw new NotFoundException("No concrete class for [{$abstract}] for makeWith.");
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $parameters)) {
                $value = $parameters[$name];
                if (is_string($value) && $this->has($value)) {
                    $value = parent::make($value);
                }
                $args[] = $value;
            } else {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $args[] = parent::make($type->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($param->isVariadic()) {
                    $args[] = [];
                } else {
                    throw new ContainerException("Unresolvable parameter \${$name} for [{$class}].");
                }
            }
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Create an instance with the given parameters.
     *
     * @template T of object
     * @param class-string<T>|string $abstract
     * @param array<string, mixed> $parameters
     * @return T
     */
    public function makeWith(string $abstract, array $parameters = []): mixed
    {
        return $this->make($abstract, $parameters);
    }

    /**
     * Call the given callable and resolve its parameters from the container.
     *
     * @param callable $callable
     * @param array<string, mixed> $parameters
     * @return mixed
     */
    public function call(callable $callable, array $parameters = []): mixed
    {
        if (is_array($callable)) {
            $reflection = new ReflectionMethod($callable[0], $callable[1]);
        } elseif ($callable instanceof \Closure) {
            $reflection = new ReflectionFunction($callable);
        } else {
            $reflection = new ReflectionMethod($callable, '__invoke');
        }

        $args = [];
        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $parameters)) {
                $args[] = $parameters[$name];
            } else {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $args[] = $this->make($type->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($param->isVariadic()) {
                    $args[] = [];
                } else {
                    throw new ContainerException("Unresolvable parameter \${$name} for call.");
                }
            }
        }

        return $callable(...$args);
    }

    /**
     * Determine if the given type has been resolved.
     */
    public function resolved(string $abstract): bool
    {
        return isset($this->resolved[$abstract]);
    }

    public static function setInstance(?ContainerInterface $container = null): void
    {
        static::$instance = $container instanceof self ? $container : null;
    }

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            throw new \RuntimeException('KernelApplication instance has not been set.');
        }
        return static::$instance;
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return T
     */
    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    /**
     * Register a new registered listener.
     *
     * @param callable $callback
     * @return static
     */
    public function registered(callable $callback): static
    {
        $this->registeredCallbacks[] = $callback;
        return $this;
    }

    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        // Dispatch lifecycle event before booting callbacks and providers.
        $this->notify(self::ON_BEFORE_BOOT_KERNEL_APPLICATION_EVENT, ['app' => $this]);

        $this->fireAppCallbacks($this->bootingCallbacks);

        array_walk($this->serviceProviders, function ($p) {
            $this->bootProvider($p);
        });

        $this->booted = true;

        $this->fireAppCallbacks($this->bootedCallbacks);

        // Dispatch lifecycle event after all providers are booted.
        $this->notify(self::ON_AFTER_BOOT_KERNEL_APPLICATION_EVENT, ['app' => $this]);

        $this->hasBeenBootstrapped = true;
    }

    public function registerCoreContainerAliases(): void
    {
        foreach ([
            'app' => [self::class, Container::class, ContainerInterface::class],
            'asset' => [Asset::class],
            'module' => [ModuleEntity::class, ModuleEntityContract::class],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * Register a service provider with the application.
     *
     * @param  ServiceProvider|string $provider
     * @param  bool  $force
     * @return ServiceProvider
     */
    public function register($provider, $force = false)
    {
        if (($registered = $this->getProvider($provider)) && ! $force) {
            return $registered;
        }

        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();

        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }

        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $key = is_int($key) ? $value : $key;

                $this->singleton($key, $value);
            }
        }

        $this->markAsRegistered($provider);

        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * Register a deferred service provider using its declared services.
     *
     * The provider's {@see ServiceProvider::provides()} list is used to
     * populate the application's deferred service map. The provider will
     * be fully registered only when one of its services is first resolved.
     *
     * @param  ServiceProvider|string  $provider
     * @return ServiceProvider
     */
    public function registerDeferred(ServiceProvider|string $provider): ServiceProvider
    {
        if (is_string($provider)) {
            $providerInstance = $this->resolveProvider($provider);
            $providerClass = $provider;
        } else {
            $providerInstance = $provider;
            $providerClass = get_class($provider);
        }

        foreach ($providerInstance->provides() as $service) {
            $this->deferredServices[$service] = $providerClass;
        }

        return $providerInstance;
    }

    /**
     * Get the registered service provider instance if it exists.
     *
     * @param  ServiceProvider|string  $provider
     * @return ServiceProvider|null
     */
    public function getProvider($provider): ?ServiceProvider
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return $this->serviceProviders[$name] ?? null;
    }

    /**
     * Get the registered service provider instances if any exist.
     *
     * @param  ServiceProvider|string  $provider
     * @return array
     */
    public function getProviders($provider): array
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return array_values(array_filter($this->serviceProviders, fn ($value) => $value instanceof $name));
    }

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string  $provider
     * @return ServiceProvider
     */
    public function resolveProvider($provider): ServiceProvider
    {
        return new $provider($this);
    }

    /**
     * Load the deferred provider if the given type is a deferred service and the instance has not been loaded.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function loadDeferredProviderIfNeeded(string $abstract): void
    {
        if ($this->isDeferredService($abstract) && !$this->resolved($abstract)) {
            $this->loadDeferredProvider($abstract);
        }
    }

    /**
     * Call the booting callbacks for the application.
     *
     * @param callable[] $callbacks
     * @return static
     */
    protected function fireAppCallbacks(array &$callbacks): static
    {
        $index = 0;

        while ($index < count($callbacks)) {
            $callbacks[$index]($this);

            $index++;
        }

        return $this;
    }

    /**
     * Mark the given provider as registered.
     *
     * @param ServiceProvider $provider
     * @return static
     */
    protected function markAsRegistered($provider): static
    {
        $class = get_class($provider);
        $this->serviceProviders[$class] = $provider;
        $this->loadedProviders[$class] = true;

        return $this;
    }

    /**
     * Boot the given service provider.
     *
     * @param ServiceProvider $provider
     * @return static
     */
    protected function bootProvider(ServiceProvider $provider): static
    {
        $provider->callBootingCallbacks();

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }

        $provider->callBootedCallbacks();
        return $this;
    }

    /**
     * Register a new boot listener.
     *
     * @param callable $callback
     * @return static
     */
    public function booting(callable $callback): static
    {
        $this->bootingCallbacks[] = $callback;
        return $this;
    }

    /**
     * Load and boot all of the remaining deferred providers.
     *
     * @return static
     */
    public function loadDeferredProviders(): static
    {
        // We will simply spin through each of the deferred providers and register each
        // one and boot them if the application has booted. This should make each of
        // the remaining services available to this application for immediate use.
        foreach ($this->deferredServices as $service => $provider) {
            $this->loadDeferredProvider($service);
        }

        $this->deferredServices = [];
        return $this;
    }

    /**
     * Load the provider for a deferred service.
     *
     * @param string $service
     * @return void
     */
    public function loadDeferredProvider($service): void
    {
        if (! $this->isDeferredService($service)) {
            return;
        }

        $provider = $this->deferredServices[$service];
        if (! isset($this->loadedProviders[$provider])) {
            $this->registerDeferredProvider($provider, $service);
        }
    }

    /**
     * Register a deferred provider and service.
     *
     * @param  string  $provider
     * @param  string|null  $service
     * @return void
     */
    public function registerDeferredProvider($provider, $service = null): void
    {
        if ($service) {
            unset($this->deferredServices[$service]);
        }

        $this->register($instance = new $provider($this));

        if (!$this->isBooted()) {
            $this->booting(function () use ($instance) {
                $this->bootProvider($instance);
            });
        }
    }

    /**
     * Set the application's deferred services.
     *
     * @param  array  $services
     * @return static
     */
    public function setDeferredServices(array $services): static
    {
        $this->deferredServices = $services;
        return $this;
    }

    /**
     * Determine if the given service is a deferred service.
     *
     * @param string $service
     * @return bool
     */
    public function isDeferredService(string $service): bool
    {
        return isset($this->deferredServices[$service]);
    }

    /**
     * Add an array of services to the application's deferred services.
     *
     * @param array $services
     * @return static
     */
    public function addDeferredServices(array $services): static
    {
        $this->deferredServices = array_merge($this->deferredServices, $services);
        return $this;
    }
}