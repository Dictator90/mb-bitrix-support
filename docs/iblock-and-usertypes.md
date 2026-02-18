## Инфоблоки и пользовательские типы полей

Пакет содержит ряд утилит для работы с инфоблоками и пользовательскими типами полей Bitrix.  
Они облегчают:

- проверку/назначение `API_CODE` инфоблока;
- генерацию детальных URL элементов/разделов;
- декларативное описание пользовательских типов инфоблоков и «диалоговых» селекторов.

---

## Утилиты для инфоблоков

### Класс `Iblock\Helper`

Файл: `src/Iblock/Helper.php`  
Пространство имён: `MB\Bitrix\Iblock`

Основные методы:

- **`hasApiCode($iblockId, $cacheTtl = 86400): bool`**
  - выполняет запрос к `Bitrix\Iblock\IblockTable`:
    - `select`: `ID`, `CODE`, `API_CODE`;
    - `where`: `ID = $iblockId`;
    - `setCacheTtl($cacheTtl)`;
  - возвращает `true`, если у инфоблока заполнено поле `API_CODE`.

- **`issetOrSetApiCode($iblockId, $cacheTtl = 86400): \Bitrix\Main\Result`**
  - загружает инфоблок по ID;
  - если `API_CODE` пуст:
    - берёт `CODE` инфоблока;
    - если `CODE` пуст или не проходит валидацию `^[a-z][a-z0-9]{0,49}$`:
      - строит код вида `iblock{$iblockId}`;
    - записывает значение в `API_CODE` и сохраняет ORM-объект;
  - при ошибках добавляет их в `Result`.

**Типичные сценарии:**

- приведение инфоблоков к корректному `API_CODE` перед использованием REST/ORM;
- быстрое добавление API-кода там, где он не был задан при создании инфоблока.

Пример:

```php
use MB\Bitrix\Iblock\Helper;

$result = Helper::issetOrSetApiCode($iblockId);
if (!$result->isSuccess()) {
    // обработать ошибки
}
```

---

### Класс `Iblock\DetailUrl`

Файл: `src/Iblock/DetailUrl.php`

Назначение: **универсальная генерация детальных URL** для элементов и разделов инфоблоков на основе шаблонов Bitrix.

Поддерживает:

- загрузку данных по элементу/разделу (через ORM-таблицы);
- вычисление `SECTION_CODE_PATH` через подзапрос (`GROUP_CONCAT` по цепочке разделов);
- замену плейсхолдеров в шаблоне URL вида:
  - `#ID#`, `#CODE#`, `#IBLOCK_ID#`, `#IBLOCK_TYPE_ID#`, `#SECTION_ID#`, `#SECTION_CODE#`, `#SECTION_CODE_PATH#`,
  - `#ELEMENT_ID#`, `#EXTERNAL_ID#`, `#SERVER_NAME#`, `#SITE_DIR#`, и т.д.

Ключевые методы:

- **`getByElement(string|int $id, ?string $template = null, ?string $siteId = null): ?string`**
  - строит `Query` на основе `Bitrix\Iblock\ElementTable`;
  - добавляет необходимые поля и runtime-поле `SECTION_CODE_PATH` (через `ExpressionField`);
  - подставляет значения в шаблон:
    - либо переданный `$template`;
    - либо `IBLOCK.DETAIL_PAGE_URL` (через псевдополе `DETAIL_PAGE_URL_SCHEMA`);
  - возвращает итоговый URL или `null`, если зависимости не удовлетворены.

- **`getBySection(string|int $id, ?string $template = null, ?string $siteId = null): ?string`**
  - аналогично `getByElement`, но на базе `Bitrix\Iblock\SectionTable`;
  - по умолчанию использует `IBLOCK.SECTION_PAGE_URL` как шаблон.

- **`buildByElement(array $row, ?string $template = null, ?string $siteId = null): string`**
  - принимает уже загруженную строку с полями (например, из сложного ORM-запроса);
  - строит URL через `getElementReplace()`.

- **`buildBySection(array $row, ?string $template = null, ?string $siteId = null): string`**
  - аналогично `buildByElement()`, но для разделов.

Внутренние методы:

- `addElementUrlDataToQuery(Query $query)` / `addSectionUrlDataToQuery(Query $query)`
  - добавляют нужные select-поля и runtime-поле `SECTION_CODE_PATH` в запрос;
- `getElementReplace(array $data, $siteId = null)` / `getSectionReplace(array $data, $siteId = null)`
  - формируют массив `['#PLACEHOLDER#' => 'value']` для `str_replace`;
- `getBaseReplace(array $data, $siteId = null)`:
  - заполняет плейсхолдеры по данным элемента/раздела;
  - подставляет `SERVER_NAME` и `SITE_DIR` текущего/заданного сайта (`SiteTable`/`Application`).

Пример:

```php
use MB\Bitrix\Iblock\DetailUrl;

$url = DetailUrl::getByElement($elementId);
// или
$url = DetailUrl::getBySection($sectionId, '/catalog/#SECTION_CODE_PATH#/#CODE#/');
```

---

## Пользовательские типы инфоблоков

### Базовый класс `Iblock\UserType\Base`

Файл: `src/Iblock/UserType/Base.php`  
Пространство имён: `MB\Bitrix\Iblock\UserType`

Реализует интерфейс `MB\Bitrix\Contracts\Iblock\UserTypeInterface` и задаёт каркас для пользовательских свойств инфоблоков.

