## Агенты и события

Пакет `mb/bitrix-support` предлагает декларативный способ описания:

- агентов Bitrix (таблица `b_agent`, запуск по cron/агентам);
- обработчиков событий (таблица `b_module_to_module`, `Bitrix\Main\EventManager`),

и их синхронизацию через менеджеры миграций.

---

## Агенты

Работа с агентами строится вокруг двух классов:

- `MB\Bitrix\Agent\Base` — базовый класс для описания агентов;
- `MB\Bitrix\Agent\AgentManager` — менеджер регистрации и синхронизации.

### Базовый класс агента `Agent\Base`

Файл: `src/Agent/Base.php`

Идея: каждый конкретный агент **декларируется в коде** как наследник `Base`, а не настраивается вручную через админку.

Ключевые элементы:

- **`abstract public static function getAgents(): array;`**
  - должен вернуть одно или несколько описаний агентов (AgentDefinition);
  - допустимо вернуть:
    - один ассоциативный массив;
    - массив таких массивов.

- Формат AgentDefinition:

```php
[
  'method'    => string,      // имя статического метода (по умолчанию run)
  'arguments' => array|null,  // аргументы вызова (опционально)
  'interval'  => int,         // интервал в секундах (по умолчанию 86400)
  'sort'      => int,         // сортировка (по умолчанию 100)
  'next_exec' => string,      // дата следующего запуска Y-m-d H:i:s (опционально)
  'update'    => string,      // правило обновления NEXT_EXEC
  'search'    => string,      // правило поиска зарегистрированных агентов
]
```

- **`getClassName()`** — возвращает полное имя класса: `\Namespace\Class`.
- **`isRegistered(Entity $moduleEntity, $agentParams = null)`** — проверяет, есть ли такой агент.
- **`register(Entity $moduleEntity, $agentParams = null)`** — регистрирует агент через `AgentManager`.
- **`unregister(Entity $moduleEntity, ?array $agentParams = null)`** — удаляет агент.
- **`callAgent(string $method, ?array $arguments = null): ?string`**
  - используется как «обёртка» для вызова из `CAgent`;
  - в зависимости от возвращаемого значения метода агента:
    - `false` — агент удаляется;
    - `array` — используется как новые аргументы вызова;
    - любое другое значение — агент переустанавливается со старыми аргументами.

- **`getDefaultParams()`** — параметры по умолчанию для агента (переопределяются при необходимости).
- **`run()`** — дефолтный метод агента (должен вернуть `false|array|mixed`).

#### Пример описания агента

```php
use MB\Bitrix\Agent\Base as AgentBase;

class SyncAgent extends AgentBase
{
    public static function getAgents(): array
    {
        return [
            [
                'method'   => 'run',
                'interval' => 3600, // раз в час
                'sort'     => 100,
            ],
        ];
    }

    public static function run()
    {
        // бизнес-логика
        // ...

        return true; // вернуть любые данные, чтобы агент остался
        // или false, чтобы удалить агент после выполнения
    }
}
```

### Менеджер агентов `AgentManager`

Файл: `src/Agent/AgentManager.php`

Наследует `MB\Bitrix\Migration\BaseEntityManager` и реализует интеграцию с `CAgent`.

Основные константы:

- правила обновления `NEXT_EXEC`:
  - `UPDATE_RULE_STRICT`, `UPDATE_RULE_FUTURE`;
- правила поиска:
  - `SEARCH_RULE_STRICT`, `SEARCH_RULE_SOFT`.

Основные методы:

- **`getEntityClass(): string`** — возвращает `Base::class`;
- **`syncAll(): Result` / `update(): Result`**
  - через `ClassFinder::findExtended()` находит все классы модуля, наследующие `Agent\Base`;
  - извлекает из них описания агентов (`getClassAgents()`);
  - получает все зарегистрированные агенты модуля (`getRegisteredAgents(true)`), фильтруя по `MODULE_ID` и имени;
  - вызывает `saveAgents()` и `deleteAgents()` для приведения состояния в БД к описаниям в коде.

- **`deleteAll(): Result`**
  - удаляет все агенты, зарегистрированные модулем.

- **`register(string $className, ?array $agentParams): void`**
  - формирует описание агента (`getAgentDescription()`), проверяя существование метода;
  - регистрирует его (либо создаёт, либо обновляет, если найден по правилу `search`).

- **`unregister($className, $agentParams): void`**
  - находит и удаляет один или несколько агентов по описанию и правилу поиска.

**Использование в установке/обновлении:**

```php
use MB\Bitrix\Agent\AgentManager;

// $moduleEntity реализует MB\Bitrix\Contracts\Module\Entity
$manager = AgentManager::create($moduleEntity);
$result = $manager->syncAll(); // или update()

if (!$result->isSuccess()) {
    // обработать ошибки
}
```

---

## Обработчики событий

Для работы с обработчиками событий используются:

- `MB\Bitrix\Event\Base` — декларация обработчиков;
- `MB\Bitrix\Event\EventManager` — регистрация и синхронизация через `Bitrix\Main\EventManager` и таблицу `b_module_to_module`.

### Базовый класс `Event\Base`

Файл: `src/Event/Base.php`

Класс задаёт контракт:

- **`abstract public static function getHandlers(): array;`**
  - возвращает один или несколько описаний обработчиков (EventHandlerDefinition).

Формат EventHandlerDefinition:

```php
[
  'module'    => string,       // FROM_MODULE_ID — модуль-источник события
  'event'     => string,       // MESSAGE_ID — код события
  'method'    => string|null,  // метод класса-обработчика (по умолчанию совпадает с event)
  'sort'      => int,          // сортировка (по умолчанию 100)
  'arguments' => array|string, // дополнительные аргументы (опционально)
]
```

Вспомогательные методы:

- `getClassName()` — полное имя класса;
- `register(Entity $moduleEntity, ?array $handlerParams = null): Result`
  - объединяет `$handlerParams` с `getDefaultParams()`;
  - делегирует регистрацию в `EventManager::register()`;
  - оборачивает вызов в `Result`, перехватывая исключения.
- `unregister(Entity $moduleEntity, $handlerParams = null): void`
  - объединяет с `getDefaultParams()` и делегирует в `EventManager::unregister()`;
- `getDefaultParams(): array` — параметры по умолчанию (могут быть переопределены).

#### Пример описания обработчика

```php
use MB\Bitrix\Event\Base as EventBase;

class UserHandlers extends EventBase
{
    public static function getHandlers(): array
    {
        return [
            [
                'module' => 'main',
                'event'  => 'OnBeforeUserUpdate',
                'method' => 'onBeforeUserUpdate',
                'sort'   => 100,
            ],
        ];
    }

    public static function onBeforeUserUpdate(&$fields)
    {
        // ваша логика
    }
}
```

### Менеджер `EventManager`

Файл: `src/Event/EventManager.php`

Наследует `BaseEntityManager` и использует `Bitrix\Main\EventManager` + прямые запросы к `b_module_to_module`.

Основные элементы:

- в конструкторе инициализируется `Main\EventManager::getInstance()`;
- **`getEntityClass(): string`** — возвращает `Event\Base::class`;
- **`syncAll(): Result` / `update(): Result`**
  - через `ClassFinder::findExtended()` находит все классы‑наследники `Event\Base` в модуле;
  - для каждого класса получает список обработчиков через `getHandlers()` (`getClassHandlers()`);
  - формирует ключи и сравнивает с уже зарегистрированными обработчиками (`getRegisteredHandlers()`):
    - добавляет недостающие (`saveHandlers()`/`saveHandler()`);
    - удаляет лишние (`deleteHandlers()`/`deleteHandler()`).

- **`register(string $className, array $handlerParams): void`**
  - использует `getHandlerDescription()`:
    - проверяет наличие `module` и `event`;
    - выводит имя метода (`method` или `event`);
    - проверяет существование метода в классе;
  - регистрирует обработчик через `Main\EventManager::registerEventHandler()` / `registerEventHandlerCompatible()` (см. реализацию).

- **`unregister(string $className, array $handlerParams): void`**
  - получает описание обработчика;
  - извлекает из БД все зарегистрированные обработчики для модуля/класса (`getRegisteredHandlers()`).

- **`deleteAll(): Result`**
  - удаляет все обработчики, привязанные к неймспейсу модуля.

**Использование:**

```php
use MB\Bitrix\Event\EventManager;

$manager = EventManager::create($moduleEntity);
$result = $manager->syncAll();

if (!$result->isSuccess()) {
    // обработать ошибки
}
```

---

## Рекомендации по работе с агентами и событиями

- **Описывайте агентов и обработчики в коде.**  
  Избегайте ручного создания агентов/событий через админку: при деплое на другие окружения они не появятся автоматически.

- **Используйте менеджеры в миграциях и установщике модуля.**  
  В `install.php`/миграциях вызывайте:

  - `AgentManager::create($moduleEntity)->syncAll();`
  - `EventManager::create($moduleEntity)->syncAll();`

  Это обеспечит одинаковый набор агентов и обработчиков на всех окружениях.

- **Следите за неймспейсами.**  
  Менеджеры ищут наследников по:
  - пути `lib` модуля (`$module->getLibPath()`);
  - базовому неймспейсу (`$module->getNamespace()`).

- **Используйте `Result` для обработки ошибок.**  
  Все методы `update()/syncAll()/deleteAll()` возвращают `MB\Bitrix\Migration\Result`, который накапливает ошибки и исключения.

Слой агентов и событий, предоставляемый пакетом, позволяет держать всю регистрацию фоновых задач и обработчиков событий под контролем версий и запускать синхронизацию в рамках миграций на любом окружении.

