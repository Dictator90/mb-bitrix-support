# KODA.md — Контекст проекта mb4it/bitrix-support

## Обзор проекта

**mb4it/bitrix-support** — это PHP-библиотека (Composer-пакет) для проектов на базе **1C-Bitrix**, предоставляющая вспомогательные классы и абстракции для:

- работы с **ORM и Highload-блоками** (декларативное описание, синхронизация с БД);
- **миграций** и менеджеров сущностей (агенты, события, файлы);
- **файлов и изображений** (сохранение, дедупликация, превью);
- **логирования** (PSR-3, уведомления, журнал событий);
- **компонентов Bitrix** (базовые классы, параметры);
- **административного UI** (EntityView, настройки, контролы).

**Технологический стек:**

| Компонент | Версия / Описание |
|-----------|-------------------|
| **PHP** | `^8.2` |
| **Bitrix** | D7 ORM, модуль `main`, опционально `highloadblock`, `ui` |
| **СУБД** | MySQL (с `ROW()` в `UPDATE JOIN`), PostgreSQL (`ON CONFLICT`) |
| **DI-контейнер** | `mb4it/container` (кастомный, **не** Laravel Illuminate) |
| **Зависимости** | `mb4it/*` (stringable, collections, conditionable, filesystem, logger), `spatie/image`, `psr/log` |

**Пространство имён:** `MB\Bitrix\` (основное публичное API).

---

## Сборка и запуск

### Установка зависимостей

```bash
composer install
```

### Команды разработки

| Команда | Описание |
|---------|----------|
| `composer lint` | Проверка синтаксиса PHP (`scripts/dev/lint.php`) |
| `composer phpstan` | Статический анализ (PHPStan уровень 5, частичное покрытие) |
| `composer test` | Unit-тесты (PHPUnit, suite `default`) |
| `composer test:sqlite` | Тесты с SQLite-базой |
| `composer test:integration` | Integration-тесты на реальном Bitrix |

### Структура тестов

- **Unit:** `tests/` — тесты отдельных компонентов (Config, Logger, Migration, Storage, Support)
- **Integration:** `tests/Integration/` — smoke-тесты с реальными классами Bitrix
- **Bootstrap:** `tests/bootstrap.php` (unit), `tests/integration-bootstrap.php` (integration)

---

## Архитектура и структура

### Основные подсистемы

```
src/
├── Foundation/          # Ядро приложения (Application, ServiceProvider)
├── Support/             # Глобальные хелперы, фасады, контракты
├── Module/              # Сущность модуля, контейнер
├── Migration/           # Миграции, менеджеры сущностей
├── Storage/             # ORM-обёртки, Query, SqlHelper (MySQL/PgSQL)
├── HighloadBlock/       # HL-блоки (Base, HighloadBlockManager, Fields)
├── Config/              # Конфигурация (ArrayRepository, ConfigLocator, Entity)
├── File/                # Файлы (FileService, Image)
├── Filesystem/          # Файловая система (Filesystem, ServiceProvider)
├── Logger/              # Логирование (LoggerFactory, UniversalLogger)
├── Agent/               # Агенты Bitrix (AgentManager, Base)
├── Event/               # События (EventManager, Base)
├── Component/           # Компоненты Bitrix (BaseComponent, Parameters)
├── EntityView/          # Административный UI (Builder, Grid, Form, Filter)
├── UI/                  # Контролы, админ-страницы, EntitySelector
├── Settings/            # Настройки модуля (Admin, Options, Controller)
├── Page/                # Страницы, ассеты (Asset, Common, Includer)
├── Iblock/              # Инфоблоки (Helper, DetailUrl, UserType)
├── Contracts/           # Интерфейсы (Module, Config, Storage, Migration...)
├── Traits/              # Трейты (Cacheable, FluentTrait, Observable...)
└── Database/            # SQLite-поддержка для тестов
```

### Ядро приложения: `Foundation\Application`

`Application` — это DI-контейнер + жизненный цикл провайдеров:

- Наследует `MB\Container\Container`
- Регистрирует базовые провайдеры (Asset, Filesystem, File, Migration, Logger, Module)
- Поддерживает **deferred-провайдеры** (ленивая загрузка)
- Управляет событиями ядра:
  - `onBuildKernelApplication`
  - `onBeforeBootKernelApplication`
  - `onAfterBootKernelApplication`

**Bootstrap:**

```php
use MB\Bitrix\Foundation\Application;

