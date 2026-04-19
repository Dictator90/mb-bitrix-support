## Миграции и менеджеры сущностей

Пакет `mb4it/bitrix-support` не навязывает конкретный формат файлов миграций, а предоставляет **базовую инфраструктуру** для описания операций над сущностями модуля:

### Фасад `MB\Bitrix\Migration\Facade`

- Контракт `Contracts\Migration\Facade` требует **`up()`** / **`down()`** — они делегируют в **`upAll()`** / **`downAll()`**.
- Фасад — это orchestration-слой для lifecycle модуля, а не версия миграций в стиле Laravel: по умолчанию он выполняет pipeline **file → storage → event** при `upAll()` и **event → storage → file** при `downAll()`.
- Каждый шаг возвращает собственный `MB\Bitrix\Migration\Result`; фасад складывает их в `Result::getData()` по ключам шага и одновременно агрегирует все ошибки в верхний `Result`.
- Ключ **`agent`** не входит в default pipeline намеренно. Агентов можно запускать отдельно через **`upAgents()`** / **`downAgents()`**, но для прод-кода по-прежнему предпочтителен прямой вызов `AgentManager`.
- Сущность **`Storage`** в `upAll`/`downAll` обходит классы модуля, наследующие `MB\Bitrix\Storage\Base`, и вызывает их `migrate()` / удаляет таблицы в `down` — опирается на `MB\Bitrix\Migration\Facades\Storage` и ядро Bitrix ORM.
- Все migration-сущности обязаны реализовывать контракт `MB\Bitrix\Contracts\Migration\Entity`; базовый `MB\Bitrix\Migration\BaseEntity` уже реализует контракт и задаёт единый shape для `check()/up()/down()`.
- Файловый шаг `MB\Bitrix\Migration\Entities\File` читает `ModuleEntity::getInstallConfig()` и трактует его как declarative install-manifest. Неизвестные action больше не игнорируются: шаг возвращает ошибку в `Result`, что делает install/uninstall предсказуемым для модуля.

### Тестирование

- `composer test` — быстрые unit-тесты пакета с минимальными Bitrix-stubs.
- `composer test:integration` — отдельный smoke-контур на реальных классах `Bitrix\Main\*` из `avshatalov48/bitrix-core-business`.
- По умолчанию integration bootstrap поднимает реальные D7-типы без полного `prolog_before.php`: этого достаточно для smoke-тестов migration-слоя и не требует поднятой БД.
- Полный `prolog_before.php` доступен как opt-in режим через `BITRIX_INTEGRATION_USE_PROLOG=1`; для него уже нужен рабочий Bitrix runtime с доступной MySQL/MariaDB.
- В integration bootstrap подавляются `E_DEPRECATED` от самого upstream-ядра Bitrix на PHP 8.4, чтобы smoke-тесты оставались сигналом о совместимости пакета, а не о legacy-сигнатурах в vendor-core.

Подробности изменений контракта при необходимости смотрите в **`CHANGELOG.md`** в корне репозитория.

---

- абстрактный менеджер `MB\Bitrix\Migration\BaseEntityManager`;
- конкретные менеджеры:
  - `MB\Bitrix\HighloadBlock\HighloadBlockManager` — для HL-блоков;
  - `MB\Bitrix\Agent\AgentManager` — для агентов;
  - `MB\Bitrix\Event\EventManager` — для обработчиков событий;
  - и другие, которые вы можете добавить по аналогии.

---

## Базовый менеджер `BaseEntityManager`

Файл: `src/Migration/BaseEntityManager.php`

```php
namespace MB\Bitrix\Migration;

use MB\Bitrix\Contracts\Module\Entity;

abstract class BaseEntityManager
{
    abstract public function getEntityClass(): string;
    abstract public function update(): Result;
    abstract public function deleteAll(): Result;

    public function __construct(protected Entity $module)
    {}

    public static function create(Entity $module): static
    {
        return new static($module);
    }
}
```

Ключевые идеи:

