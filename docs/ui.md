# UI-подсистема `MB\Bitrix\UI`

Пакет `MB\Bitrix\UI` предоставляет набор классов для создания **административных интерфейсов** в Bitrix: формы настроек, таблицы (Grid), элементы управления (controls), вкладки (tabs) и диалоговые селекторы (EntitySelector).

## Обзор архитектуры

```
src/UI/
├── Base/                    # Базовые классы для UI-компонентов
│   ├── View.php            # Базовый view с CSS-генерацией
│   ├── FormContainer.php   # Контейнер форм
│   ├── BitrixAdapter.php   # Адаптер к Bitrix UI Renderable
│   ├── Field/              # Базовые классы полей
│   ├── Form/               # Базовые классы форм
│   ├── Grid/               # Базовые классы grid
│   ├── Row/                # Базовые классы строк
│   ├── Tab/                # Базовые классы вкладок
│   └── Traits/             # Трейты для Base-компонентов
├── Control/                 # Конкретные элементы управления
│   ├── Field/              # Поля ввода (20+ классов)
│   ├── Tab/                # Вкладки (BitrixTab, CustomTab...)
│   ├── TabSet/             # Наборы вкладок
│   ├── Form/               # Формы
│   ├── Row/                # Строки форм
│   ├── Button/             # Кнопки
│   └── Traits/             # Трейты для контролов
├── EntitySelector/          # Провайдеры для диалогов выбора
│   ├── IblockListProvider.php
│   ├── IblockElementListProvider.php
│   ├── IblockPropertyListProvider.php
│   ├── UserListProvider.php
│   └── UserGroupListProvider.php
├── Providers/               # Service Provider для DI
│   └── ServiceProvider.php
├── Traits/                  # Общие трейты (20+ traits)
│   ├── HasName.php
│   ├── HasValue.php
│   ├── HasOptions.php
│   ├── HasId.php
│   ├── HasLabel.php
│   ├── HasDisabled.php
│   ├── HasRequired.php
│   └── ...
└── Admin/
    └── MenuIcon.php
```

---

## Базовые классы

### `View` — базовый view с CSS

Абстрактный класс для генерации CSS-стилей:

```php
namespace MB\Bitrix\UI\Base;

abstract class View
{
    abstract public function getCss(): array;
    
    public function showCss($withTag = true): void
    {
        // Выводит <style>...</style>
    }
}
```

**Пример реализации:**

```php
class MyView extends View
{
    public function getCss(): array
    {
        return [
            '.my-component' => [
                'margin' => '0 12px 0 0',
                'color' => 'red'
            ]
        ];
    }
}
```

### `FormContainer` — контейнер форм

Управляет коллекцией форм без привязки к CSS View:

```php
use MB\Bitrix\UI\Base\FormContainer;
use MB\Bitrix\UI\Base\Form\Base as FormBase;

$container = new FormContainer([
    $form1,
    $form2,
]);

$container->addForm($form3);
$forms = $container->getForms();
```

### `BitrixAdapter` — адаптер к Bitrix UI

Адаптирует `\Bitrix\UI\Contract\Renderable` к контракту пакета:

```php
use MB\Bitrix\UI\Base\BitrixAdapter;
use Bitrix\UI\Contract\Renderable as BitrixRenderable;

$bitrixComponent = new \Bitrix\UI\SomeComponent();
$adapter = new BitrixAdapter($bitrixComponent);
$adapter->render(); // Выведет HTML
```

---

## Поля (Fields)

### Иерархия полей

```
Contracts\UI\Renderable
    ↓
Base\Field\AbstractBaseField (HasId, HasValue, HasStyle, HasEnabled, HasCondition)
    ↓
    ├─ Base\Field\AbstractInputField (HasName, HasPlaceholder, HasClass...)
    │   ↓
    │   └─ Control\Field\InputField (HasIcon, HasMultiple)
    │       ↓
    │       ├─ TextField (HasLength, HasSize)
    │       ├─ NumberField
    │       ├─ PasswordField
    │       ├─ PhoneField
    │       └─ ...
    │
    ├─ Base\Field\AbstractOptionsField (HasName, HasOptions)
    │   ↓
    │   └─ Control\Field\DropDownField
    │
    └─ Control\Field\* (кастомные поля)
```

### Базовое поле `AbstractBaseField`

Предоставляет общую функциональность:

- **ID, значение, стили** — трейты `HasId`, `HasValue`, `HasStyle`
- **Включение/отключение** — `HasEnabled`
- **Условия отображения** — `HasCondition`, `RendersWithConditions`
- **Связь с формой и строкой** — `setForm()`, `setRow()`
- **Методы жизненного цикла** — `beforeRender()`, `afterRender()`, `beforeSave()`

```php
abstract class AbstractBaseField implements Renderable
{
    use HasId, HasValue, HasStyle, HasEnabled, HasCondition, RendersWithConditions;
    
    protected ?Form\Base $form = null;
    protected ?Row\Base $row = null;
    
    abstract public function getHtml(): string;
    
    public function beforeSave(&$value) {}
    public function setForm(Form\Base $form) { ... }
    public function setRow(Row\Base $row) { ... }
}
```

### Поля ввода `AbstractInputField`

Базовый класс для input-полей с атрибутами:

```php
abstract class AbstractInputField extends AbstractBaseField
{
    use HasName, HasPlaceholder, HasJsEvent, HasClass, 
        HasRequired, HasDisabled, HasReadonly, HasSize;
    
    abstract public static function getType(): string;
    
    public function __construct($name)
    {
        $this->setName($name);
        $this->setId("{$name}_" . Random::getString(10));
    }
    
    public function getHtml(): string
    {
        // Генерирует <input type="..." ... />
    }
}
```

**Пример: `TextField`:**

```php
namespace MB\Bitrix\UI\Control\Field;

class TextField extends InputField
{
    use HasLength, HasSize;

    public static function getType(): string
    {
        return 'text';
    }

    protected function exAttributes(): array
    {
        return [
            'size' => $this->getSize(),
            'minlength' => $this->getMinlength(),
            'maxlength' => $this->getMaxlength(),
        ];
    }
}
```

### Поля с опциями `AbstractOptionsField`

Для select, radio, checkbox:

```php
class DropDownField extends AbstractOptionsField
{
    use HasMultiple, HasStyle, HasClass, HasDisabled;

    public function getHtml(): string
    {
        $iconNode = $this->isMultiple() ? '' : '<div class="ui-ctl-after ui-ctl-icon-angle"></div>';
        return <<<DOC
            <div class="{$this->getContainerClass()}">
                {$iconNode}
                <select class="ui-ctl-element" 
                        id="{$this->getId()}" 
                        name="{$this->getName()}" 
                        {$this->getMultipleHtml()}
                        {$this->getStyle()}
                        class="{$this->getClass()}"
                        {$this->getDisabled()}
                >
                    {$this->getOptionsHtml()}
                </select>
            </div>
DOC;
    }
}
```

### Специализированные поля

#### `CalendarField` — календарь

```php
use MB\Bitrix\UI\Control\Field\CalendarField;

$field = new CalendarField('DATE_START');
$field->setValue('2026-04-27');
$field->setPlaceholder('Дата начала');
```

**HTML-вывод:**

```html
<div class="ui-ctl ui-ctl-after-icon ui-ctl-datetime ui-ctl-w33">
    <div class="ui-ctl-after ui-ctl-icon-calendar"></div>
    <input id="DATE_START_abc123" name="DATE_START" class="ui-ctl-element" 
           type="text" readonly="readonly" value="2026-04-27"
           placeholder="Дата начала"
           onclick="BX.calendar({node: this, field: this})">
</div>
```

#### `IblockElementSelectorField` — выбор элементов инфоблока

```php
use MB\Bitrix\UI\Control\Field\IblockElementSelectorField;

$field = new IblockElementSelectorField('PRODUCT_ID', 5); // iblockId = 5
$field->setMultiple(true);
$field->setValue([10, 25, 30]);
```

**Использует JavaScript-компонент:**

```html
<div id="tag_selector_PRODUCT_ID"></div>
<script>
    new MB.UI.DialogSelector.DialogSelector({
        target: '#tag_selector_PRODUCT_ID',
        name: 'PRODUCT_ID',
        dialog: {
            context: 'MB_CORE_PRODUCT_ID',
            dropdownMode: true,
            preload: true,
            entities: [{
                id: 'iblock-element-list',
                options: { selected: [10,25,30], iblockId: 5 },
                dynamicLoad: true,
                dynamicSearch: true
            }]
        },
        multiple: true
    }).render();
</script>
```

#### `UserSelectorField` — выбор пользователей

```php
use MB\Bitrix\UI\Control\Field\UserSelectorField;

$field = new UserSelectorField('ASSIGNED_USERS');
$field->setMultiple(true);
$field->setValue([1, 5, 10]);
```