$app = new Application();
$app->setBasePath($_SERVER['DOCUMENT_ROOT'] . '/local'); // опционально
$app->register(MyServiceProvider::class);
$app->boot();
```

**Глобальные хелперы** (после bootstrap):

- `app(string $abstract = null)` — получение из контейнера
- `config(string $key = null, mixed $default = null)` — конфигурация
- `module(string $id)` — сущность модуля

---

## Ключевые классы и контракты

### Стабильные точки входа

| Класс / Функция | Описание |
|-----------------|----------|
| `MB\Bitrix\Foundation\Application` | Ядро приложения, контейнер |
| `app()`, `config()`, `module()` | Глобальные хелперы (`src/Support/helpers.php`) |
| `MB\Bitrix\Contracts\*` | Интерфейсы для всех подсистем |
| `MB\Bitrix\Support\Facades\*` | Фасады (Config, Module, Logger...) |

### Подсистема Storage / ORM

- **`Storage\Entity`** — обёртка над `Bitrix\Main\ORM\Entity`
- **`Storage\EntityObject`** — объект сущности
- **`Storage\Collection`** — коллекция объектов
- **`Storage\Query`** — расширенный query-builder (where*, having*, with*)
- **`Storage\SqlHelper`** — кросс-СУБД SQL (MySQL `ROW()`, PostgreSQL `ON CONFLICT`)
- **Трейты:** `BatchUpsertTrait`, `UpdateByWhereTrait`, `MassUpdateTrait`, `DeleteByQueryTrait`, `BuildIndexes`

### Подсистема HighloadBlock

- **`HighloadBlock\Base`** — базовый описатель HL-блока (абстрактный класс)
- **`HighloadBlock\HighloadBlockManager`** — менеджер миграций для HL-блоков
- **`HighloadBlock\Fields\*`** — типы полей (StringField, IntegerField...)

**Пример объявления HL-блока:**

```php
use MB\Bitrix\HighloadBlock\Base;
use MB\Bitrix\HighloadBlock\Fields\StringField;

class MyHlBlock extends Base
{
    public static function getTableName(): string
    {
        return 'my_hl_table';
    }

    public static function getName(): string
    {
        return 'MyHlBlock';
    }

    public static function getLang(): array
    {
        return ['ru' => 'Мой HL-блок'];
    }

    public static function getMap(): array
    {
        return [
            new StringField('UF_NAME', 'Название'),
            // другие поля...
        ];
    }
}
```

### Подсистема Migration

- **`Migration\Facade`** — фасад для миграций (`up()`, `down()`, `upAll()`, `downAll()`)
- **`Migration\BaseEntity`** — базовая сущность миграции
- **`Migration\BaseEntityManager`** — базовый менеджер
- **Менеджеры:** `HighloadBlockManager`, `AgentManager`, `EventManager`, `StorageEntityManager`

### Подсистема File

- **`File\FileService`** — сервис работы с файлами (сохранение, дедупликация, кэш)
- **Контракт:** `Contracts\File\FileServiceContract`
- **Image:** `File\Image\*` — обработка изображений через `spatie/image`

**Пример сохранения файла:**

```php
use MB\Bitrix\Contracts\File\FileServiceContract;

