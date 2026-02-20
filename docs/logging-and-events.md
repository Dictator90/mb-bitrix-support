## Логирование и уведомления

Пакет `mb/bitrix-support` предоставляет унифицированный слой логирования, который использует:

- интерфейсы `psr/log`;
- файловые логи (`/local/logs/...`);
- журнал событий Bitrix (`/bitrix/admin/event_log.php`);
- системные уведомления в админке.

Ключевые классы:

- `MB\Bitrix\Logger\FileLogger` — логирование в файл;
- `MB\Bitrix\Logger\EventLogger` — логирование в журнал событий;
- `MB\Bitrix\Logger\NotificationLogger` — системные уведомления;
- `MB\Bitrix\Logger\UniversalLogger` — единая точка входа, реализующая `Psr\Log\LoggerInterface`.

---

## Файловый логгер `FileLogger`

Файл: `src/Logger/FileLogger.php`  
Пространство имён: `MB\Bitrix\Logger`

Является самостоятельной реализацией файлового логгера, добавляя:

- каталог логов по умолчанию: `LOG_FOLDER = '/local/logs/'`;
- максимальный размер файла: `LOG_FILE_MAX_SIZE = 1048576` (1 МБ);
- шаблон строки лога: `LOG_FILE_ENTRY_TEMPLATE = '{date} {level} {message}\n{context}\n\n'`;
- формат даты: `Y-m-d H:i:s`.

Конструктор:

```php
public function __construct(
    string $relativeFileName,
    bool $autoDate = true,
    ?string $logFolder = null,
    ?int $logFileMaxSize = null
)
```

Особенности:

- если `$autoDate = true` и у `$relativeFileName` нет расширения, то:
  - итоговый путь будет вида `{$relativeFileName}/Y-m-d.log`;
- путь к файлу строится как `DOCUMENT_ROOT . $relativePath`, где `$relativePath` формируется из `$logFolder` и относительного пути;
- при необходимости автоматически создаются каталоги через фасад `MB\Bitrix\Support\Facades\Filesystem`;
- при достижении размера `LOG_FILE_MAX_SIZE` текущий файл переименовывается в резервную копию с суффиксом `.1`, а новый лог продолжается в свежем файле.

Дополнительные методы:

- `getSiteLogFileName(): string` — путь к файлу относительно `DOCUMENT_ROOT` (удобно для вывода в журнале событий/уведомлениях);
- `getAbsoluteLogFileName(): string` — абсолютный путь к файлу;
- переопределён `logMessage()` так, чтобы форматировать запись по шаблону с датой, уровнем и контекстом.

---

## Логгер журнала событий `EventLogger`

Файл: `src/Logger/EventLogger.php`

Наследует `Bitrix\Main\Diag\Logger`, использует `CEventLog` и карту уровней:

- константы:
  - `CONTEXT_FIELD_EVENT_MODULE_ID = 'EVENT_MODULE_ID'`;
  - `CONTEXT_FIELD_EVENT_TYPE = 'EVENT_TYPE'`;
  - `CONTEXT_FIELD_EVENT_ITEM_ID = 'EVENT_ITEM_ID'`;
  - `DEFAULT_EVENT_TYPE = 'MB_CORE_MISC'`;
  - `DEFAULT_MODULE_ID = 'mb.core'`.

- `LEVELS_MAP` сопоставляет уровни `Psr\Log\LogLevel` с константами `CEventLog::SEVERITY_*`.

Основные методы:

- `__construct(string $moduleId = self::DEFAULT_MODULE_ID, string $eventType = self::DEFAULT_EVENT_TYPE)`
  - задаёт дефолтный модуль и тип события;
- `setEventType(string $eventType): self` — изменить тип;
- `setModuleId(string $moduleId): self` — изменить модуль;
- `logMessage(string $level, string $message): void`
  - формирует массив для `CEventLog::Add()`:
    - `SEVERITY`, `AUDIT_TYPE_ID`, `MODULE_ID`, `ITEM_ID`, `DESCRIPTION`.

Метод `interpolate()` заполняет:

- `EVENT_ITEM_ID` — из контекста (`ITEM_ID`, `ID` или `CONTEXT_FIELD_EVENT_ITEM_ID`);
- `EVENT_MODULE_ID` и `EVENT_TYPE` — либо из контекста, либо из значений по умолчанию.

---

## Логгер уведомлений `NotificationLogger`

Файл: `src/Logger/NotificationLogger.php`

Также наследует `Bitrix\Main\Diag\Logger`, но пишет в `CAdminNotify`:

- константы:
  - `CONTEXT_FIELD_EVENT_MODULE_ID = 'EVENT_MODULE_ID'`;
  - `CONTEXT_FIELD_EVENT_TYPE = 'EVENT_TYPE'`;
  - `DEFAULT_EVENT_TYPE = 'MB_CORE_MISC'`;
  - `DEFAULT_MODULE_ID = 'main'`.

Методы:

- конструктор аналогичен `EventLogger`;
- `setEventType()` / `setModuleId()` — изменение типа и модуля;
- `logMessage(string $level, string $message): void`
  - вызывает `CAdminNotify::Add()` с `MESSAGE`, `TAG`, `MODULE_ID` и `ENABLE_CLOSE = 'Y'`;
- `interpolate()` — заполняет `eventType` и `eventModuleId` из контекста или значений по умолчанию.

---

## Универсальный логгер `UniversalLogger`

Файл: `src/Logger/UniversalLogger.php`

Наследует `Psr\Log\AbstractLogger` и реализует единый интерфейс логирования для файлов, журнала событий и уведомлений.

Константы:

- поля контекста (в т.ч. для дальнейшего использования в уведомлениях/ссылках):
  - `CONTEXT_FIELD_EVENT_MODULE_ID` — прокси для `EventLogger::CONTEXT_FIELD_EVENT_MODULE_ID`;
  - `CONTEXT_FIELD_EVENT_TYPE` — прокси для `EventLogger::CONTEXT_FIELD_EVENT_TYPE`;
  - `CONTEXT_FIELD_EVENT_ITEM_ID` — прокси для `EventLogger::CONTEXT_FIELD_EVENT_ITEM_ID`;
  - `CONTEXT_FIELD_NOTIFICATION_MESSAGE = 'NOTIFICATION_MESSAGE'` — альтернативный текст для уведомления.

- уровни для разных каналов:
  - `PANEL_NOTIFICATION_LEVELS` — уровни, при которых будет создано уведомление:
    - `LogLevel::EMERGENCY`, `ALERT`, `CRITICAL`;
  - `EVENT_NOTIFICATION_LEVELS` — уровни, при которых будет запись в `CEventLog`:
    - `EMERGENCY`, `ALERT`, `CRITICAL`, `ERROR`, `WARNING`, `NOTICE`.

Конструктор:

```php
public function __construct(
    string $relativeFileName,
    string $moduleId = 'main',
    bool $autoDate = true,
    ?string $logFolder = null,
    ?int $logFileMaxSize = null
)
```

- инициализирует:
  - `$this->fileLogger = new FileLogger(...)`;
  - `$this->eventLogger = new EventLogger($moduleId)`;
  - `$this->notificationLogger = new NotificationLogger($moduleId)`.

### Метод `log($level, $message, array $context = [])`

Реализует интерфейс `Psr\Log\LoggerInterface`:

1. Если уровень входит в `EVENT_NOTIFICATION_LEVELS`:
   - формирует HTML-сообщение для журнала:
     - основной текст;
     - ссылка на файл лога (`getSiteLogFileName()` от `FileLogger`);
   - вызывает `$this->eventLogger->log($level, $eventMessage, $context)`.
2. Если уровень входит в `PANEL_NOTIFICATION_LEVELS`:
   - берёт текст уведомления из:
     - `$context[self::CONTEXT_FIELD_NOTIFICATION_MESSAGE]` или
     - `$message`;
   - при наличии записи в журнале событий добавляет в конец предложение «Подробнее в журнале» с ссылкой на `event_log.php` и подстановкой `EVENT_TYPE` из контекста;
   - вызывает `$this->notificationLogger->log($level, $notificationMessage, $context)`.
3. Всегда:
   - пишет исходное сообщение в файл:
     - `$this->fileLogger->log($level, (string) $message, $context)`.

Дополнительные методы:

- `logToEvent($level, $message, array $context = []): void` — напрямую в журнал событий;
- `logToNotification($level, $message, array $context = []): void` — напрямую в уведомление;
- `logToFile($level, $message, array $context = []): void` — напрямую в файл;
- `setLevel(string $level): self` — задаёт минимальный уровень сразу для всех трёх логгеров.

---

## Примеры использования

### Базовая инициализация логгера модуля через контейнер

```php
use MB\Bitrix\Contracts\Log\LoggerFactoryInterface;
use Psr\Log\LogLevel;

/** @var LoggerFactoryInterface $factory */
$factory = app(LoggerFactoryInterface::class);

$logger = $factory->universal('my_module/main');
$logger->setModuleId('my.module'); // идентификатор модуля

$logger->setLevel(LogLevel::INFO);
```

### Запись ошибки с ссылкой на журнал событий

```php
$logger->error(
    'Ошибка при обработке заказа',
    [
        UniversalLogger::CONTEXT_FIELD_EVENT_TYPE => 'MY_MODULE_ORDER_ERROR',
        UniversalLogger::CONTEXT_FIELD_EVENT_ITEM_ID => $orderId,
        'ORDER_ID' => $orderId,
        'DATA' => $payload,
    ]
);
```

Результат:

- запись в файле лога с деталями и контекстом;
- запись в журнале событий с типом `MY_MODULE_ORDER_ERROR`, модулем `my.module` и `ITEM_ID = $orderId`.

### Критическая ошибка с уведомлением в админке

```php
use Psr\Log\LogLevel;

$logger->log(
    LogLevel::CRITICAL,
    'Критическая ошибка при синхронизации',
    [
        UniversalLogger::CONTEXT_FIELD_NOTIFICATION_MESSAGE =>
            'Синхронизация с внешней системой завершилась с ошибкой. Проверьте журнал событий.',
        UniversalLogger::CONTEXT_FIELD_EVENT_TYPE => 'MY_MODULE_SYNC_ERROR',
    ]
);
```

Результат:

- запись в файловом логе;
- запись в журнале событий;
- системное уведомление в админке с кратким текстом и ссылкой «Подробнее в журнале».

---

## Рекомендации по внедрению логирования

- Создавайте **один экземпляр `UniversalLogger` на модуль** (например, через DI-контейнер, синглтон или сервис-локатор).
- Для каждой подсистемы (например, импорт, интеграции, cron-задачи) используйте **отдельные файлы логов**:
  - `import/products`, `agents/sync`, `webhooks/main` и т.п.
- Всегда передавайте **контекст**:
  - идентификаторы объектов (`ID`, `ORDER_ID`, `USER_ID`);
  - дополнительные данные в удобном для чтения виде (массива/строки).
- Для событий, которые вы хотите отслеживать отдельно в журнале:
  - задайте `CONTEXT_FIELD_EVENT_TYPE` и используйте единый префикс (`MY_MODULE_*`).

Используя этот слой, вы получаете единообразное логирование во всех частях модуля: разработчики могут искать проблемы как по файлам логов, так и через стандартный интерфейс Bitrix (журнал событий и уведомления).

