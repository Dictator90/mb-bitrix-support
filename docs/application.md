## Ядро приложения `Foundation\Application`

Файл: `src/Foundation/Application.php`  
Пространство имён: `MB\Bitrix\Foundation`

Класс `Application` — это **ядро mb-bitrix-support**, объединяющее:

- DI‑контейнер (`MB\Container\Container`);
- интеграцию с Bitrix (`CMain`, `Bitrix\Main\Application`, пути проекта);
- систему сервис‑провайдеров и отложенных (deferred) сервисов;
- события жизненного цикла ядра.

На практике `Application` выступает в роли **единой точки входа** для сервисов пакета и модулей Bitrix.

---

## Жизненный цикл `Application`

Рекомендуемая последовательность инициализации:

```php
use MB\Bitrix\Foundation\Application;

$app = new Application();

$app
    ->setBasePath($_SERVER['DOCUMENT_ROOT'] . '/local') // опционально
    // регистрация собственных провайдеров
    ->register(\App\Providers\AppServiceProvider::class)
    ->registerDeferred(\App\Providers\DeferredServiceProvider::class);

// загрузка всех оставшихся отложенных провайдеров (по необходимости)
// $app->loadDeferredProviders();

$app->boot();
```

Ключевые этапы:

- **Конструктор**:
  - регистрирует события ядра (`registerEvents()`),
  - настраивает базовые биндинги (`registerBaseBindings()`),
  - регистрирует базовые провайдеры (`registerBaseServiceProviders()`),
  - настраивает алиасы контейнера (`registerCoreContainerAliases()`),
  - компилирует контейнер (`compile()`),
  - отправляет событие `ON_BUILD_KERNEL_APPLICATION_EVENT`.
- **Конфигурация путей** (`setBasePath()`):
  - задаёт базовый путь проекта;
  - через `bindPathsInContainer()` регистрирует сервисы:
    - `path.local` — `DOCUMENT_ROOT . '/local'`,
    - `path.bitrix` — `DOCUMENT_ROOT . '/bitrix'`,
    - `path.template` — `SITE_TEMPLATE_PATH` (если определён),
    - `path.front` — путь к фронтенду из опции `kernel.options.front_path`.
- **Регистрация провайдеров** (`register()` / `registerDeferred()`):
  - обычные провайдеры конфигурируют контейнер сразу;
  - отложенные провайдеры регистрируются только в карте `$deferredServices`.
- **Запуск** (`boot()`):
  - отправляет событие `ON_BEFORE_BOOT_KERNEL_APPLICATION_EVENT`;
  - выполняет boot‑колбэки приложения;
  - вызывает `boot()` у всех зарегистрированных провайдеров и их boot‑колбэки;
  - выполняет booted‑колбэки приложения;
  - отправляет событие `ON_AFTER_BOOT_KERNEL_APPLICATION_EVENT`;
  - устанавливает флаг `$hasBeenBootstrapped = true`.

Флаг `hasBeenBootstrapped()` позволяет понять, инициализировалось ли ядро хотя бы один раз.

---

## События жизненного цикла ядра

`Application` использует `BitrixEventsObservableTrait` и генерирует три основных события в области `mb.core`:

- **`ON_BUILD_KERNEL_APPLICATION_EVENT`**  
  Отправляется в конструкторе (`attachEvents()`), когда:
  - базовые биндинги и провайдеры уже зарегистрированы,
  - но `boot()` ещё не вызывался.

- **`ON_BEFORE_BOOT_KERNEL_APPLICATION_EVENT`**  
  Отправляется в начале `boot()` — до boot‑колбэков и вызова `boot()` у провайдеров.

- **`ON_AFTER_BOOT_KERNEL_APPLICATION_EVENT`**  
  Отправляется в конце `boot()` — после того как:
  - все провайдеры загружены и отбутованы,
  - выполнены booted‑колбэки приложения.

Подписчики получают в payload ключ `app` с экземпляром `Application`, что позволяет:

- регистрировать дополнительные сервисы;
- модифицировать конфигурацию контейнера;
- выполнять интеграционные действия на разных этапах загрузки.

---

## Интеграция с Bitrix

В `registerBaseBindings()` настраиваются ключевые биндинги:

