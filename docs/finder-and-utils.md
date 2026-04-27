## Утилиты и поиск классов

Помимо крупных подсистем (файлы, HL-блоки, миграции, UI), пакет содержит набор небольших, но полезных утилит:

- поиск классов через `MB\Bitrix\Filesystem\Filesystem::classFinder()` (mb4it/filesystem);
- трейты для кэширования `MB\Bitrix\Traits\Cacheable` и `MB\Bitrix\Traits\RememberCachable`;
- (см. также разделы `storage-advanced.md`, `logging-and-events.md`, `ui.md`, где эти утилиты используются).

---

## Поиск классов: `Filesystem::classFinder()`

Мост: `MB\Bitrix\Filesystem\Filesystem` (см. `src/Filesystem/Filesystem.php`).  
Поиск реализован пакетом mb4it/filesystem (`MB\Filesystem\Finder\ClassFinder`): обход PHP‑файлов и разбор токенов без загрузки классов (без ReflectionClass/autoload).

Используется менеджерами:

- `HighloadBlock\HighloadBlockManager` — поиск классов, наследующих `HighloadBlock\Base`;
- `Agent\AgentManager` — наследников `Agent\Base`;
- `Event\EventManager` — наследников `Event\Base`.

### API

- **`Filesystem::classFinder()->extends(string $directory, string $baseClassFqcn): array`**  
  Возвращает массив метаданных (для каждого класса: `class`, `file`, `namespace`, `short_name`, `extends`, `implements`, `traits`).  
  Список FQCN: `array_column(Filesystem::classFinder()->extends($dir, $baseClass), 'class')`.

- **`implements(string $directory, string $interfaceFqcn): array`** — поиск классов, реализующих интерфейс (тот же формат метаданных).

- **`hasTrait(string $directory, string $traitFqcn): array`** — поиск классов, использующих трейт.

### Пример

```php
use MB\Bitrix\Filesystem\Filesystem;

$dir = $module->getLibPath();

// Список FQCN наследников базового класса
$hlClasses = array_column(
    Filesystem::classFinder()->extends($dir, \MB\Bitrix\HighloadBlock\Base::class),
    'class'
);

// Все реализации интерфейса (массив метаданных)
$handlers = Filesystem::classFinder()->implements($dir, \My\Module\Contracts\HandlerInterface::class);
```

Это позволяет реализовать сценарии «по конвенции»: достаточно унаследоваться от базового класса или реализовать интерфейс — сущность будет найдена и обработана (агент, обработчик события, HL-блок и т.д.).

---

## Трейт `Traits\Cacheable`

Файл: `src/Traits/Cacheable.php`  
Пространство имён: `MB\Bitrix\Traits`

Назначение: **простейший статический кэш по ключу с префиксом класса**.

Основная идея — иметь общий статический массив `static::$cache` и обращение к нему через методы:

- `getFromCache(string $key, $default = null)`
- `setToCache(string $key, $value): void`
- `hasInCache(string $key): bool`
- `removeFromCache(string $key): bool`
- `clearCache(): void` — очищает кэш только для текущего класса (по префиксу);
- `getAllCache(): array` — возвращает только значения, относящиеся к текущему классу;
- `setMultipleToCache(array $values): void`
- `getMultipleFromCache(array $keys, $default = null): array`

При этом ключи фактически хранятся как:

- `static::class . '::' . $key`

что позволяет нескольким классам использовать общий статический массив, не пересекаясь по значениям.

**Типичный сценарий:**

- сохранить какие‑то тяжелые для вычисления данные (например, список полей, конфигурацию и т.п.);
- быстро переиспользовать их в рамках одного запроса без обращений к БД/файлам.

---

## Трейт `Traits\RememberCachable`

Файл: `src/Traits/RememberCachable.php`

Этот трейт реализует «ленивый» кэш с TTL:

- хранит в `static::$cache` массивы вида:

```php
[
  'value'   => mixed,
  'expires' => int|null, // timestamp или null для «бессрочного» кэша
]
```

Ключевые методы:

- `setCache(string $key, $value, ?int $ttl = null): static`
  - сохраняет значение и срок жизни (в секундах);
- `getCache(string $key, $default = null)`
  - возвращает значение, если оно есть и не истекло;
- `hasCache(string $key): bool`
  - проверяет наличие и актуальность ключа;
- `removeCache(string $key): self`
  - удаляет запись по ключу;
- `clearCache(): self`
  - очищает весь кэш;
- `remember(string $key, callable $callback, ?int $ttl = null): mixed`
  - если значение по ключу есть и не протухло — возвращает его;
  - иначе вычисляет через `$callback()`, сохраняет через `setCache()` и возвращает результат.

Статический вызов `static::remember(...)` обрабатывается через `__callStatic` и **должен возвращать** то же значение, что и экземплярный `remember()` (результат колбэка или закэшированное значение).

Дополнительно:

- методы для работы с множеством значений (`setMultipleCache`, `getMultipleCache`, `removeMultipleCache`);
- получение статистики (`getCacheStats()`), списка ключей (`getCacheKeys()`).

**Пример:**

```php
use MB\Bitrix\Traits\RememberCachable;

class MyService
{
    use RememberCachable;

    public static function getSomethingExpensive(int $id): array
    {
        return static::remember("expensive_{$id}", function () use ($id) {
            // медленный запрос к БД или внешнему API
            return self::loadFromDb($id);
        }, 300); // кэш 5 минут
    }
}
```

---

## Где утилиты используются в пакете

- `ClassFinder`:
  - `HighloadBlock\HighloadBlockManager` — поиск всех классов HL-блоков;
  - `Agent\AgentManager` — поиск всех классов‑агентов;
  - `Event\EventManager` — поиск всех классов‑обработчиков событий.

- `RememberCachable`:
  - может подключаться в сервисах с TTL; `File\FileService` использует **собственный** приватный статический кеш строк `b_file`, а не этот трейт.

- `Cacheable`:
  - может использоваться в различных вспомогательных классах (списки, конфигурация, словари) для быстрого статического кэша.

---

## Рекомендации

- Используйте `ClassFinder` когда:
  - хотите, чтобы система автоматически находила все реализации определенного базового класса/интерфейса в рамках модуля;
  - вам нужна расширяемость «по конвенции» (добавил класс — он автоматически будет учтён миграциями/менеджерами).

- Подключайте `RememberCachable` в:
  - сервисах и помощниках, где часто требуются дорогостоящие вычисления или запросы;
  - местах, где важно иметь TTL и уметь инвалидацию по времени.

- Используйте `Cacheable`, когда:
  - нужна простая статическая мапа «ключ → значение» без TTL;
  - значения зависят только от класса и не меняются в течение жизни запроса.

Эти утилиты помогают уменьшить дублирование кода и повысить производительность, сохраняя при этом лаконичный и предсказуемый интерфейс.