$fileData = $_FILES['PHOTO'] ?? null;
if ($fileData) {
    $files = app('file.service'); // или app(FileServiceContract::class)
    $fileId = $files->save($fileData, 'my_module/photos');
}
```

### Подсистема Config

- **`Config\ArrayRepository`** — репозиторий конфигурации (массив)
- **`Config\ConfigLocator`** — обнаружение классов конфигурации в модуле
- **`Config\ConfigManager`** — менеджер конфигурации
- **`Config\Entity`** — базовый класс конфигурации модуля

---

## Правила разработки

### Стиль кодирования

- **Строгая типизация:** Требуется `declare(strict_types=1);` в начале каждого файла. На момент аудита (~2026-04) только ~7.5% файлов имеют это объявление — требуется постепенное добавление.
- **PHPStan:** Уровень 5, проверяет ~12 путей из ~306 файлов в `src/`. План: поднять до уровня 8 с полным покрытием `src/`.
- **Линтинг:** `composer lint` проверяет синтаксис PHP через `php -l`.

### Тестирование

- **Unit-тесты:** PHPUnit, `tests/` — 6 файлов, покрывают ~6 из ~300 файлов исходников
- **Integration-тесты:** Smoke-тесты на реальных классах Bitrix (`tests/Integration/`)
- **Покрытие:** Требуется расширение тестов для `HighloadBlock/*`, `Storage/Concerns/*`, `File/FileService`, `EntityView/*`, `UI/*`

### Контрибуция

1. **Не добавлять новые публичные API без тестов.**
2. **Предпочитать аддитивные изменения** (обратная совместимость).
3. **Согласовывать документацию с кодом**, особенно `docs/application.md`, `docs/README.md`, `docs/SUPPORTED.md`.
4. **Не использовать Laravel Illuminate** — проект использует кастомный `mb4it/container`.

---

## Зоны риска и известные проблемы

### 1. Dual PSR-4 (namespace collision)

Ранее `composer.json` мапил оба `MB\Bitrix\` и `MB\Core\` на `src/`, что создавало коллизии с модулем `mb.core`. В текущей версии оставлен только `MB\Bitrix\`.

**Решение:** Использовать только `MB\Bitrix\*` для нового кода. Классы `MB\Core\*` считаются устаревшими.

### 2. Легаси Bitrix globals

Некоторые классы используют `global $APPLICATION`, `new \CUserTypeEntity()`, `GetMessage()`, что делает их нетестируемыми:

- `HighloadBlock/Base.php:159,197` — `global $APPLICATION`, `new \CUserTypeEntity()`
- `EntityView/Base.php:123` — `global $APPLICATION`, `AuthForm()`, `GetGroupRight('mb.core')`
- `File/FileService.php:465,880` — `@chmod`, `new \CDiskQuota()`, `GetMessage()`
- `UI/Control/Field/HtmlEditorField.php:24` — `new \CHTMLEditor()`

**План:** Ввести контракты `Contracts\Bitrix\*` (CurrentApplication, UserFieldRegistry, DiskQuota, HtmlEditor) и внедрять их через DI.

### 3. Storage\Concerns\* — дефекты трейтов

- **`BatchUpsertTrait`:** Фиксирует поля по первой строке; строки с другими ключами дают некорректный SQL. `try { } catch { throw $e; }` — бесполезный перехват.
- **`DeleteByQueryTrait`:** Regex-парсинг `SELECT → DELETE` ломается на CTE, подзапросах, комментариях.
- **`UpdateByWhereTrait`, `FluentTrait`:** Бесполезные `try-catch` блоки.

**Шаблон для исправления:** `MassUpdateTrait` — правильная работа с транзакциями, валидация PK.

### 4. Singleton в HighloadBlock\Base

`protected static $_instance` — глобальное состояние, нетестированное, без `resetInstance()`.

**План:** Заменить на фабрию через контейнер (`HighloadBlockRegistry`).

### 5. Кэш-трейты без Bitrix-бэкенда

- **`Traits\Cacheable`:** Процесс-локальный `static array $cache`, нет TTL.
- **`Traits\RememberCachable`:** Добавляет TTL, но `__callStatic` создаёт новый экземпляр каждый раз — не делит кэш.

**План:** Ввести `Contracts\Cache\Repository` (PSR-16) с адаптером `Bitrix\ManagedCacheAdapter`.

### 6. @dev зависимости

Пакеты `mb4it/*` (container, stringable, collections, conditionable, logger) требуются как `@dev`. Это блокирует установку в проектах со `minimum-stability: stable`.

**План:** Выпустить тегированные версии `0.x` / `1.x` для всех `mb4it/*`.

### 7. Reflection в Application

`Foundation\Application` использует Reflection для доступа к приватным свойствам `$aliases` / `$bindings` родительского `Container`.

**Риск:** Изменения в `mb4it/container` могут сломать `buildWithParameters()`.

**План:** Добавить публичный API в контейнер для чтения metadata.

---

## Матрица поддержки API

| Подсистема | Namespace | Статус | Примечания |
|------------|-----------|--------|------------|
| Storage / SqlHelper | `MB\Bitrix\Storage\*` | ✅ Поддерживается | Batch upsert/update/delete, MySQL `ROW()` / PostgreSQL `ON CONFLICT` |
| HighloadBlock | `MB\Bitrix\HighloadBlock\*` | ✅ Поддерживается | Требуется модуль `highloadblock` |
| Migration | `MB\Bitrix\Migration\*` | ✅ Поддерживается | `upAll()` / `downAll()` не включают агентов (см. `docs/migrations.md`) |
| Foundation / Facades | `Application`, `Support\Facades\*` | ✅ Поддерживается | Требуется явный bootstrap |
| Module Entity | `MB\Bitrix\Module\Entity` | ✅ Поддерживается | Использует `ConfigLocator`, `Settings\*` |
| Config | `MB\Bitrix\Config\*` | ✅ Поддерживается | `Entity::create($moduleId, $siteId)` — фабрика |
| Admin / Settings UI | `MB\Bitrix\Settings\*` | ⚠️ Экспериментально | Зависит от Bitrix UI, требует smoke-тестов |
| EntityView | `MB\Bitrix\EntityView\*` | ⚠️ Частично | `MenuAction` минимален — переопределять в модуле |
| File / Image | `MB\Bitrix\File\*` | ✅ Поддерживается | DI через `app('file.service')` |
| Logger | `MB\Bitrix\Logger\*` | ✅ Поддерживается | PSR-3, UniversalLogger, EventLogger |

---

## Примеры использования

### Bootstrap приложения

```php
use MB\Bitrix\Foundation\Application;

$app = new Application();
$app->setBasePath($_SERVER['DOCUMENT_ROOT'] . '/local');
$app->register(MyServiceProvider::class);
$app->boot();
```

### Регистрация модуля

```php
use MB\Bitrix\Foundation\Application;

$app = Application::getInstance();
$app->registerModule('my.module');

$module = app('my.module:module');
$config = app('my.module:config');
$migration = app('my.module:migration');
$logger = app('my.module:logger');
```

### Миграция HL-блока

```php
use MB\Bitrix\HighloadBlock\HighloadBlockManager;

$manager = HighloadBlockManager::create($moduleEntity);
$result = $manager->update(); // создать/обновить все HL-блоки

if (!$result->isSuccess()) {
    // обработать ошибки
}
```

### Сохранение файла

```php
use MB\Bitrix\Contracts\File\FileServiceContract;

$fileData = $_FILES['PHOTO'] ?? null;
if ($fileData) {
    $files = app(FileServiceContract::class);
    $fileId = $files->save($fileData, 'my_module/photos');
    
    if ($fileId) {
        $fileInfo = $files->getFileData($fileId);
    }
}
```

### Логирование

```php
use MB\Bitrix\Logger\UniversalLogger;

$logger = app(UniversalLogger::class);
$logger->info('Сообщение', ['context' => 'data']);
$logger->error('Ошибка', ['exception' => $e]);
```

---

## Ссылки на документацию

| Файл | Описание |
|------|----------|
| [`docs/README.md`](docs/README.md) | Обзор пакета, установка, быстрый старт |
| [`docs/application.md`](docs/application.md) | Ядро приложения, жизненный цикл, провайдеры |
| [`docs/SUPPORTED.md`](docs/SUPPORTED.md) | Матрица поддерживаемого API |
| [`docs/migrations.md`](docs/migrations.md) | Миграции, менеджеры сущностей |
| [`docs/storage-and-highloadblock.md`](docs/storage-and-highloadblock.md) | Storage, ORM, HL-блоки |
| [`docs/file-and-image.md`](docs/file-and-image.md) | Файлы и изображения |
| [`docs/laravel-parity.md`](docs/laravel-parity.md) | DI в стиле Laravel (без Illuminate) |
| [`docs/logging-and-events.md`](docs/logging-and-events.md) | Логирование и уведомления |
| [`docs/agents-and-events.md`](docs/agents-and-events.md) | Агенты и обработчики событий |
| [`docs/components.md`](docs/components.md) | Компоненты Bitrix |
| [`CHANGELOG.md`](CHANGELOG.md) | История изменений |
| [`AGENTS.md`](AGENTS.md) | Инструкция для ИИ-агентов (entrypoints, flow, risk zones) |
| [`AUDIT.md`](AUDIT.md) | Подробный аудит кода (проблемы, roadmap рефакторинга) |

---

## Приоритетные задачи (из AUDIT.md)

| # | Приоритет | Область | Задача |
|---|-----------|---------|--------|
| 1 | **Высокий** | Namespace | Merge `MB\Core\*` → `MB\Bitrix\*`, добавить `class_alias` shim |
| 2 | **Высокий** | Bitrix coupling | Ввести контракты `Contracts\Bitrix\*`, убрать `global $APPLICATION` |
| 3 | **Высокий** | Storage | Исправить `BatchUpsertTrait` (field-union, chunking, AddResult) |
| 4 | **Высокий** | Storage | Заменить regex `SELECT→DELETE` на прямой DELETE builder |
| 5 | **Высокий** | CI | PHPStan уровень 5 → 8, полное покрытие `src/` |
| 6 | **Высокий** | Composer | Тегировать `mb4it/*`, заменить `@dev` на `^1.x` |
| 7 | **Высокий** | mb.core | Добавить зависимость `mb4it/bitrix-support`, начать cutover Config |
| 8 | **Средний** | HighloadBlock | De-singletonify `HighloadBlock\Base`, добавить `HighloadBlockRegistry` |
| 9 | **Средний** | Cache | `Contracts\Cache\Repository` + Bitrix ManagedCache adapter |
| 10 | **Средний** | Strict types | Добавить `declare(strict_types=1)` во все 306 файлов |

---

## Контакты и авторы

- **Автор:** Maxim Bezvodinskikh (m.bezvodinskikh@gmail.com)
- **Лицензия:** MIT
- **Packagist:** `mb4it/bitrix-support`
