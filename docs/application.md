# Ядро приложения `Foundation\Application`

Файл: `src/Foundation/Application.php`  
Пространство имен: `MB\Bitrix\Foundation`

`Application` - это ядро пакета: контейнер зависимостей + жизненный цикл провайдеров + интеграция с Bitrix.

## Что делает `Application`

- наследует `MB\Container\Container`;
- регистрирует базовые биндинги (`app`, `config`, пути `path.*`);
- подключает базовые провайдеры пакета;
- поддерживает deferred-провайдеры;
- управляет `boot`-циклом приложения;
- публикует события ядра (`onBuildKernelApplication`, `onBeforeBootKernelApplication`, `onAfterBootKernelApplication`).

## Инициализация

```php
use MB\Bitrix\Foundation\Application;

$app = new Application();

$app
    ->setBasePath($_SERVER['DOCUMENT_ROOT'] . '/local') // опционально
    ->register(\App\Providers\AppServiceProvider::class)
    ->registerDeferred(\App\Providers\DeferredServiceProvider::class);

$app->boot();
```

Важно: в конструкторе **нет eager `compile()`**.  
Контейнер работает в lazy-режиме; при необходимости предкомпиляцию вызывают отдельно (`compile()` / `compileToFile()` на уровне приложения).

## Жизненный цикл и флаги

### Конструктор

При `new Application()` выполняются:

1. регистрация событий ядра;
2. базовые биндинги и path-сервисы;
3. регистрация базовых провайдеров;
4. регистрация container aliases;
5. событие `onBuildKernelApplication`.

### Boot

`boot()` выполняется один раз:

1. событие `onBeforeBootKernelApplication`;
2. `booting` callbacks приложения;
3. `boot()` у зарегистрированных провайдеров;
4. `booted` callbacks приложения;
5. событие `onAfterBootKernelApplication`.

После успешного `boot()`:

- `isBooted() === true`;
- `hasBeenBootstrapped() === true` (флаг означает, что `boot()` уже завершался хотя бы один раз).

## Провайдеры

### Обычные

`register()`:

- создает/принимает экземпляр провайдера;
- вызывает `register()`;
- применяет `$bindings` и `$singletons` из провайдера;
- помечает провайдер как зарегистрированный;
- вызывает callbacks из `registered(...)`;
- если приложение уже booted - сразу вызывает `boot()` провайдера.

`registered(callable $callback)` вызывается на каждый успешный `register()`.  
Зависимости callback резолвятся через `Application::call()`, можно type-hint:

- `MB\Bitrix\Foundation\Application $app`
- `MB\Bitrix\Foundation\ServiceProvider $provider`

### Deferred

`registerDeferred()` только наполняет карту `serviceId -> ProviderClass`.

Провайдер регистрируется лениво:

- при `make($serviceId)` через `loadDeferredProviderIfNeeded()`;
- либо принудительно через `loadDeferredProviders()`.

## Разрешение сервисов и `makeWith`

`make(string $abstract, array $parameters = [])`:

- для обычного резолва использует родительский контейнер;
- для непустых параметров идет в `buildWithParameters()` (поведение в стиле `makeWith`).

`makeWith(...)` - alias к `make(..., $parameters)`.

В `buildWithParameters()` используется стратегия:

1. быстрый путь: если `$abstract` уже class-string, используется он;
2. fallback: alias/binding lookup в контейнере;
3. сбор аргументов конструктора из:
   - `$parameters` по имени,
   - type-hint зависимостей из контейнера,
   - значений по умолчанию.

## Риск и план снижения зависимости от reflection

Сейчас fallback для alias/binding lookup опирается на reflection к внутренним registry родительского контейнера.  
Это оставлено для обратной совместимости с текущим `mb4it/container`, но рекомендуется следующий план:

1. добавить в `mb4it/container` публичный API для чтения alias/concrete metadata;
2. перевести `Application` на этот API;
3. удалить reflection fallback после выравнивания минимальной версии зависимости.

До этого момента любые изменения в internals `mb4it/container` должны сопровождаться тестами `Application`.

## Module-сценарий

`registerModule($moduleId)` регистрирует:

- `$moduleId:module`
- `$moduleId:config`
- `$moduleId:migration`
- `$moduleId:logger`

и позволяет использовать `module('vendor.name')` и `app()->container('vendor.name')`.