Вы должны реализовать:

- **`public static function getUserType(): string`**
  - строковый идентификатор пользовательского типа (используется в `USER_TYPE`);
- **`public static function getPropertyType(): string`**
  - тип свойства (`'S'`, `'E'`, `'N'`, `'F'`, `'G'`, `'L'`);
  - соответствует константам из `\Bitrix\Iblock\PropertyTable`;
- **`public static function getDescription(): string`**
  - человекочитаемое название пользовательского свойства.

Метод:

- **`getUserTypeDescription(): array`**
  - возвращает массив для регистрации пользовательского типа в Bitrix:

```php
[
  'PROPERTY_TYPE' => static::getPropertyType(),
  'USER_TYPE'     => static::getUserType(),
  'DESCRIPTION'   => static::getDescription(),
  'GetPropertyFieldHtml'       => [static::class, 'getPropertyFieldHtml'],
  'GetPropertyFieldHtmlMulty'  => [static::class, 'getPropertyFieldHtmlMulty'],
  'GetPublicViewHTML'          => [static::class, 'getPublicViewHTML'],
]
```

Также доступны:

- `getModuleDependence(): array` — список ID модулей, от которых зависит пользовательский тип (по умолчанию пустой);
- `checkDependence(): bool` — проверяет `ModuleManager::isModuleInstalled()` для всех модулей;
- `getDependenceErrorMessage(): string` — текст ошибки при отсутствии модулей.

Регистрация пользовательского типа обычно происходит через `OnIBlockPropertyBuildList`.

---

### Диалоговый селектор `Iblock\UserType\DialogSelector\Base`

Файл: `src/Iblock/UserType/DialogSelector/Base.php`

Абстрактный класс для пользовательских типов в виде **диалога выбора сущностей** с использованием `ui.entity-selector`.

Наследует `UserTypeBase` и добавляет:

- абстрактный метод:

```php
/**
 * Массив элементов для диалога выбора (ItemOptions)
 * @return array
 */
abstract protected static function getItems(): array;
```

Остальные методы реализуют:

- рендеринг формы в админке (в том числе для множественных свойств);
- инициализацию `BX.UI.EntitySelector.TagSelector` с переданными элементами.

Ключевые методы:

- **`getPropertyType(): string`**
  - всегда возвращает `'S'` (строковый тип хранения, ID выбранных элементов в строковом виде);

- **`getPropertyFieldHtml($arProperty, $value, $strHTMLControlName)`**
  - если зависимостей не хватает (`checkDependence() === false`) — возвращает сообщение об ошибке;
  - иначе:
    - для одиночного свойства:
      - выводит div‑контейнеры для селектора и скрытых `<input>` с выбранными значениями;
      - подключает JS‑код инициализации TagSelector через `getTagSelectorScript()`.

- **`getPropertyFieldHtmlMulty($arProperty, $values, $strHTMLControlName)`**
  - аналогично, но для множественного свойства:
    - генерирует несколько скрытых `<input>` для каждого значения.

- **`getPublicViewHTML($arProperty, $value, $strHTMLControlName)`**
  - возвращает строковое представление значения (для публичной части).

- **`getTagSelectorScript($arProperty, $value, $strHTMLControlName): string`**
  - подключает расширение `ui.entity-selector`;
  - помечает элементы из `getItems()` как выбранные в зависимости от текущего значения свойства;
  - возвращает JS‑скрипт, который:
    - инициализирует `BX.UI.EntitySelector.TagSelector`;
    - обрабатывает события `onAfterTagAdd` / `onAfterTagRemove` для синхронизации скрытых `<input>` со значениями в форме.

#### Пример наследника диалогового селектора

```php
use MB\Bitrix\Iblock\UserType\DialogSelector\Base as DialogSelectorBase;

class MyEntitySelector extends DialogSelectorBase
{
    public static function getUserType(): string
    {
        return 'my_entity_selector';
    }

    public static function getDescription(): string
    {
        return 'Выбор сущности моего модуля';
    }

    protected static function getItems(): array
    {
        // Вернуть массив объектов для TagSelector: id, title, entityId, и т.д.
        return [
            ['id' => 1, 'title' => 'Элемент 1'],
            ['id' => 2, 'title' => 'Элемент 2'],
        ];
    }
}
```

После регистрации пользовательского типа вы сможете использовать этот селектор в настройках свойства инфоблока.

---

## Рекомендации по использованию

- При работе с шаблонами детальных страниц:
  - используйте `Iblock\DetailUrl` для построения URL на стороне PHP, особенно если:
    - вы получаете данные напрямую через ORM;
    - нужно учесть `SECTION_CODE_PATH`, `SERVER_NAME`, `SITE_DIR` и др.

- Перед использованием `API_CODE` инфоблока:
  - прогоните все нужные инфоблоки через `Iblock\Helper::issetOrSetApiCode()` во время установки/обновления модуля.

- Для пользовательских типов:
  - наследуйте `Iblock\UserType\Base` или `DialogSelector\Base` и описывайте всё поведение в одном классе;
  - используйте `getModuleDependence()` для проверки наличия зависимых модулей (`ui`, ваши модули и т.п.).

Это позволяет централизованно контролировать поведение инфоблоков, URL и пользовательских типов, а также упростить их настройку и использование в интерфейсе админки Bitrix.