- менеджер всегда «привязан» к конкретному модулю Bitrix через контракт `MB\Bitrix\Contracts\Module\Entity` (он должен уметь вернуть ID модуля, неймспейс, путь к `lib` и т.п.);
- каждый конкретный менеджер:
  - знает, с какими **сущностями** он работает (например, с наследниками `HighloadBlock\Base` или `Agent\Base`);
  - умеет:
    - **`update()`** — привести систему к состоянию, описанному в коде;
    - **`deleteAll()`** — удалить все связанные сущности (для деинсталляции).

Фабричный метод `::create($module)` упрощает создание экземпляра в коде миграций/инсталляторов.

---

## Менеджер HL-блоков `HighloadBlockManager`

Подробно описан в разделе [`storage-and-highloadblock.md`](storage-and-highloadblock.md).

Кратко:

- наследует `BaseEntityManager`;
- `getEntityClass()` возвращает `MB\Bitrix\HighloadBlock\Base::class`;
- `update()`:
  - находит все классы, наследующие `HighloadBlock\Base`, через `Filesystem::classFinder()->extends()`;
  - у каждого вызывает `createTable()` или `refresh()` в зависимости от существования HL-блока;
- `deleteAll()` — удаляет все HL-блоки модуля;
- дополнительные удобные методы `createFor()`/`dropFor()` для работы с одним конкретным классом.

---

## Менеджер агентов `AgentManager`

Файл: `src/Agent/AgentManager.php`  
Базовый класс агентов: `src/Agent/Base.php`

### Базовый класс агента `Agent\Base`

`Agent\Base` описывает **один или несколько агентов** модуля:

- статический метод `getAgents(): array` должен вернуть одно или несколько описаний агентов;
- каждое описание содержит:

```php
[
  'method'    => string,      // имя статического метода класса-агента (по умолчанию run)
  'arguments' => array|null,  // позиционные аргументы
  'interval'  => int,         // интервал запуска (секунды)
  'sort'      => int,         // сортировка
  'next_exec' => string,      // дата следующего запуска Y-m-d H:i:s (опционально)
  'update'    => string,      // правило обновления NEXT_EXEC
  'search'    => string,      // правило поиска существующих агентов
]
```

Также класс содержит:

- `getClassName()` — полное имя класса с ведущим `\`;
- `isRegistered(Entity $moduleEntity, $agentParams = null)` — проверка регистрации;
- `register(Entity $moduleEntity, $agentParams = null)` — регистрация через `AgentManager`;
- `unregister(Entity $moduleEntity, ?array $agentParams = null)` — удаление;
- `callAgent(string $method, ?array $arguments = null): ?string` — обёртка для вызова из `CAgent`;
- `run()` — метод агента по умолчанию.

### Менеджер `AgentManager`

`AgentManager` наследует `BaseEntityManager` и решает две задачи:

- синхронизация списка агентов в БД с декларациями в коде (`update()`/`syncAll()`);
- регистрация/удаление/поиск конкретного агента.

Основные методы:

- `getEntityClass(): string` — возвращает `Agent\Base::class`;
- `update(): Result`:
  - через `Filesystem::classFinder()->extends()` находит все классы, наследующие `Agent\Base` в модуле;
  - строит актуальный список агентов (`getClassAgents()`);
  - сравнивает его с зарегистрированными агентами `CAgent::GetList` и:
    - добавляет/обновляет недостающие (`saveAgents()`/`saveAgent()`);
    - удаляет лишние (`deleteAgents()`/`deleteAgent()`).

- `deleteAll(): Result` — удаляет все агенты модуля;
- `register(string $className, ?array $agentParams): void` — регистрирует один агент;
- `unregister($className, $agentParams): void` — удаляет один/несколько агентов по правилам поиска.

**Использование в миграции/инсталляторе:**

```php
use MB\Bitrix\Agent\AgentManager;