#### `HtmlEditorField` — HTML-редактор

```php
use MB\Bitrix\UI\Control\Field\HtmlEditorField;

$field = new HtmlEditorField('CONTENT');
$field->setValue('<p>Текст</p>');
```

### Фабрика полей `FieldFactory`

Создаёт поля через Service Locator:

```php
use MB\Bitrix\UI\Control\Field\FieldFactory;
use MB\Container\Container;

$container = new Container();
$factory = new FieldFactory($container);

// Через метод create()
$field = $factory->create('text', 'NAME');

// Через магический метод
$field = $factory->createString('NAME');
$field = $factory->createNumber('QUANTITY');
$field = $factory->createDropDown('STATUS', ['active' => 'Активен', 'inactive' => 'Неактивен']);
$field = $factory->createIblockElementSelector('PRODUCT', 5);
```

**Зарегистрированный сервис:**

```php
// В ServiceProvider
$this->app->singleton('ui.field', FieldFactory::class);

// Использование
$fieldFactory = app('ui.field');
```

---

## Формы

### Базовый класс `Form\Base`

Управляет формой с вкладками, строками и полями:

```php
namespace MB\Bitrix\UI\Base\Form;

abstract class Base
{
    use HasId, HasRequest, HasSiteId;
    
    protected Tab\Set $tabSet;
    protected ?ModuleEntityContract $module = null;
    protected ?Options\Base $optionsEntity = null;
    
    public function __construct(
        string $id,
        $siteId = false,
        Request $request = null,
        RightsCheckerInterface $rightsChecker = null,
        ButtonPanelRendererInterface $buttonPanelRenderer = null
    ) {}
    
    public function setModule(ModuleEntityContract $module): static;
    public function setOptions(Options\Base $options): static;
    public function checkRequest();
    public function saveSettingsAction();
    public function render();
}
```

### Жизненный цикл формы

1. **Создание:**
   ```php
   $form = new MyForm('my_settings');
   $form->setModule($moduleEntity);
   $form->setOptions($optionsEntity);
   ```

2. **Обработка запроса:**
   ```php
   $form->checkRequest(); // Проверяет POST, сохраняет данные
   ```

3. **Рендер:**
   ```php
   $form->render(); // Выводит форму
   ```

### Режимы действий

- **`MODE_ACTION_SETTINGS`** — настройки (по умолчанию)
- **`MODE_ACTION_CUSTOM`** — кастомное действие

### События формы

```php
use MB\Bitrix\UI\Base\Form\Base;

// Событие после сохранения
$this->attach('my.module', Base::EVENT_ON_FORM_SAVE_ACTION, function($event) {
    $formId = $event->getParameter('formId');
    $tabSet = $event->getParameter('tabSet');
    $request = $event->getParameter('request');
});
```

### Пример реализации формы

```php
namespace MyModule\UI\Forms;

use MB\Bitrix\UI\Base\Form\Base;
use MB\Bitrix\UI\Control\Field\TextField;
use MB\Bitrix\UI\Control\Field\DropDownField;
use MB\Bitrix\UI\Control\Field\SwitcherField;

class SettingsForm extends Base
{
    protected function getButtonPanelParams(): array
    {
        return [
            'ALIGN' => 'left',
            'BUTTONS' => [
                [
                    'TYPE' => 'save',
                    'NAME' => 'Сохранить',
                    'VALUE' => 'Y',
                    'ID' => 'saveButton',
                ],
                [
                    'TYPE' => 'cancel',
                    'NAME' => 'Отмена',
                ],
            ],
        ];
    }
}
```

---

## Вкладки (Tabs)

### Базовый класс `Tab\Base`

```php
namespace MB\Bitrix\UI\Base\Tab;

abstract class Base implements Renderable
{
    use HasId, HasLabel, HasDescription, HasActive, HasEnabled;
    
    protected array $rows = [];
    
    abstract public function getTabHtml(): string;
    abstract public function getTabContentHtml(): string;
    
    public function addRow(Row\Base $row);
    public function getRows();
    public function render();
}
```

### `BitrixTab` — стандартная вкладка Bitrix

