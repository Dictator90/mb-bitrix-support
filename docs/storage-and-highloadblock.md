## Storage и Highload-блоки

Этот раздел описывает надстройку над D7 ORM и Highload-блоками, предоставляемую пространствами имён:

- `MB\Bitrix\Storage` — тонкие обёртки вокруг ORM-сущностей, объектов и коллекций, а также расширенный query‑builder;
- `MB\Bitrix\HighloadBlock` — декларативное описание HL-блоков в коде и их синхронизация с БД.

---

## Пространство `MB\Bitrix\Storage`

Основные классы:

- `Storage\Entity` — наследник `Bitrix\Main\ORM\Entity`;
- `Storage\EntityObject` — наследник `Bitrix\Main\ORM\Objectify\EntityObject`;
- `Storage\Collection` — наследник `Bitrix\Main\ORM\Objectify\Collection`;
- `Storage\Query` — обёртка над `Bitrix\Main\ORM\Query\Query` с расширенными where*/having*/with* методами.

### Класс `Storage\Entity`

Файл: `src/Storage/Entity.php`

```php
namespace MB\Bitrix\Storage;

use Bitrix\Main\ORM\Entity as BitrixEntity;

class Entity extends BitrixEntity
{
    public function createObject($setDefaultValues = true)
    {
        $objectClass = $this->getObjectClass();
        $entityObjectClass = new $objectClass($setDefaultValues);
        $entityObjectClass::$dataClass = $this->getDataClass();
        return $entityObjectClass;
    }
}
```

Особенность: при создании объекта сущности автоматически устанавливается статическое свойство `$dataClass`, чтобы объект знал, с какой `DataManager` он связан.

Это упрощает последующую работу с методами ORM:

```php
/** @var \MB\Bitrix\Storage\Entity $entity */
$entity = MyDataManager::getEntity();        // где MyDataManager расширяет DataManager

/** @var \MB\Bitrix\Storage\EntityObject $object */
$object = $entity->createObject();

$object->set('NAME', 'Test');
$object->save();
```

### Классы `EntityObject` и `Collection`

Файлы:

- `src/Storage/EntityObject.php`
- `src/Storage/Collection.php`

Они просто наследуют базовые классы Bitrix:

- `EntityObject` от `Bitrix\Main\ORM\Objectify\EntityObject`;
- `Collection` от `Bitrix\Main\ORM\Objectify\Collection`.

Это создаёт единый неймспейс `MB\Bitrix\Storage` и позволяет использовать их совместно со своей `Entity`/`DataManager`.

### Расширенный query‑builder: `Storage\Query`

Файл: `src/Storage/Query.php`

`MB\Bitrix\Storage\Query` расширяет стандартный `Bitrix\Main\ORM\Query\Query` и добавляет поддержку «виртуальных» методов:

- `where*` / `having*`, проксируемых либо:
  - в статические методы `DataManager` (например, `whereAny`, `whereAll`, `withRelation` и т.п.);
  - либо в `Bitrix\Main\ORM\Query\Filter\ConditionTree` (класс `Filter`);
- `with*` — проксируются в статические методы `DataManager`.

Поддерживаемый набор `where*`-методов (см. phpdoc в `Query`):

- `where`, `whereNot`, `whereColumn`, `whereNull`, `whereNotNull`;
- `whereIn`, `whereNotIn`, `whereBetween`, `whereNotBetween`;
- `whereLike`, `whereNotLike`;
- `whereExists`, `whereNotExists`;
- `whereMatch`, `whereNotMatch`;
- `whereExpr`;
- а также `whereAny`, `whereAll`, `whereNone` — уже реализуются в самом `DataManager` (см. комментарии в коде).

Пример использования:

```php
use MB\Bitrix\Storage\Query;

$entity = MyDataManager::getEntity(); // DataManager должен использовать MB\Bitrix\Storage\Entity

$query = new Query($entity);

$rows = $query
    ->setSelect(['ID', 'NAME'])
    ->whereLike('NAME', '%Test%')
    ->whereIn('STATUS', ['A', 'B'])
    ->setLimit(20)
    ->fetchAll();
```

Если вызываемый `where*`-метод найден:

- в `DataManager` — туда передаётся текущий экземпляр `Query` первым аргументом, что позволяет строить на нём любые вспомогательные фильтры;
- иначе — в `Filter` (`$this->filterHandler`).

При попытке вызвать неизвестный `where*`/`with*` метод будет выброшено `SystemException` с подробным сообщением.

---

## Пространство `MB\Bitrix\HighloadBlock`

Этот слой позволяет **декларативно описывать Highload-блоки в коде** и синхронизировать их схему (таблицу и UF‑поля) с БД.

Ключевые классы:

- `HighloadBlock\Base` — базовый описатель одного HL-блока;
- `HighloadBlock\HighloadBlockManager` — менеджер миграций для HL-блоков.

### Базовый класс `HighloadBlock\Base`

Файл: `src/HighloadBlock/Base.php`

`Base` инкапсулирует всю работу с:

- таблицей `b_highloadblock` и `b_highloadblock_lang`;
- пользовательскими полями через `CUserTypeEntity` / `UserFieldTable`;
- компиляцией ORM‑сущности (`HighloadBlockTable::compileEntity()`);
- синхронизацией UF-полей по декларации в коде.

Ключевые абстрактные методы:

- **`public static function getTableName(): string`** — имя таблицы HL-блока;
- **`public static function getName(): string`** — символьное имя HL-блока;
- **`public static function getMap(): array`** — массив полей (`AbstractField` или массивы конфигурации);
- **`public static function getLang(): array`** — языковые названия блока `['ru' => 'Название', 'en' => 'Name', ...]`.

В типичном наследнике вы описываете:

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
        return ['ru' => 'Мой HL‑блок'];
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

`Base` реализует:

- **`getInstance()`** — синглтон по имени блока (возвращает один экземпляр на класс);
- **`isExist()`** — проверяет наличие HL-блока (наличие ORM‑сущности);
- **`createTable()`**:
  - создаёт запись в `b_highloadblock` с `NAME` и `TABLE_NAME`;
  - создаёт записи в `b_highloadblock_lang` по `getLang()`;
  - регистрирует UF‑поля по `getMap()` через `CUserTypeEntity::Add()`;
- **`refresh()`**:
  - проходит по существующим полям;
  - обновляет/добавляет поля по `getMap()` (`refreshFields()`/`buildUserField()`);
- **`dropTable()`** — удаляет HL-блок через `HighloadBlockTable::delete()` (включая таблицу и UF-поля);
- **`truncateTable()`** — очищает данные в таблице;
- вспомогательные методы для получения `ENTITY_ID`, списка `UserFieldTable`, построения конфигурации поля и накопления ошибок (`ErrorCollection`).

### Менеджер `HighloadBlockManager`

Файл: `src/HighloadBlock/HighloadBlockManager.php`

`HighloadBlockManager` наследует `MB\Bitrix\Migration\BaseEntityManager` и используется для **синхронизации всех HL-блоков модуля, описанных через `Base`**.

Ключевые методы:

- **`getEntityClass(): string`** — всегда возвращает `Base::class`;
- **`update(): Result`**
  - вызывает `createTable()` (см. ниже);
  - по сути является методом «синхронизировать всё».

- **`deleteAll(): Result`**
  - вызывает `dropTable()` и удаляет все HL-блоки модуля, описанные наследниками `Base`.

- **`createFor(string $className): Result`**
  - создает или обновляет таблицу только для одного указанного наследника `Base`.

- **`dropFor(string $className): Result`**
  - удаляет таблицу и HL-блок для одного указанного наследника `Base`.

#### Внутренние операции

- **`createTable(?array $classList = null): Result`**
  - если `$classList` не задан, через `Finder\ClassFinder::findExtended()` ищет все классы модуля, наследующие `Base`;
  - для каждого класса:
    - если блок уже существует (`isExist()`) — вызывает `refresh()` для обновления полей;
    - иначе — вызывает `createTable()` для первичного создания;
    - собирает все ошибки из `ErrorCollection` в единый объект `Migration\Result`.

- **`dropTable(): Result`** и **`dropTableFor(array $classList, ?Result $result = null): Result`**
  - находят все классы‑наследники `Base` и вызывают у них `dropTable()`.

Использование в миграциях/установщике модуля:

```php
use MB\Bitrix\HighloadBlock\HighloadBlockManager;

// $moduleEntity реализует MB\Bitrix\Contracts\Module\Entity
$manager = HighloadBlockManager::create($moduleEntity);

// Создать/обновить все HL-блоки
$result = $manager->update();

// Удалить все HL-блоки модуля
// $result = $manager->deleteAll();
```

---

## Типичный цикл работы с HL-блоками

1. **Описываете класс HL-блока** — наследник `HighloadBlock\Base`, реализующий `getTableName()`, `getName()`, `getLang()`, `getMap()`.
2. **Создаёте миграцию/скрипт**, в котором:
   - получаете сущность модуля (`MB\Bitrix\Contracts\Module\Entity`);
   - создаёте `HighloadBlockManager::create($moduleEntity)`;
   - вызываете `$manager->update()` или `$manager->createFor(MyHlBlock::class)`.
3. **При изменениях** (добавили/переименовали поле в `getMap()`):
   - снова запускаете `update()` — таблица и UF-поля будут синхронизированы.
4. **Для удаления HL-блоков** (например, при деинсталляции модуля):
   - вызываете `$manager->deleteAll()` или `$manager->dropFor(MyHlBlock::class)`.

---

## Пример: объявление HL-блока и миграции

Условный пример описания HL-блока:

```php
use MB\Bitrix\HighloadBlock\Base;
use MB\Bitrix\HighloadBlock\Fields\StringField;

class HlUserToken extends Base
{
    public static function getTableName(): string
    {
        return 'hl_user_token';
    }

    public static function getName(): string
    {
        return 'UserToken';
    }

    public static function getLang(): array
    {
        return ['ru' => 'Токены пользователей'];
    }

    public static function getMap(): array
    {
        return [
            new StringField('UF_USER_ID', 'ID пользователя'),
            new StringField('UF_TOKEN', 'Токен'),
        ];
    }
}
```

Миграция/инсталлятор:

```php
use MB\Bitrix\HighloadBlock\HighloadBlockManager;

class InstallStep
{
    public function up(\MB\Bitrix\Contracts\Module\Entity $moduleEntity): void
    {
        $manager = HighloadBlockManager::create($moduleEntity);
        $result = $manager->createFor(HlUserToken::class);

        if (!$result->isSuccess()) {
            // обработать ошибки
        }
    }
}
```

Таким образом, схема HL-блока находится под контролем версий кода и может синхронизироваться на любых окружениях (dev/stage/prod) через единый механизм миграций.

