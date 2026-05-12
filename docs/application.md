# Foundation Application

File: `src/Foundation/Application.php`  
Namespace: `MB\Bitrix\Foundation`

`Application` is the package kernel: DI container + provider lifecycle + Bitrix integration.

## Bootstrap Flow

```php
use MB\Bitrix\Foundation\Application;

$app = new Application();

$app
    ->setBasePath($_SERVER['DOCUMENT_ROOT'] . '/local')
    ->register(\App\Providers\AppServiceProvider::class)
    ->registerDeferred(\App\Providers\DeferredServiceProvider::class);

$app->boot();
```

## Lifecycle Events

Kernel-level events:

- `onBuildKernelApplication`
- `onBeforeBootKernelApplication`
- `onAfterBootKernelApplication`

## Orchestrators

Lifecycle internals are split into dedicated orchestrators:

- `BootOrchestrator` - boot callbacks, provider booting, before/after boot events.
- `DeferredProviderOrchestrator` - deferred map registration and lazy loading.
- `ProviderResolutionOrchestrator` - provider lookup, resolution, and registration bookkeeping.

`Application` remains the public entrypoint and delegates internal workflow to these components.

## Deferred Providers

`registerDeferred()` only stores service-to-provider mapping.

Provider registration happens when:

1. a deferred service is first resolved (`make()`),
2. or `loadDeferredProviders()` is called explicitly.

## make / makeWith

`makeWith()` is an alias to `make($abstract, $parameters)`.

For parameterized construction the kernel uses:

1. direct class-string resolution,
2. alias/binding resolution,
3. constructor argument binding from explicit parameters, container type-hints, and defaults.

## Stability Notes

- Constructor does not call eager `compile()`.
- `hasBeenBootstrapped()` indicates at least one successful `boot()` cycle.
- `isBooted()` indicates current booted state.