```php
namespace MB\Bitrix\UI\Control\Tab;

class BitrixTab extends Base
{
    use HasCounter;

    public function getTabHtml(): string
    {
        $activeClass = $this->isActive() ? 'mb-tabs-switcher-selected' : '';
        $counter = $this->hasCounter()
            ? "<span class=\"mb-tabs-switcher-text-counter\">{$this->getCounter()}</span>"
            : "";

        return <<<DOC
            <div class="mb-tabs-switcher {$activeClass}" data-tab-id="{$this->getId()}">
                <div class="mb-tabs-switcher-text">
                    <div class="mb-tabs-switcher-text-inner">
                        {$this->getLabel()}
                        {$counter}
                    </div>
                </div>
            </div>
DOC;
    }

    public function getTabContentHtml(): string
    {
        $activeClass = $this->isActive() ? 'mb-tabs-switcher-block-selected' : '';
        return <<<DOC
            <div class="mb-tabs-switcher-block {$activeClass}" data-tab-content="{$this->getId()}">
                <div class="mb-tabs-switcher-block__title">
                    {$this->getDescription()}
               </div>
                <div class="ui-form ui-form-section">
                {$this->getHtml()}
                </div>
            </div>
DOC;
    }
}
```

### `BitrixTabSet` — набор вкладок

```php
namespace MB\Bitrix\UI\Control\TabSet;

class BitrixTabSet extends Set
{
    use HasId;

    public function render(): void
    {
        echo $this->getHtml();
    }

    public function getHtml(): string
    {
        return $this->getTabsetStartHtml()
            . $this->getTabsetHeaderHtml()
            . $this->getTabsetContentHtml()
            . $this->getTabsetEndHtml();
    }
}
```

**Пример использования:**

```php
use MB\Bitrix\UI\Control\Tab\BitrixTab;
use MB\Bitrix\UI\Control\TabSet\BitrixTabSet;

$tabSet = new BitrixTabSet();

$tab1 = new BitrixTab('tab1');
$tab1->setLabel('Основные');
$tab1->addRow($row1);
$tab1->addRow($row2);

$tab2 = new BitrixTab('tab2');
$tab2->setLabel('Дополнительно');
$tab2->addRow($row3);

$tabSet->addTab($tab1);
$tabSet->addTab($tab2);
$tabSet->render();
```

---

## EntitySelector — диалоги выбора

Компонент для выбора сущностей Bitrix (пользователи, элементы инфоблоков, группы).

### Провайдеры

| Провайдер | Описание |
|-----------|----------|
| `IblockListProvider` | Выбор инфоблоков |
| `IblockElementListProvider` | Выбор элементов инфоблока |
| `IblockPropertyListProvider` | Выбор свойств инфоблока |
| `UserListProvider` | Выбор пользователей |
| `UserGroupListProvider` | Выбор групп пользователей |

### `UserListProvider` — выбор пользователей

**Возможности:**

- Поиск по имени, email, логину
- Недавние пользователи
- Предзагрузка пользователей
- Фильтрация (активные, с email, intranet/extranet)
- Кастомизация полей вывода

**Пример использования:**

```php
use MB\Bitrix\UI\EntitySelector\UserListProvider;

$provider = new UserListProvider([
    'selected' => [1, 5], // Выбранные пользователи
    'moduleId' => 'my.module',
    'nameTemplate' => '#LAST_NAME# #NAME#',
    'onlyWithEmail' => true,
    'activeUsers' => true,
    'limit' => 100,
]);

// Проверка доступности
if ($provider->isAvailable()) {
    // Получение элементов
    $items = $provider->getItems([1, 5, 10]);
}
```

**Методы:**

```php
// Получить пользователей
$users = UserListProvider::getUsers([
    'userId' => [1, 5, 10],
    'searchQuery' => 'Иван',
    'order' => ['LAST_NAME' => 'asc'],
    'limit' => 50,
]);

// Создать Items для диалога
$items = UserListProvider::makeItems($users, ['selected' => [1]]);

// Получить одного пользователя
$user = UserListProvider::getUser(1);

// Форматирование имени
$name = UserListProvider::formatUserName($user, [
    'nameTemplate' => '#LAST_NAME# #NAME#'
]);

// Получить аватар
$avatar = UserListProvider::makeUserAvatar($user);
```

### `IblockListProvider` — выбор инфоблоков

```php
use MB\Bitrix\UI\EntitySelector\IblockListProvider;

$provider = new IblockListProvider([
    'selected' => [5, 10],
    'moduleId' => 'my.module',
]);

$provider->isAvailable(); // Проверка прав
```

---

## Трейты (Traits)

### Основные трейты