- `app` → экземпляр `Application` (singleton);
- `asset` → `MB\Bitrix\Page\Asset` (через синглтон `Asset::getInstance()`);
- `filesystem` → `MB\Bitrix\Filesystem` (`MB\Filesystem\Contracts\Filesystem`);
- `module` → `MB\Bitrix\Module\Entity` (`MB\Bitrix\Contracts\Module\Entity`);
- миграции: alias `migration.facade` → `MB\Bitrix\Migration\Facade`;
- Bitrix‑объекты:
  - `bitrix.cmain` → глобальный `$APPLICATION`;
  - `bitrix.application` → `Bitrix\Main\Application::getInstance()`;
  - `bitrix.context`, `bitrix.request`, `bitrix.cache` — обёртки вокруг текущего контекста и кеша.

Дополнительно есть метод:

- **`registerModule(string $moduleId): void`**
  - регистрирует:
    - `$moduleId:module` — сущность модуля (`ModuleEntityContract`);
    - `$moduleId:config` — конфигурацию модуля;
    - `$moduleId:migration` — фасад миграций для модуля.

Это упрощает работу модулей Bitrix, позволяя получать их сервисы напрямую из контейнера.

---

## Провайдеры и отложенные сервисы

`Application` использует абстрактный `Foundation\ServiceProvider` для конфигурации контейнера.

### Обычная регистрация

```php
$app->register(\App\Providers\AppServiceProvider::class);
```

- провайдер создаётся (`resolveProvider()`),
- вызывается его `register()`,
- применяются массивы `$bindings` и `$singletons` (если объявлены),
- при необходимости вызывается `boot()` и boot‑колбэки (если приложение уже booted).

### Отложенная (deferred) регистрация

Для ленивых сервисов используется связка:

- метод провайдера `provides(): array<int, string>` — список id сервисов, которые он предоставляет;
- метод приложения `registerDeferred(ServiceProvider|string $provider)`:
  - не вызывает `register()` немедленно;
  - заполняет карту `$deferredServices` вида `['serviceId' => ProviderClass::class]`.

Когда из контейнера запрашивается сервис:

- `make()`/`get()` вызывают `loadDeferredProviderIfNeeded($abstract)`;
- если `$abstract` есть в `$deferredServices` и ещё не был разрешён, вызывается `loadDeferredProvider()`:
  - создаёт экземпляр провайдера;
  - регистрирует его через `registerDeferredProvider()` и при необходимости планирует `boot()` на этапе `boot()`.

Это позволяет **не создавать тяжёлые сервисы**, пока они реально не понадобятся.

---

## `Application` как фасад контейнера

Помимо стандартных операций контейнера (`bind`, `singleton`, `instance`, `make`, `get`) `Application` добавляет:

- **`makeWith(string $abstract, array $parameters = []): mixed`**  
  Создаёт объект с явной подстановкой аргументов конструктора.  
  Внутри используется `buildWithParameters()`, который:
  - учитывает параметры по имени;
  - подставляет зависимости из контейнера по типам;
  - использует значения по умолчанию, если они определены.

- **`call(callable $callable, array $parameters = []): mixed`**  
  Вызывает любую функцию/метод с автоподстановкой аргументов из контейнера по типам и именам параметров.

- **`resolved(string $abstract): bool`**  
  Позволяет проверить, был ли данный id уже разрешён (используется, в частности, в `ServiceProvider::callAfterResolving()`).

Рекомендуется:

- использовать `make()`/`get()` для обычных сервисов;
- использовать `makeWith()` только там, где действительно нужны явные аргументы конструктора (например, сущности модулей/миграций);
- использовать `call()` для колбэков, которые должны автоматически получать зависимости из контейнера.

---

## Рекомендации по использованию

- Создавайте **один экземпляр `Application` на запрос** и храните его как глобальное ядро (через `Application::setInstance()` / `getInstance()` или собственный bootstrap).
- Регистрируйте все инфраструктурные сервисы и провайдеры в одном месте (bootstrap‑файл/модуль), а в остальном коде запрашивайте только готовые зависимости из контейнера.
- Для тяжёлых или редко используемых сервисов:
  - выносите их в отдельные `ServiceProvider`;
  - реализуйте `provides()` и регистрируйте через `registerDeferred()`.
- Используйте события жизненного цикла (`ON_BUILD_KERNEL_APPLICATION_EVENT`, `ON_BEFORE_BOOT_KERNEL_APPLICATION_EVENT`, `ON_AFTER_BOOT_KERNEL_APPLICATION_EVENT`) для интеграции с Bitrix и сторонними модулями без жёстких связей с реализацией `Application`.

