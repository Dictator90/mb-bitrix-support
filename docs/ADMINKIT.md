# AdminKit — документация

> **📦 Пакет:** Это документация для отдельного пакета [`mb4it/bitrix-admin-kit`](https://github.com/mb4it/bitrix-admin-kit). AdminKit содержит классы для построения административных страниц и был выделен из `mb4it/bitrix-support` в v1.

Конструктор административных страниц для Bitrix D7-модулей.
Вдохновлён [MoonShine](https://moonshine-laravel.com/), полностью построен на Bitrix-классах.

---

## Содержание

1. [Быстрый старт](#быстрый-старт)
2. [Resource](#resource)
3. [Fields — поля](#fields)
   - [Базовые поля](#базовые-поля)
   - [Расширенные поля](#расширенные-поля)
   - [Selector-поля](#selector-поля)
   - [Preview — отображение без ввода](#preview)
   - [Fluent API и валидация](#fluent-api-и-валидация)
4. [Layout-компоненты](#layout-компоненты)
5. [Filters — фильтры](#filters)
6. [Row-actions и Bulk-actions](#actions)
7. [Tabs — вкладки](#tabs)
8. [Reactive fields](#reactive-fields)
9. [AdminKitManager — роутинг](#adminkitmanager)
10. [Async actions](#async-actions)
11. [CSV-экспорт](#csv-экспорт)
12. [Миграция с Settings / UI](#миграция-с-settings--ui)

---

## Быстрый старт

### 1. Создайте Resource

```php
// my.module/lib/Admin/OrderResource.php
namespace My\Module\Admin;

use MB\Bitrix\AdminKit\Field\{ID, Text, Select, Date, Switcher};
use MB\Bitrix\AdminKit\Filter\Types\{TextFilter, SelectFilter, DateFilter};
use MB\Bitrix\AdminKit\Action\RowAction;
use MB\Bitrix\AdminKit\Resource\Resource;
use My\Module\ORM\OrderTable;

class OrderResource extends Resource
{
    protected string $title             = 'Заказы';
    protected ?string $dataManagerClass = OrderTable::class;
    protected ?string $moduleId         = 'my.module';

    public static function getId(): string    { return 'orders'; }
    public static function getSort(): int     { return 10; }
    public static function getMenuIcon(): string { return 'adm-menu-crm'; }

    public function indexFields(): iterable
    {
        return [
            ID::make('ID', 'ID'),
            Text::make('Номер', 'NUMBER'),
            Select::make('Статус', 'STATUS')->options(['new' => 'Новый', 'done' => 'Выполнен']),
            Date::make('Дата', 'DATE_CREATE'),
            Switcher::make('Активен', 'ACTIVE'),
        ];
    }

    public function formFields(): iterable
    {
        return [
            Text::make('Номер', 'NUMBER')->required(),
            Select::make('Статус', 'STATUS')->options(['new' => 'Новый', 'done' => 'Выполнен']),
            Switcher::make('Активен', 'ACTIVE'),
        ];
    }

    public function filters(): iterable
    {
        return [
            TextFilter::make('Номер', 'NUMBER'),
            SelectFilter::make('Статус', 'STATUS')->options(['new' => 'Новый', 'done' => 'Выполнен']),
            DateFilter::make('Дата', 'DATE_CREATE'),
        ];
    }

    public function rowActions(): iterable
    {
        return [RowAction::edit(), RowAction::view(), RowAction::delete()];
    }
}
```

### 2. Admin-файл (3 строки)

```php
// /bitrix/admin/my_module.php
require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php";
module('my.module')->adminKit()->getCurrentPage()->render();
require_once $_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/include/epilog_admin.php";
```

### 3. menu.php

```php
// /local/modules/my.module/admin/menu.php
use Bitrix\Main\Loader;
Loader::includeModule('my.module');

return [
    'parent_menu' => 'global_menu_services',
    'section'     => 'my_module',
    'sort'        => 100,
    'text'        => 'Мой модуль',
    'items'       => module('my.module')->adminKit()->getMenu('/bitrix/admin/my_module.php'),
];
```

---

## Resource

Базовый класс `MB\Bitrix\AdminKit\Resource\Resource`.

### Свойства

| Свойство | Тип | Описание |
|---|---|---|
| `$title` | `string` | Заголовок страницы/меню |
| `$dataManagerClass` | `?string` | Класс ORM DataManager (null = нет CRUD) |
| `$primaryKey` | `string` | Первичный ключ (по умолчанию `'ID'`) |
| `$moduleId` | `?string` | ID модуля — нужен для `OptionsPage` |
| `$multiSiteOptions` | `bool` | Мультисайтовые опции |

### Статические методы (для меню/роутинга)

| Метод | По умолчанию | Описание |
|---|---|---|
| `getId()` | Имя класса без суффикса `Resource` в lowercase | Slug для `?page=` |
| `getSort()` | `100` | Порядок в меню |
| `getMenuIcon()` | `''` | CSS-класс иконки |
| `isVisibleInMenu()` | `true` | Скрыть из меню |
| `getParentMenuId()` | `null` | Вложить под другой ресурс |

### Переопределяемые методы

```php
public function indexFields(): iterable   // поля Grid-таблицы
public function formFields(): iterable    // поля формы создания/редактирования
public function detailFields(): iterable  // поля детальной страницы (= formFields по умолчанию)
public function filters(): iterable       // фильтры для Grid
public function optionFields(): iterable  // поля настроек (b_option)
public function rowActions(): iterable    // действия в строке Grid
public function bulkActions(): iterable   // массовые действия
public function asyncActions(): iterable  // AJAX-действия
public function formTabs(): iterable      // вкладки формы
public function optionTabs(): iterable    // вкладки настроек
```

### Lifecycle hooks

```php
protected function beforeCreating(DataWrapper $item): DataWrapper { return $item; }
protected function afterCreated(DataWrapper $item): DataWrapper   { return $item; }
protected function beforeUpdating(DataWrapper $item): DataWrapper { return $item; }
protected function afterUpdated(DataWrapper $item): DataWrapper   { return $item; }
protected function beforeDeleting(DataWrapper $item): void {}
protected function afterDeleted(DataWrapper $item): void {}
```

### Только-настройки Resource (без CRUD)

```php
class SettingsResource extends Resource
{
    protected string $title    = 'Настройки';
    protected ?string $moduleId = 'my.module';

    public function indexFields(): iterable { return []; }
    public function formFields(): iterable  { return []; }

    public function optionFields(): iterable
    {
        return [
            Text::make('API ключ', 'api_key')->required(),
            Number::make('Таймаут (сек)', 'timeout')->default(30),
            Switcher::make('Debug', 'debug_mode'),
        ];
    }
}
```

---

## Fields

Все поля находятся в `MB\Bitrix\AdminKit\Field\*`.

Любое поле реализует `FieldContract` и участвует в:
- **Grid** — отображение столбца, форматирование ячейки
- **Form** — HTML-элемент для создания/редактирования
- **Options** — элемент на странице настроек
- **Filter** — поле фильтрации (если поле задаёт `getFilterType()`)

### Базовые поля

| Класс | HTML-элемент | Особенности |
|---|---|---|
| `Text` | `<input type="text">` | `maxLength()` |
| `Number` | `<input type="number">` | `min()`, `max()`, `step()` |
| `Email` | `<input type="email">` | Браузерная валидация формата |
| `Password` | `<input type="password">` | Значение никогда не отображается повторно; скрыт в Grid/Detail |
| `Textarea` | `<textarea>` | `rows()` |
| `Select` | `<select>` | `options()`, `placeholder()`, `multiple()` |
| `Checkbox` | `<input type="checkbox">` | `values(checked, unchecked)`, по умолчанию `Y`/`N` |
| `Switcher` | Битриксовый `BX.UI.Switcher` | `values(checked, unchecked)`, по умолчанию `Y`/`N` |
| `Date` | Битриксовый датапикер | `dateFormat('d.m.Y')` |
| `DateTime` | Датапикер с временем | |
| `Hidden` | `<input type="hidden">` | |
| `ID` | Только чтение | Отображает первичный ключ; скрыт в форме |

### Расширенные поля

| Класс | Описание |
|---|---|
| `File` | Загрузка файла через `CFile`. Хранит ID файла в БД |
| `Image` | Загрузка изображения с превью в Grid. Extends `File` |
| `Html` | WYSIWYG через `bitrix:main.html.editor`, fallback на `<textarea>` |
| `Color` | Нативный `<input type="color">` + hex-поле |

### Selector-поля

Поля для выбора связанных сущностей через `BX.UI.EntitySelector.Dialog`.
После выбора отображают чипы с кнопкой удаления; значения хранятся как скрытые `<input>`.

| Класс | Описание |
|---|---|
| `EntitySelect` | Базовый: настраивается вручную под любой entity-provider |
| `UserSelect` | Выбор пользователей Bitrix. Названия загружаются из `UserTable` |
| `IblockSelect` | Выбор инфоблоков. Названия из `CIBlock` |
| `IblockElementSelect` | Выбор элементов инфоблока. Принимает `iblockId` |

#### EntitySelect

```php
use MB\Bitrix\AdminKit\Field\EntitySelect;

// Ручная настройка под произвольный entity-provider
EntitySelect::make('Менеджер', 'MANAGER_ID')
    ->entity('user-list', ['dynamicLoad' => true, 'dynamicSearch' => true])
    ->preselectedEntityId('user')           // entityId для BX.UI.EntitySelector.Dialog::preselectedItems
    ->resolveLabels(fn(array $ids) => [...])// fn(string[] $ids): array<id, string> — подгрузка заголовков
```

| Метод | Описание |
|---|---|
| `->entity(string $id, array $options = [])` | Добавить entity. `$id` — провайдер (напр. `'user-list'`). Можно вызывать несколько раз |
| `->multiple()` | Множественный выбор |
| `->preselectedEntityId(string $id)` | EntityId для `preselectedItems` при открытии диалога |
| `->resolveLabels(Closure $fn)` | `fn(array $ids): array<id, title>` — для отображения текущих значений |

#### UserSelect

```php
use MB\Bitrix\AdminKit\Field\UserSelect;

UserSelect::make('Ответственный', 'MANAGER_ID')

UserSelect::make('Участники', 'MEMBER_IDS')
    ->multiple()
```

Автоматически:
- подключает entity `user-list` с `dynamicLoad`/`dynamicSearch`
- резолвит `NAME LAST_NAME` (или `LOGIN`) через `UserTable`

#### IblockSelect

```php
use MB\Bitrix\AdminKit\Field\IblockSelect;

IblockSelect::make('Инфоблок', 'IBLOCK_ID')

IblockSelect::make('Каталоги', 'IBLOCK_IDS')
    ->multiple()
```

Подключает entity `iblock-list`, резолвит `NAME` через `CIBlock`.
Требует модуль `iblock`.

#### IblockElementSelect

```php
use MB\Bitrix\AdminKit\Field\IblockElementSelect;

// Третий параметр make() — ID инфоблока (обязателен для фильтрации)
IblockElementSelect::make('Статья', 'ARTICLE_ID', iblockId: 12)

IblockElementSelect::make('Товары', 'PRODUCT_IDS', iblockId: 2)
    ->multiple()

// iblockId = 0 — выбор из всех элементов (все инфоблоки)
IblockElementSelect::make('Элемент', 'ELEMENT_ID')
```

> **Сигнатура `make()`:** `IblockElementSelect::make(string $label, ?string $column = null, int $iblockId = 0)`  
> Именованный аргумент `iblockId:` рекомендуется для читаемости.

Резолвит `NAME` элемента через `CIBlockElement`. Требует модуль `iblock`.

---

### Preview

`MB\Bitrix\AdminKit\Field\Preview` — аналог MoonShine `Preview`.

**Только для отображения** — не рендерит `<input>`, не участвует в сохранении.
Используется для вычисляемых/форматированных значений в Grid, форме и на странице настроек.

```php
use MB\Bitrix\AdminKit\Field\Preview;

// Отображает сырое значение из БД
Preview::make('Статус', 'STATUS')

// Цветной бейдж (Bitrix ui-label)
Preview::make('Статус', 'STATUS')
    ->badge('success')                    // success | danger | warning | info | default

// Динамический бейдж по значению
Preview::make('Статус', 'STATUS')
    ->format(fn($v) => match($v) {
        'active'   => '<span class="ui-label ui-label-green">Активен</span>',
        'inactive' => '<span class="ui-label ui-label-gray">Неактивен</span>',
        default    => htmlspecialcharsbx($v),
    })

// Ссылка — значение поля используется как href
Preview::make('URL', 'DETAIL_URL')
    ->link()                              // target="_blank" по умолчанию
    ->link('_self')                       // в текущей вкладке

// Произвольный HTML через format() или preview()
Preview::make('Цена', 'PRICE')
    ->format(fn($v) => '<b>' . number_format((float)$v, 2) . '</b> ₽')

Preview::make('Фото превью', 'PHOTO_ID')
    ->preview(fn($id) => $id ? '<img src="' . \CFile::GetFileSRC(\CFile::GetByID($id)->Fetch()) . '" style="max-height:40px">' : '—')
```

| Метод | Описание |
|---|---|
| `->badge(string $color)` | Обернуть в `<span class="ui-label ui-label-{color}">` |
| `->link(string $target = '_blank')` | Обернуть в `<a href="{value}">` |
| `->format(Closure $fn)` | `fn(mixed $value): string` — произвольный HTML |
| `->preview(Closure $fn)` | Алиас для `format()`, используется для отображения в Grid |

**Цвета бейджей:**

| Ключ | Класс Bitrix |
|---|---|
| `success` | `ui-label-green` |
| `danger` / `error` | `ui-label-red` |
| `warning` | `ui-label-yellow` |
| `info` / `primary` | `ui-label-blue` |
| `default` | `ui-label-gray` |
| произвольная строка | используется как суффикс `ui-label-{value}` |

> `Preview` помечает поле как `isReadOnly() === true`. `FormPage` и `OptionsPage` автоматически исключают такие поля из POST-обработки и сохранения в БД.

---

### Fluent API и валидация

Методы доступны на всех полях:

```php
Text::make('Название', 'NAME')
    ->required()                          // обязательное поле
    ->default('Новый')                    // значение по умолчанию
    ->hint('Подсказка')                   // иконка (?) с тултипом
    ->sortable(false)                     // отключить сортировку в Grid
    ->editable()                          // inline-edit в Grid
    ->hideOnIndex()                       // скрыть в Grid
    ->hideOnForm()                        // скрыть в форме
    ->format(fn($v) => strtoupper($v))   // форматирование в Grid-ячейке
```

```php
Select::make('Статус', 'STATUS')
    ->options(['new' => 'Новый', 'done' => 'Выполнен'])
    ->placeholder('Выберите статус')
    ->multiple()
```

```php
Number::make('Цена', 'PRICE')
    ->min(0)
    ->max(999999)
    ->step(0.01)
```

```php
Switcher::make('Активен', 'ACTIVE')
    ->values('Y', 'N')     // values($checkedValue, $uncheckedValue)
```

```php
Date::make('Дата', 'DATE_CREATE')
    ->dateFormat('d.m.Y')
```

```php
Image::make('Фото', 'PHOTO_ID')
    ->previewSize(120, 120)   // размер превью в Grid
```

```php
Html::make('Описание', 'DESCRIPTION')
    ->rows(20)
    ->disableEditor()   // только <textarea>, без WYSIWYG
```

```php
Color::make('Цвет темы', 'THEME_COLOR')
    ->defaultColor('#3bc8e7')
```

#### Валидация

```php
use MB\Bitrix\AdminKit\Support\Validation\Rules;

Text::make('Email', 'EMAIL')
    ->required()
    ->email()

Text::make('Сайт', 'URL')
    ->url()

Number::make('Количество', 'QTY')
    ->min(1)
    ->max(100)
    ->numeric()

Text::make('Код', 'CODE')
    ->maxLength(50)
    ->pattern('/^[a-z0-9_]+$/', 'Только строчные латинские, цифры и _')
    ->validate(function ($value) {
        if (str_contains($value, 'reserved')) {
            return 'Это слово зарезервировано';
        }
        return true; // или null / '' = ок
    })
```

---

## Layout-компоненты

Используются внутри `formFields()`, `optionFields()`, `components()` и внутри `Tab`.

### Box (секция с заголовком)

```php
use MB\Bitrix\AdminKit\Component\Layout\Box;

// Без заголовка
Box::make([
    Email::make('Email', 'EMAIL'),
    Text::make('Телефон', 'PHONE'),
])

// С заголовком
Box::make('Контактные данные', [
    Email::make('Email', 'EMAIL'),
    Text::make('Телефон', 'PHONE'),
])

// Раскрывашка, раскрыта по умолчанию
Box::make('Настройки', [...])->collapsible()

// Раскрывашка, свёрнута по умолчанию
Box::make('Настройки', [...])->collapsible(true)
```

### Flex

```php
use MB\Bitrix\AdminKit\Component\Layout\Flex;

Flex::make([
    Text::make('Имя', 'FIRST_NAME'),
    Text::make('Фамилия', 'LAST_NAME'),
])->gap(16)->justify('between')
```

### Grid + Column (12-колоночный)

```php
use MB\Bitrix\AdminKit\Component\Layout\{Grid, Column};

Grid::make([
    Column::make([Text::make('Название', 'NAME')])->span(8),
    Column::make([Select::make('Роль', 'ROLE')])->span(4),
])
```

### Collapse (без JS, через `<details>`)

```php
use MB\Bitrix\AdminKit\Component\Layout\Collapse;

Collapse::make('Дополнительные настройки', [
    Text::make('Примечание', 'NOTE'),
])->open()    // по умолчанию раскрыта
```

### Divider, LineBreak

```php
use MB\Bitrix\AdminKit\Component\Layout\{Divider, LineBreak};

Divider::make()           // горизонтальная линия
Divider::make('или')      // с подписью посередине

LineBreak::make()         // 16px отступ
LineBreak::make(32)       // кастомная высота
```

### Alert, Badge, Heading

```php
use MB\Bitrix\AdminKit\Component\{Alert, Badge, Heading};

Alert::success('Настройки применены')
Alert::danger('Ошибка подключения')->closable()
Alert::warning('Проверьте заполнение')
Alert::info('Поля со * обязательны')

Badge::make('Активен')->success()
Badge::make('Черновик')->neutral()->pill()
Badge::make($status)->map(['Y' => 'success', 'N' => 'danger'])

Heading::make('Контактные данные')
Heading::make('Дополнительно', 3)->subtitle('Необязательные поля')
```

### Tabs (вкладки в компонентах)

Вкладки внутри `components()` (OptionsPage) или `formFields()` оборачиваются в `Tabs::make([...])`.

```php
use MB\Bitrix\AdminKit\Component\Layout\Tabs;
use MB\Bitrix\AdminKit\Support\Tab;

Tabs::make([
    Tab::make('Основное', [
        Text::make('API ключ', 'api_key'),
    ])->id('main')->active(),

    Tab::make('Дополнительно', [
        Switcher::make('Debug', 'debug'),
    ])->id('advanced'),
])
```

Переключение вкладок сопровождается анимацией появления через `opacity` (fade-in при открытии, fade-out при закрытии).

---

## Filters

```php
use MB\Bitrix\AdminKit\Filter\Types\{TextFilter, SelectFilter, DateFilter, NumberFilter, CheckboxFilter};

public function filters(): iterable
{
    return [
        TextFilter::make('Имя', 'NAME'),
        SelectFilter::make('Статус', 'STATUS')
            ->options(['new' => 'Новый', 'done' => 'Выполнен']),
        DateFilter::make('Дата создания', 'DATE_CREATE'),
        NumberFilter::make('Количество', 'COUNT'),
        CheckboxFilter::make('Только активные', 'ACTIVE'),
    ];
}
```

---

## Actions

### Row-actions (строки Grid)

```php
use MB\Bitrix\AdminKit\Action\RowAction;

public function rowActions(): iterable
{
    return [
        RowAction::edit(),     // открыть форму редактирования в SidePanel
        RowAction::view(),     // открыть детальную страницу в SidePanel
        RowAction::delete(),   // удалить с подтверждением
    ];
}
```

### Bulk-actions (массовые)

```php
use MB\Bitrix\AdminKit\Action\BulkAction;

public function bulkActions(): iterable
{
    return [
        BulkAction::delete(),
        BulkAction::make('activate', 'Активировать')
            ->handle(function (array $ids, Resource $resource) {
                foreach ($ids as $id) {
                    $resource->getDataManagerClass()::update($id, ['ACTIVE' => 'Y']);
                }
            }),
    ];
}
```

---

## Tabs

Вкладки в форме Resource (FormPage) задаются через `formTabs()`.

```php
use MB\Bitrix\AdminKit\Support\Tab;

public function formTabs(): iterable
{
    return [
        Tab::make('Основное', [
            Text::make('Название', 'TITLE')->required(),
            Textarea::make('Описание', 'DESCRIPTION'),
        ])->id('main')->active(),

        Tab::make('Контакты', [
            Email::make('Email', 'EMAIL'),
            Text::make('Телефон', 'PHONE'),
        ])->id('contacts'),

        Tab::make('Настройки', [
            Heading::make('Отображение'),
            Switcher::make('Активен', 'ACTIVE'),
            Divider::make(),
            Collapse::make('Дополнительно', [
                Hidden::make('SORT')->default('500'),
            ]),
        ])->id('settings'),
    ];
}
```

Вкладки в `OptionsPage` (standalone) задаются через `components()` с `Tabs::make([...])` — см. [секцию Layout: Tabs](#tabs-вкладки-в-компонентах).

---

## Reactive fields

Поле, изменение которого динамически обновляет другие поля через AJAX.

```php
Select::make('Тип', 'TYPE')
    ->options(['physical' => 'Физ. лицо', 'legal' => 'Юр. лицо'])
    ->onChange('COMPANY_NAME', function ($value, array $allData) {
        return $value === 'legal' ? ($allData['TITLE'] ?? '') : '';
    })
    ->onChange('INN', fn($value) => '')

// Возврат массива опций для Select-поля
Select::make('Страна', 'COUNTRY')
    ->options(['ru' => 'Россия', 'us' => 'США'])
    ->onChange('CITY', function ($value, array $allData) {
        return match ($value) {
            'ru' => [['value' => 'msk', 'label' => 'Москва'], ['value' => 'spb', 'label' => 'Питер']],
            'us' => [['value' => 'nyc', 'label' => 'New York'], ['value' => 'la',  'label' => 'Los Angeles']],
            default => [],
        };
    })
```

---

## AdminKitManager

```php
// Получить экземпляр
$kit = module('my.module')->adminKit();

// Текущая страница — рендер
$kit->getCurrentPage()->render();

// Меню для Bitrix admin sidebar
$kit->getMenu('/bitrix/admin/my_module.php');

// Явная регистрация (если auto-discovery недостаточно)
$kit->register(OrderResource::class)
    ->register(UserResource::class);
```

### Auto-discovery

`AdminKitManager` автоматически сканирует `{module_path}/lib` на все классы, расширяющие `Resource`. Явная регистрация не требуется.

### URL-схема

```
?page=orders                      → IndexPage (список)
?page=orders&action=list          → IndexPage
?page=orders&action=edit&id=5     → FormPage (редактирование #5)
?page=orders&action=add           → FormPage (создание)
?page=orders&action=options       → OptionsPage (настройки)
```

---

## Async actions

```php
use MB\Bitrix\AdminKit\Action\AsyncAction;

class SendEmailAction extends AsyncAction
{
    public function getId(): string { return 'send_email'; }

    public function handle(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        // ... логика ...
        return ['message' => 'Email отправлен'];
    }
}

// В Resource:
public function asyncActions(): iterable
{
    return [new SendEmailAction()];
}
```

Вызов из JS:
```js
fetch('?async_action=send_email&sessid=' + BX.bitrix_sessid(), {
    method: 'POST',
    body: JSON.stringify({ id: 42 })
})
.then(r => r.json())
.then(data => console.log(data.data.message))
```

---

## CSV-экспорт

Доступен автоматически по URL `?page=orders&action=export`.
Кнопка появляется в Toolbar на IndexPage.

Форматирование значений настраивается через `previewValue()` или `format()` на полях.

---

## Как создать страницу для своего модуля

### Структура файлов

```
my.module/
├── admin/
│   ├── my_module.php        ← admin-файл (3 строки)
│   └── menu.php
└── lib/
    └── Admin/
        ├── ProductResource.php   ← CRUD-ресурс
        ├── CategoryResource.php
        └── SettingsResource.php  ← только настройки
```

### Пример: только настройки (без CRUD)

```php
namespace My\Module\Admin;

use MB\Bitrix\AdminKit\Field\{Text, Number, Select, Switcher, Preview};
use MB\Bitrix\AdminKit\Resource\Resource;

class SettingsResource extends Resource
{
    protected string $title    = 'Настройки';
    protected ?string $moduleId = 'my.module';
    protected bool $multiSiteOptions = true;

    public static function getId(): string { return 'settings'; }
    public static function getSort(): int  { return 500; }

    public function indexFields(): iterable { return []; }
    public function formFields(): iterable  { return []; }

    public function optionFields(): iterable
    {
        return [
            Text::make('API-ключ', 'api_key')->required(),
            Number::make('Таймаут (сек)', 'timeout')->default(30),
            Select::make('Режим', 'mode')->options(['prod' => 'Продакшн', 'dev' => 'Разработка']),
            Switcher::make('Отладка', 'debug'),
        ];
    }
}
```

### Пример: CRUD-ресурс с вкладками формы и selector-полями

```php
namespace My\Module\Admin;

use MB\Bitrix\AdminKit\Field\{ID, Text, Number, Select, Switcher, Image, UserSelect, Preview};
use MB\Bitrix\AdminKit\Component\Layout\{Grid, Column, Box};
use MB\Bitrix\AdminKit\Filter\Types\{TextFilter, SelectFilter};
use MB\Bitrix\AdminKit\Action\RowAction;
use MB\Bitrix\AdminKit\Resource\Resource;
use MB\Bitrix\AdminKit\Support\Tab;

class ProductResource extends Resource
{
    protected string $title             = 'Товары';
    protected ?string $dataManagerClass = \My\Module\ORM\ProductTable::class;
    protected ?string $moduleId         = 'my.module';

    public static function getId(): string { return 'products'; }

    public function indexFields(): iterable
    {
        return [
            ID::make('ID'),
            Text::make('Название', 'NAME'),
            Number::make('Цена', 'PRICE'),
            Preview::make('Статус', 'STATUS')->badge('success'),
            Switcher::make('Активен', 'ACTIVE')->editable(),
        ];
    }

    public function filters(): iterable
    {
        return [
            TextFilter::make('Название', 'NAME'),
            SelectFilter::make('Активен', 'ACTIVE')->options(['Y' => 'Да', 'N' => 'Нет']),
        ];
    }

    public function formTabs(): iterable
    {
        return [
            Tab::make('Основное', [
                Grid::make([
                    Column::make([Text::make('Название', 'NAME')->required()])->span(8),
                    Column::make([Number::make('Цена', 'PRICE')])->span(4),
                ]),
                UserSelect::make('Менеджер', 'MANAGER_ID'),
            ])->id('main')->active(),

            Tab::make('Медиа', [
                Image::make('Фото', 'PHOTO'),
            ])->id('media'),

            Tab::make('Настройки', [
                Switcher::make('Активен', 'ACTIVE'),
                Number::make('Сортировка', 'SORT')->default(500),
            ])->id('settings'),
        ];
    }

    public function rowActions(): iterable
    {
        return [RowAction::edit(), RowAction::delete()];
    }
}
```

---

## Миграция с Settings / UI

### Что чем заменяется

| Старый код | AdminKit |
|---|---|
| `Settings\Page\Entity\Base` | `AdminKit\Resource\Resource` |
| `Settings\Page\Entity\OptionsPage` + `Options\Collection` | `Resource::optionFields()` |
| `Settings\Page\Entity\EntityViewPage` + `EntityView\Builder` | `Resource::indexFields()` + `Resource::filters()` |
| `Settings\Page\Entity\ContentPage` | `Resource` с переопределённым `indexPage()->render()` |
| `Settings\Page\PageManager` | `AdminKitManager` |
| `module()->getPageManager()` | `module()->adminKit()` |
| `UI\Control\Field\TextField` | `AdminKit\Field\Text` |
| `UI\Control\Field\NumberField` | `AdminKit\Field\Number` |
| `UI\Control\Field\StringField` | `AdminKit\Field\Text` |
| `UI\Control\Field\DropDownField` | `AdminKit\Field\Select` |
| `UI\Control\Field\SwitcherField` | `AdminKit\Field\Switcher` |
| `UI\Control\Field\PasswordField` | `AdminKit\Field\Password` |
| `UI\Control\Field\CalendarField` | `AdminKit\Field\Date` / `DateTime` |
| `UI\Control\Field\FileInputField` | `AdminKit\Field\File` |
| `UI\Control\Field\ImageInputField` | `AdminKit\Field\Image` |
| `UI\Control\Field\HtmlEditorField` | `AdminKit\Field\Html` |
| `UI\Control\Field\UserSelectorField` | `AdminKit\Field\UserSelect` |
| `UI\Control\Field\IblockSelectorField` | `AdminKit\Field\IblockSelect` |
| `UI\Control\Field\IblockElementSelectorField` | `AdminKit\Field\IblockElementSelect` |
| `UI\Control\Field\DialogSelectorField` | `AdminKit\Field\EntitySelect` (базовый) |
| `UI\Control\Field\NonEditableField` / `NoneEditableUserField` | `AdminKit\Field\Preview` |
| `UI\Control\Tab\BitrixTab` | `AdminKit\Support\Tab` |
| `UI\Control\Row\InputRow` | встроено в Field (AdminKit рендерит строки сам) |
| `UI\Control\Tab\GroupRightsTab` | пока не реализован — используйте `$APPLICATION->IncludeComponent(...)` напрямую |

### Можно ли удалить Settings и UI?

**`Settings\*`** — можно помечать как `@deprecated` и удалить после миграции всех страниц.

**`UI\*`** — большую часть можно удалить после миграции. Исключения:
- `UI\EntitySelector\*` — провайдеры для `BX.UI.EntitySelector`, используются AdminKit selector-полями. **Оставить.**
- `UI\Admin\MenuIcon` — enum иконок для `menu.php`. **Оставить или перенести в AdminKit.**

**Рекомендуемый порядок:**
1. Создать AdminKit Resources для каждой страницы
2. Переключить admin-файлы на `adminKit()->getCurrentPage()->render()`
3. Переключить `menu.php` на `adminKit()->getMenu()`
4. Пометить старые `Settings\Page\*` как `@deprecated`
5. После 1–2 релизов — удалить