| Трейт | Описание |
|-------|----------|
| `HasName` | Свойство `$name` + геттер/сеттер |
| `HasValue` | Свойство `$value` + геттер/сеттер |
| `HasId` | Свойство `$id` + геттер/сеттер |
| `HasOptions` | Свойство `$options` + методы работы |
| `HasDisabled` | Флаг отключения + `getDisabled()` |
| `HasRequired` | Флаг обязательности + `getRequired()` |
| `HasReadonly` | Флаг readonly + `getReadonly()` |
| `HasPlaceholder` | Placeholder для input |
| `HasClass` | CSS-классы (строка + массив) |
| `HasStyle` | Inline-стили |
| `HasJsEvent` | JavaScript-события (onclick, onchange...) |
| `HasMultiple` | Поддержка множественного выбора |
| `HasLabel` | Label для элемента |
| `HasDescription` | Описание элемента |
| `HasActive` | Флаг активности |
| `HasEnabled` | Флаг включения (с проверкой условий) |
| `RendersWithConditions` | Реализация `render()` с условиями |

### Пример: `HasValue`

```php
trait HasValue
{
    use HasName, HasDefaultValue;

    protected mixed $value = null;
    protected mixed $rawValue = null;

    public function getValue(): mixed
    {
        return $this->value ?: $this->getDefaultValue();
    }

    public function setValue(mixed $value): static
    {
        $this->rawValue = $value;
        $this->beforeSetValue($value);
        $this->value = $value;
        return $this;
    }

    protected function beforeSetValue(&$value) {}
}
```

### Пример: `RendersWithConditions`

```php
trait RendersWithConditions
{
    public function render(): void
    {
        // Выполняем действия условий
        if ($this->hasConditionActions()) {
            $this->doConditionActions();
        }

        // Проверяем включён ли элемент
        if (!$this->isEnabled()) {
            return;
        }

        // Рендер
        $this->beforeRender();
        echo $this->getHtml();
        $this->afterRender();
    }
}
```

---

## Grid (Таблицы)

### `Base` — базовый grid

CSS Grid-based таблица:

```php
namespace MB\Bitrix\UI\Base\Grid;

class Base extends View
{
    use HasId;
    
    protected ?TemplateArea $area = null;
    
    public function getCss(): array
    {
        $rowsString = [];
        foreach ($this->area->getRows() as $row) {
            $rowsString[] = '"' . $row->getString() . '"';
        }

        return [
            '.mb-core-grid' => [
                'display' => 'grid',
                'grid-template-areas' => implode("\n", $rowsString)
            ]
        ];
    }
}
```

### `TemplateArea` — область grid

Определяет структуру таблицы через CSS Grid Areas.

---

## Service Provider

### Регистрация в контейнере

```php
namespace MB\Bitrix\UI\Providers;

use MB\Bitrix\Foundation\ServiceProvider;
use MB\Bitrix\UI\Control\Field\FieldFactory;

class ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('ui.field', FieldFactory::class);
        $this->app->alias('ui.field', FieldFactory::class);
    }
}
```

---

## Примеры использования

### 1. Простая форма настроек

```php
use MB\Bitrix\UI\Control\Field\TextField;
use MB\Bitrix\UI\Control\Field\DropDownField;
use MB\Bitrix\UI\Control\Field\SwitcherField;
use MB\Bitrix\UI\Control\Tab\BitrixTab;
use MB\Bitrix\UI\Base\Form\Base;

class MySettingsForm extends Base
{
    protected function initFields(): void
    {
        // Текстовое поле
        $nameField = new TextField('SITE_NAME');
        $nameField->setValue('Мой сайт');
        $nameField->setPlaceholder('Название сайта');
        
        // Выпадающий список
        $statusField = new DropDownField('STATUS', [
            'active' => 'Активен',
            'inactive' => 'Неактивен',
        ]);
        $statusField->setValue('active');
        
        // Переключатель
        $enabledField = new SwitcherField('ENABLED');
        $enabledField->setValue('Y');
    }
}

// Использование
$form = new MySettingsForm('my_settings');
$form->setModule($moduleEntity);
$form->checkRequest(); // Обработка POST
$form->render(); // Вывод формы
```

### 2. Форма с вкладками

```php
use MB\Bitrix\UI\Control\Tab\BitrixTab;
use MB\Bitrix\UI\Control\TabSet\BitrixTabSet;
use MB\Bitrix\UI\Control\Field\TextField;
use MB\Bitrix\UI\Base\Row\Base as RowBase;

// Создание вкладок
$tab1 = new BitrixTab('general');
$tab1->setLabel('Общие');
$tab1->addRow(new RowBase([
    new TextField('NAME'),
    new TextField('DESCRIPTION'),
]));

$tab2 = new BitrixTab('advanced');
$tab2->setLabel('Дополнительно');
$tab2->addRow(new RowBase([
    new TextField('CODE'),
]));

// Набор вкладок
$tabSet = new BitrixTabSet([$tab1, $tab2]);
$tabSet->render();
```