// Внутри шага установки/обновления модуля
$manager = AgentManager::create($moduleEntity);
$result = $manager->syncAll(); // или update()
```

---

## Менеджер обработчиков событий `EventManager`

Файлы:

- `src/Event/Base.php` — базовый класс описателя обработчиков;
- `src/Event/EventManager.php` — менеджер регистрации/синхронизации.

### Базовый класс `Event\Base`

`Event\Base` позволяет декларативно описать обработчики событий:

- статический метод `getHandlers(): array` возвращает одно или несколько описаний вида:

```php
[
  'module'    => string,       // модуль-источник события (FROM_MODULE_ID)
  'event'     => string,       // код события (MESSAGE_ID)
  'method'    => string|null,  // метод класса-обработчика (по умолчанию совпадает с event)
  'sort'      => int,          // сортировка (по умолчанию 100)
  'arguments' => array|string, // дополнительные аргументы (опционально)
]
```

Также есть:

- `getClassName()` — полное имя класса;
- `register(Entity $moduleEntity, ?array $handlerParams = null): Result` — зарегистрировать один обработчик через `EventManager`;
- `unregister(Entity $moduleEntity, $handlerParams = null): void` — удалить;
- `getDefaultParams(): array` — параметры по умолчанию.

### Менеджер `EventManager`

`EventManager` наследует `BaseEntityManager` и работает с таблицей `b_module_to_module` через `Bitrix\Main\EventManager`.

Основные задачи:

- **`syncAll()` / `update()`**:
  - находит через `Filesystem::classFinder()->extends()` все классы, наследующие `Event\Base`;
  - для каждого класса получает описания обработчиков (`getHandlers()`);
  - сравнивает их с уже зарегистрированными (`getRegisteredHandlers()`), исходя из неймспейса модуля;
  - добавляет недостающие (`saveHandler()`/`saveHandlers()`), удаляет лишние (`deleteHandler()`/`deleteHandlers()`).

- **`register(string $className, array $handlerParams): void`**
  - проверяет, что указаны `module` и `event`;
  - определяет метод-обработчик (по умолчанию равно `event`);
  - проверяет существование метода в классе;
  - регистрирует обработчик через `Bitrix\Main\EventManager::registerEventHandler()`.

- **`unregister(string $className, array $handlerParams): void`**
  - строит ключ обработчика и удаляет его из `b_module_to_module`.

**Использование:**

```php
use MB\Bitrix\Event\EventManager;

$manager = EventManager::create($moduleEntity);
$result = $manager->syncAll(); // или update()
```

---

## Рекомендации по организации миграций

1. **Описывайте структуру в коде, а не в SQL.**  
   Для HL-блоков, агентов и событий структура полностью описывается в PHP (наследники `HighloadBlock\Base`, `Agent\Base`, `Event\Base`).

2. **В миграциях вызывайте менеджеры.**  
   Вместо того чтобы напрямую вызывать `CUserTypeEntity`, `CAgent`, `EventManager::registerEventHandler` и т.п., используйте:

   - `HighloadBlockManager::update()` / `createFor()` / `dropFor()`;
   - `AgentManager::syncAll()` / `register()` / `unregister()` / `deleteAll()`;
   - `EventManager::syncAll()` / `register()` / `unregister()` / `deleteAll()`.

3. **Используйте `Result` для обработки ошибок.**  
   Большинство методов менеджеров возвращают `MB\Bitrix\Migration\Result`, который:

   - накапливает исключения и ошибки Bitrix;
   - позволяет в конце миграции одним блоком проверить `isSuccess()` и вывести/залoгировать все сообщения.

4. **Делайте миграции идемпотентными.**  
   Повторный запуск `update()`/`syncAll()` не должен ломать систему:

   - при существующих сущностях менеджеры выполняют «refresh» (обновление полей, интервалов и т.д.);
   - при их отсутствии — создают заново.

Используя данный слой, вы получаете централизованный, декларативный способ управлять инфраструктурой модуля Bitrix в стиле миграций.  
Конкретный формат хранения миграций (файлы, таблицы, консольные команды) вы выбираете самостоятельно либо интегрируете существующую систему миграций с предоставленными менеджерами.

