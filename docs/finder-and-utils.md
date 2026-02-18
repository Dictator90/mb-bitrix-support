## Утилиты и поиск классов

Помимо крупных подсистем (файлы, HL-блоки, миграции и т.п.), пакет содержит набор небольших, но полезных утилит:

- поисковик классов `MB\Bitrix\Finder\ClassFinder`;
- трейты для кэширования `MB\Bitrix\Traits\Cacheable` и `MB\Bitrix\Traits\RememberCachable`;
- (см. также разделы `storage-advanced.md`, `logging-and-events.md`, где эти утилиты используются).

---

## Поиск классов: `Finder\ClassFinder`

Файл: `src/Finder/ClassFinder.php`  
Пространство имён: `MB\Bitrix\Finder`

Назначение: **поиск классов по файловой системе, которые либо наследуют заданный базовый класс, либо реализуют интерфейс.**

Используется, в частности, менеджерами:

- `HighloadBlock\HighloadBlockManager` — для поиска всех классов, наследующих `HighloadBlock\Base`;
- `Agent\AgentManager` — для поиска всех наследников `Agent\Base`;
- `Event\EventManager` — для поиска всех наследников `Event\Base`.

### Методы

```php
public static function findExtended(string $dir, string $namespace, string $parentClass)
```

- рекурсивно обходит каталог `$dir` (`RecursiveDirectoryIterator` + `RecursiveIteratorIterator`);
- для каждого PHP‑файла:
  - строит имя класса как `$namespace . str_replace('/', '\\', $relativePath) . '\\' . $basename`;
  - пытается создать `ReflectionClass`;
  - добавляет в результат, если:
    - класс не абстрактный;
    - является наследником `$parentClass` (`isSubclassOf()`).

```php
public static function findImplements(string $dir, string $namespace, string $interfaceClass)
```

- похожая логика, но фильтрует по `implementsInterface($interfaceClass)`;
- дополнительно проверяет, что `$relativePath` не пуст (чтобы исключить базовые классы из корня).

### Пример использования

```php
use MB\Bitrix\Finder\ClassFinder;

$dir = $module->getLibPath();      // /local/modules/my.module/lib
$namespace = $module->getNamespace(); // \My\Module\

// Все наследники абстрактного базового класса
$hlClasses = ClassFinder::findExtended($dir, $namespace, \MB\Bitrix\HighloadBlock\Base::class);

// Все реализации интерфейса
$handlers = ClassFinder::findImplements($dir, $namespace, \My\Module\Contracts\HandlerInterface::class);
```

Это позволяет реализовать множество сценариев «по конвенции, а не по конфигурации», когда достаточно унаследоваться от базового класса или реализовать интерфейс, и сущность будет автоматически найдена и обработана (например, зарегистрирована как агент или обработчик события).

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
  - `File\FileService` — кэширование данных о файлах (`getFileData()`, `getFilesData()` и др.);

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