### 3. Диалог выбора пользователей

```php
use MB\Bitrix\UI\Control\Field\UserSelectorField;

// Одиночный выбор
$userField = new UserSelectorField('ASSIGNED_USER');
$userField->setValue(5);

// Множественный выбор
$multiField = new UserSelectorField('ASSIGNED_USERS');
$multiField->setMultiple(true);
$multiField->setValue([1, 5, 10]);

// Вывод
echo $userField->getHtml();
echo $multiField->getHtml();
```

### 4. Выбор элементов инфоблока

```php
use MB\Bitrix\UI\Control\Field\IblockElementSelectorField;

$field = new IblockElementSelectorField('PRODUCTS', 5); // iblockId = 5
$field->setMultiple(true);
$field->setValue([10, 20, 30]);

echo $field->getHtml();
```

### 5. Кастомное поле

```php
use MB\Bitrix\UI\Base\Field\AbstractBaseField;

class ColorPickerField extends AbstractBaseField
{
    use HasName;
    
    public function __construct(string $name)
    {
        $this->setName($name);
    }
    
    public function getHtml(): string
    {
        return <<<DOC
            <div class="ui-ctl ui-ctl-color">
                <input type="color" name="{$this->getName()}" value="{$this->getValue()}" />
            </div>
DOC;
    }
}
```

---

## Интеграция с Bitrix

### Требуемые модули

- `main` — обязательно
- `iblock` — для `IblockElementSelectorField`, `IblockListProvider`
- `ui` — для JavaScript-компонентов

### Подключение JS-библиотек

```php
use Bitrix\Main\UI\Extension;

// В поле или форме
Extension::load(['ui', 'mb.ui.dialog-selector', 'mb.ui.multi-input']);
```

### Права доступа

Формы проверяют права через `RightsCheckerInterface`:

```php
protected function checkPermissions()
{
    global $USER;
    return
        $this->request->isAdminSection()
        && $USER->IsAuthorized()
        && $this->getUserRights() === 'W';
}
```

---

## Миграция с legacy-кода

### До

```php
// Legacy Bitrix
$tabControl = new CAdminTabControl('tabControl', [
    [
        'DIV' => 'edit1',
        'TAB' => 'Общие',
        'TITLE' => 'Общие настройки',
    ],
]);

$tabControl->Begin();
?>
<tr>
    <td>Название:</td>
    <td><input type="text" name="SITE_NAME" value="<?=$SITE_NAME?>" /></td>
</tr>
<?
$tabControl->Buttons();
```

### После

```php
use MB\Bitrix\UI\Control\Tab\BitrixTab;
use MB\Bitrix\UI\Control\Field\TextField;

$tab = new BitrixTab('edit1');
$tab->setLabel('Общие');
$tab->addRow(new RowBase([
    new TextField('SITE_NAME'),
]));

$tab->render();
```

---

## Диагностика и отладка

### Методы `toArray()`

Большинство классов поддерживают сериализацию в массив:

```php
$fieldData = $field->toArray();
$tabData = $tab->toArray();
$formData = $form->toJson();
```

### Логирование

```php
use MB\Bitrix\Logger\UniversalLogger;

$logger = app(UniversalLogger::class);
$logger->debug('UI Field rendered', [
    'field' => get_class($field),
    'value' => $field->getValue(),
]);
```

---

## Известные ограничения

1. **CSS-генерация** — `View::showCss()` выводит стили напрямую, нет системы сборки
2. **JavaScript-зависимости** — некоторые поля требуют `mb.ui.*` компоненты (нужно подключать вручную)
3. **EntitySelector** — провайдеры жестко завязаны на Bitrix-классы (`CUser`, `IblockTable`)
4. **Grid** — базовая реализация, нет pagination/sorting из коробки

---

## Ссылки

- [`docs/components.md`](components.md) — компоненты Bitrix
- [`docs/iblock-and-usertypes.md`](iblock-and-usertypes.md) — инфоблоки
- [`docs/application.md`](application.md) — bootstrap приложения
- [`docs/laravel-parity.md`](laravel-parity.md) — DI-контейнер