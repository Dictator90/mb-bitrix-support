<?php

declare(strict_types=1);
namespace MB\Bitrix\Foundation;

use Bitrix\Main\Application as BitrixApplication;
use MB\Bitrix\Config\ArrayRepository as ConfigArrayRepository;
use MB\Bitrix\Foundation\PackageManifest;
use MB\Bitrix\Contracts\Config\Repository as ConfigRepositoryContract;
use MB\Bitrix\File\ServiceProvider as FileServiceProvider;
use MB\Bitrix\Filesystem\ServiceProvider as FilesystemServiceProvider;
use MB\Bitrix\Foundation\Orchestrator\BootOrchestrator;
use MB\Bitrix\Foundation\Orchestrator\DeferredProviderOrchestrator;
use MB\Bitrix\Foundation\Orchestrator\ProviderResolutionOrchestrator;
use MB\Bitrix\Logger\ModuleLoggerFactory;
use MB\Bitrix\Logger\ServiceProvider as LoggerServiceProvider;
use MB\Bitrix\Migration\Facade as MigrationFacade;
use MB\Bitrix\Migration\ServiceProvider as MigrationServiceProvider;
use MB\Bitrix\Module\Entity as ModuleEntity;
use MB\Bitrix\Module\ModuleContainer;
use MB\Bitrix\Module\ServiceProvider as ModuleServiceProvider;
use MB\Bitrix\Page\Asset;
use MB\Bitrix\Page\ServiceProvider as AssetServiceProvider;
use MB\Bitrix\ServiceProvider as BitrixServiceProvider;
use MB\Bitrix\Traits\BitrixEventsObservableTrait;
use MB\Container\AliasRegistry;
use MB\Container\BindingRegistry;
use MB\Container\Container;
use MB\Container\Exceptions\ContainerException;
use MB\Container\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;
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
     * Indicates that boot() has successfully completed at least once.
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
     * Indicates if the application is currently running boot().
     *
     * @var bool
     */
    protected bool $booting = false;

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

    private readonly BootOrchestrator $bootOrchestrator;
    private readonly DeferredProviderOrchestrator $deferredOrchestrator;
    private readonly ProviderResolutionOrchestrator $providerResolutionOrchestrator;

    /**
     * Create a new Kernel Application instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->bootOrchestrator = new BootOrchestrator();
        $this->deferredOrchestrator = new DeferredProviderOrchestrator();
        $this->providerResolutionOrchestrator = new ProviderResolutionOrchestrator();
        $this->registerEvents();

        $this->registerBaseBindings();
        $this->bindPathsInContainer();
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();

        // Eager compile() removed: bindings whose concrete requires runtime
        // parameters (e.g. ModuleEntity(string $id) via makeWith) cannot be
        // precompiled. Container::make() falls back to lazy build() when
        // $this->compiled is null. Opt into precompilation via compileToFile()
        // from the application layer if needed.

        $this->attachEvents();
    }

    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance('app', $this);

        $this->instance('config', new ConfigArrayRepository());
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
        $this->register(FileServiceProvider::class);
        $this->register(MigrationServiceProvider::class);
        $this->register(BitrixServiceProvider::class);
        $this->register(LoggerServiceProvider::class);
        $this->register(ModuleServiceProvider::class);
        $this->registerPackageProviders();
    }

    /**
     * Registers service providers advertised by installed mb4it packages via
     * their composer.json `extra.mb.providers`. This lets satellite packages
     * (console, migration, …) plug into the kernel without the support package
     * depending on them (avoiding a composer dependency cycle).
     */
    protected function registerPackageProviders(): void
    {
        foreach (PackageManifest::create()->providers() as $provider) {
            if (class_exists($provider)) {
                $this->register($provider);
            }
        }
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

    public function dispatchKernelLifecycleEvent(string $event): void
    {
        $this->notify($event, ['app' => $this]);
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

    public function getBasePath(): ?string
    {
        return $this->basePath;
    }

    /**
     * Set the application base path and load PHP config files from "{$basePath}/config/*.php" into the config repository.
     */
    public function setBasePath(string $path): static
    {
        $this->basePath = rtrim($path, "/\\");

        // Instance bindings are not visible to has(); always resolve config if registered.
        try {
            $config = $this->make('config');
            if ($config instanceof ConfigArrayRepository) {
                $config->loadFromDirectory($this->basePath . DIRECTORY_SEPARATOR . 'config');
            }
        } catch (NotFoundException) {
            // No config binding (non-standard Application subclass).
        }

        return $this;
    }

    /**
     * Load additional PHP config files from an arbitrary directory into the config repository.
     */
    public function loadConfigFrom(string $directory): static
    {
        try {
            $config = $this->make('config');
            if ($config instanceof ConfigArrayRepository) {
                $config->loadFromDirectory($directory);
            }
        } catch (NotFoundException) {
        }

        return $this;
    }

    public function registerModule($moduleId): void
    {
        $this->singleton("$moduleId:module", static fn () => new ModuleEntity($moduleId));
        $this->bind("$moduleId:config", fn (Application $app) => $app->make("$moduleId:module")->getConfig(''));
        $this->singleton("$moduleId:migration", fn (Application $app) => new MigrationFacade($app->make("$moduleId:module")));
        $this->singleton(
            "$moduleId:logger",
            fn (Application $app) => $app->make(ModuleLoggerFactory::class)->make($moduleId)
        );
    }

    /**
     * @param class-string|non-falsy-string $abstract
     * @param array<string, mixed> $parameters
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

    private static ?\ReflectionProperty $containerAliasesReflection = null;

    private static ?\ReflectionProperty $containerBindingsReflection = null;

    private function aliasesRegistry(): AliasRegistry
    {
        self::$containerAliasesReflection ??= (function (): \ReflectionProperty {
            $p = (new ReflectionClass(Container::class))->getProperty('aliases');
            $p->setAccessible(true);

            return $p;
        })();

        /** @var AliasRegistry */
        return self::$containerAliasesReflection->getValue($this);
    }

    private function bindingsRegistry(): BindingRegistry
    {
        self::$containerBindingsReflection ??= (function (): \ReflectionProperty {
            $p = (new ReflectionClass(Container::class))->getProperty('bindings');
            $p->setAccessible(true);

            return $p;
        })();

        /** @var BindingRegistry */
        return self::$containerBindingsReflection->getValue($this);
    }

    protected function resolveAliasName(string $abstract): string
    {
        return $this->aliasesRegistry()->resolve($abstract);
    }

    protected function getConcreteBinding(string $resolved): string|callable|null
    {
        return $this->bindingsRegistry()->getConcrete($resolved);
    }

    /**
     * Resolve a concrete class name for parameterized building.
     *
     * Fast path: if $abstract is already a class-string, avoid touching
     * parent container internals. Reflection fallback is used only for
     * alias/binding-backed ids.
     */
    protected function resolveParameterizedBuildClass(string $abstract): ?string
    {
        if (class_exists($abstract)) {
            return $abstract;
        }

        $resolved = $this->resolveAliasName($abstract);
        if (class_exists($resolved)) {
            return $resolved;
        }

        $concrete = $this->getConcreteBinding($resolved);
        if (is_string($concrete) && !is_callable($concrete) && class_exists($concrete)) {
            return $concrete;
        }

        return null;
    }

    /**
     * Create an instance with constructor parameters (bypasses parent singleton cache).
     */
    protected function buildWithParameters(string $abstract, array $parameters): object
    {
        $class = $this->resolveParameterizedBuildClass($abstract);

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
     * @param class-string|non-falsy-string $abstract
     * @param array<string, mixed> $parameters
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

    public static function hasInstance(): bool
    {
        return static::$instance !== null;
    }

    public static function getInstance(): Application
    {
        if (static::$instance === null) {
            throw new \RuntimeException('Application instance has not been set.');
        }

        return static::$instance;
    }

    public function container(string $moduleId): ModuleContainer
    {
        return new ModuleContainer($this, $moduleId);
    }

    /**
     * Register a callback fired each time a provider is registered.
     *
     * Callback dependencies are resolved via {@see self::call()} and may
     * type-hint both Application ($app) and ServiceProvider ($provider).
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
     * Determine if the application has completed boot().
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

        $this->booting = true;
        try {
            $this->bootOrchestrator->run(
                $this,
                $this->bootingCallbacks,
                $this->serviceProviders,
                $this->bootedCallbacks
            );
            $this->booted = true;
            $this->hasBeenBootstrapped = true;
        } finally {
            $this->booting = false;
        }
    }

    public function registerCoreContainerAliases(): void
    {
        foreach ([
            'app' => [self::class, Container::class, ContainerInterface::class],
            'asset' => [Asset::class],
            'config' => [ConfigRepositoryContract::class, ConfigArrayRepository::class],
            // No 'module' group: each module has its own "$moduleId:module" binding;
            // aliasing ModuleEntityContract::class → 'module' (with no canonical binding)
            // breaks compileAll (ReflectionClass('module')).
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
            $provider = $this->providerResolutionOrchestrator->resolveProvider($this, $provider);
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

        $this->providerResolutionOrchestrator->markAsRegistered(
            $this->serviceProviders,
            $this->loadedProviders,
            $provider
        );
        $this->fireRegisteredCallbacks($provider);

        if ($this->isBooted() || $this->booting) {
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
        return $this->deferredOrchestrator->registerDeferred($this, $this->deferredServices, $provider);
    }

    /**
     * Get the registered service provider instance if it exists.
     *
     * @param  ServiceProvider|string  $provider
     * @return ServiceProvider|null
     */
    public function getProvider($provider): ?ServiceProvider
    {
        return $this->providerResolutionOrchestrator->getProvider($this->serviceProviders, $provider);
    }

    /**
     * Get the registered service provider instances if any exist.
     *
     * @param  ServiceProvider|string  $provider
     * @return array
     */
    public function getProviders($provider): array
    {
        return $this->providerResolutionOrchestrator->getProviders($this->serviceProviders, $provider);
    }

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string  $provider
     * @return ServiceProvider
     */
    public function resolveProvider($provider): ServiceProvider
    {
        return $this->providerResolutionOrchestrator->resolveProvider($this, $provider);
    }

    /**
     * Load the deferred provider if the given type is a deferred service and the instance has not been loaded.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function loadDeferredProviderIfNeeded(string $abstract): void
    {
        $this->deferredOrchestrator->loadIfNeeded(
            $this,
            $this->deferredServices,
            $this->loadedProviders,
            $this->resolved,
            $abstract
        );
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

    protected function fireRegisteredCallbacks(ServiceProvider $provider): static
    {
        foreach ($this->registeredCallbacks as $callback) {
            $this->call($callback, ['provider' => $provider, 'app' => $this]);
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
     * Register a new "booted" listener.
     *
     * @param callable $callback
     * @return static
     */
    public function booted(callable $callback): static
    {
        $this->bootedCallbacks[] = $callback;
        return $this;
    }

    /**
     * Load and boot all of the remaining deferred providers.
     *
     * @return static
     */
    public function loadDeferredProviders(): static
    {
        $this->deferredOrchestrator->loadAll(
            $this,
            $this->deferredServices,
            $this->loadedProviders
        );
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
        $this->deferredOrchestrator->loadService(
            $this,
            $this->deferredServices,
            $this->loadedProviders,
            (string) $service
        );
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

        $this->registerResolvedProvider((string) $provider);
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
        return $this->deferredOrchestrator->isDeferredService($this->deferredServices, $service);
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

    public function resolveProviderClass(string $provider): ServiceProvider
    {
        return $this->providerResolutionOrchestrator->resolveProvider($this, $provider);
    }

    public function registerResolvedProvider(string $provider): void
    {
        $this->register(new $provider($this));
    }

    public function bootRegisteredProvider(ServiceProvider $provider): static
    {
        return $this->bootProvider($provider);
    }

    public function markBooted(): void
    {
        $this->booted = true;
    }
}
