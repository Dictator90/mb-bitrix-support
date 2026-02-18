## Базовый компонент и параметры

Пакет предоставляет инфраструктуру для построения компонентов Bitrix:

- базовый класс компонента `MB\Bitrix\Component\BaseComponent`;
- билдер параметров `MB\Bitrix\Component\Parameters\Builder` и набор готовых трейтов (`UseIblock`, `UseForm`, `UseAgreement`);
- специализированные параметры для UI Form `MB\Bitrix\Component\Bitrix\UIForm\Parameters`.

---

## Базовый компонент `BaseComponent`

Файл: `src/Component/BaseComponent.php`  
Пространство имён: `MB\Bitrix\Component`

```php
abstract class BaseComponent extends \CBitrixComponent implements Main\Errorable
{
    protected Main\ErrorCollection $errorCollection;

    /** @var LangProviderInterface|null */
    protected $langProvider = null;

    public function __construct($component = null)
    {
        parent::__construct($component);
        $this->errorCollection = new Main\ErrorCollection();
        $this->loadModules();
    }

    // ...
}
```

Ключевые особенности:

- реализует интерфейс `Main\Errorable` через `ErrorCollection`;
- автоматически загружает необходимые модули через `loadModules()` при создании;
- поддерживает внешний провайдер языковых сообщений `LangProviderInterface`.

### Загрузка обязательных модулей

Методы:

- **`protected function getRequiredModules(): array`** — возвращает список ID модулей, которые должны быть подключены (по умолчанию `[]`);
- **`protected function loadModules(): void`**
  - итерируется по `getRequiredModules()` и вызывает `Main\Loader::includeModule()`;
  - если модуль не удалось подключить, бросает `Main\SystemException` с локализованным сообщением (`COMP_ERROR_MODULE_NOT_INSTALLED`).

Пример в наследнике:

```php
use MB\Bitrix\Component\BaseComponent;

class MyComponent extends BaseComponent
{
    protected function getRequiredModules(): array
    {
        return ['iblock', 'mb.core'];
    }
}
```

### Параметры компонента

- **`protected function getParameter(string $key): string`**  
  Возвращает строковое значение параметра компонента (`$this->arParams[$key] ?? ''`).

Рекомендуется использовать его вместо прямого доступа к `$arParams`, чтобы привести значение к строке и избежать предупреждений.

### Локализация через `LangProviderInterface`

- `setLangProvider(?LangProviderInterface $langProvider): static` — инъекция провайдера;
- `protected function getLang(string $code, $replace = null, $language = null): string`
  - если провайдер задан — делегирует вызов ему (`getLang($code, $replace, $language)`);
  - иначе возвращает сам код (для упрощённого режима без языковых файлов).

Это позволяет гибко организовать хранение языковых сообщений (в стандартных `lang`‑файлах Bitrix или в собственном хранилище).

### Работа с ошибками

Методы для реализации `Main\Errorable`:

- **`addError(string $text, int|string $code = 0, $customData = null): void`** — добавляет ошибку в коллекцию;
- **`getErrors(): array`** — возвращает массив `Main\Error`;
- **`getErrorByCode($code): ?Main\Error`** — возвращает конкретную ошибку по коду;
- **`hasErrors(): bool`** — есть ли ошибки;
- **`showErrors(): void`**
  - если текущий пользователь — администратор (`CurrentUser::get()->isAdmin()`),
  - выводит каждую ошибку через встроенный метод компонента `__showError()`.

Типичный шаблон использования:

```php
class MyComponent extends BaseComponent
{
    public function executeComponent()
    {
        try {
            // бизнес-логика
        } catch (\Throwable $e) {
            $this->addError($e->getMessage(), $e->getCode());
        }

        if ($this->hasErrors()) {
            $this->showErrors();
            return;
        }

        $this->includeComponentTemplate();
    }
}
```

---

## Билдер параметров компонента `Component\Parameters\Builder`

Файл: `src/Component/Parameters/Builder.php`  
Пространство имён: `MB\Bitrix\Component\Parameters`

Назначение: **удобно строить массивы `PARAMETERS` и `GROUPS` в `component.php`** с учётом условий, значений и текущего запроса.

Основные свойства:

- `protected ?Collection $params` — коллекция параметров;
- `protected ?Collection $groups` — коллекция групп;
- `protected ?Collection $values` — текущие значения параметров (`$arCurrentValues`);
- `$request` — текущий HTTP-запрос (`Context::getCurrent()->getRequest()`);
- `$langProvider` — опциональный `LangProviderInterface`.

Конструктор:

```php
public function __construct(array $array = [], array $values = [])
{
    Loader::includeModule('iblock');
    Loader::includeModule('form');

    $this->params = new Collection($array['PARAMETERS'] ?? []);
    $this->groups = new Collection($array['GROUPS'] ?? []);
    $this->values = new Collection($values);
    $this->request = Context::getCurrent()->getRequest();
}
```

### Добавление параметров с условиями

Методы:

- **`addParam($name, $params, $conditions = null): static`**
  - если `$conditions` не `null` и является:
    - массивом условий `[[key, operator, value], ...]`;
    - `callable(array $values): bool`;
    - реализацией `ConditionInterface`;
  - то делегирует в `addParamByCondition()`;
  - иначе добавляет параметр без условий.

- **`addParamByCondition(string $name, array $params, array|callable|ConditionInterface $conditions): static`**
  - для массивов условий:
    - проходится по условиям;
    - если параметр, от которого зависит условие, уже описан, включает ему `REFRESH = 'Y'`;
    - вызывает `evaluateArrayConditions()`, которая последовательно сравнивает значения (`compare()`).
  - если условие «не прошло», но в `$values` уже есть значение для этого параметра — сбрасывает его в `DEFAULT`.

Оператор сравнения поддерживает: `=`, `==`, `!=`, `<>`, `>`, `>=`, `<`, `<=`.

### Работа с группами и значениями

- **`addGroup(string $name, array $params): static`** — добавляет группу (`GROUPS`);
- **`getParam(string $name, $default = null)`** — получить параметр;
- **`getGroud(string $name, $default = null)`** — получить группу (опечатка в имени метода сохранена по месту);
- **`getValue(string $name, $default = null)`** — получать текущее значение параметра (`$arCurrentValues`).

### Готовые вспомогательные методы

Билдер включает ряд готовых методов для типовых сценариев:

- **Инфоблоки**:
  - `addIblockParams()` — добавляет параметры `IBLOCK_TYPE` и `IBLOCK_ID` с поддержкой динамического списка значений и `REFRESH`;
  - `addIblockElementFields()` — добавляет параметр `FIELD_CODE` через `\CIBlockParameters::GetFieldCode()`.

- **Настройки 404**:
  - `add404Settings(bool $bStatus = true, bool $bPage = true)` — создаёт группу `404_SETTINGS` и параметры `SET_STATUS_404`/`SHOW_404`.

- Также внутри `Builder` реализован ряд вспомогательных методов (`addCheckboxParam`, `addListParam` и др., см. реализацию в файле).

### Использование в `component.php`

```php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use MB\Bitrix\Component\Parameters\Builder;

$builder = new Builder(['PARAMETERS' => $arComponentParameters['PARAMETERS'] ?? []], $arCurrentValues);

$builder
    ->addIblockParams()
    ->add404Settings()
    // свои параметры
    ->addParam('CACHE_TIME', [
        'PARENT' => 'CACHE_SETTINGS',
        'NAME'   => 'Время кеширования',
        'TYPE'   => 'STRING',
        'DEFAULT'=> 3600,
    ]);

$arComponentParameters['PARAMETERS'] = $builder->params->all();
$arComponentParameters['GROUPS']     = $builder->groups->all();
```

---

## Параметры UI Form: `Bitrix\UIForm\Parameters`

Файл: `src/Component/Bitrix/UIForm/Parameters.php`  
Пространство имён: `MB\Bitrix\Component\Bitrix\UIForm`

Этот класс расширяет `MB\Bitrix\Component\Parameters\Base` (см. исходники) и предоставляет **флюентный интерфейс** для конфигурации параметров компонента `bitrix:ui.form`.

Примеры наиболее часто используемых методов:

- Идентификация и сущность:
  - `setGuId(string|int $value)` — GUID формы;
  - `setEntityId(string|int $value)` — ID сущности;
  - `setEntityTypeName(string $value)` — тип сущности;
  - `setEntityFields(array $values)` — описание полей;
  - `setEntityConfig(array $values)` — конфигурация секций и полей;
  - `setEntityData(array $values)` — данные сущности.

- Пользовательские поля:
  - `setUserFieldEntityId(string $value)` — ID сущности пользовательских полей;
  - `setUserFieldPrefix(string $value)` — префикс для UF-полей;
  - `configereUserFieldCreation(bool $value = true)` — разрешить создание пользовательских полей;
  - `setUserFieldCreationSign(string $value)` — подпись для создания UF.

- Режимы и UI:
  - `setInitialMode(string $value)` — начальный режим (`INITIAL_MODE_VIEW` / `INITIAL_MODE_EDIT`);
  - `configureAjaxMode(bool $value = true)` — включить AJAX;
  - `configureToggleMode(bool $value = true)` — включить переключение режимов;
  - `configureVisibilityPolict(bool $value = true)` — включить политику видимости;
  - `configureSectionCreation(bool $value = true)` / `configureSectionEdit()` / `configureSectionDragAndDrop()` — управление секциями;
  - `configureFieldDragAndDrop(bool $value = true)` — перетаскивание полей;
  - `configureToolPanel(bool $value = true)` / `configureBottomPanel()` — панель инструментов/нижняя панель;
  - `configureReadOnly(bool $value = true)` — режим только для чтения;
  - `configureEmbeded(bool $value = true)` — вложенный режим;
  - `configureForceDefaultConfig(bool $value = true)` / `configureForceDefaultSectionName()` — поведение конфигурации.

- AJAX и сервисы:
  - `setComponentAjaxData(array $values)` — данные для AJAX;
  - `setServiceUrl(string $value)` — URL сервиса.

- Кнопки:
  - `setToolPanelCustomButtons(array $values)` — кастомные кнопки в панели;
  - `setToolPanelButtonsOrder(array $values)` — порядок кнопок.

Каждый метод возвращает `static`, что позволяет строить конфигурацию цепочками:

```php
use MB\Bitrix\Component\Bitrix\UIForm\Parameters as UIFormParams;

$params = (new UIFormParams('user_edit'))
    ->setEntityId($userId)
    ->setEntityTypeName('USER')
    ->setEntityFields($fields)
    ->setEntityConfig($config)
    ->setEntityData($data)
    ->configureAjaxMode()
    ->configureToolPanel()
    ->configureBottomPanel()
    ->configureReadOnly(false);

$arResult['UI_FORM_PARAMS'] = $params->toArray(); // см. реализацию Base
```

---

## Рекомендации по использованию

- Используйте `BaseComponent` для унификации:
  - загрузки модулей;
  - обработки ошибок;
  - работы с языковыми сообщениями.

- Для компонентов с большим количеством параметров:
  - выносите логику построения `PARAMETERS` в отдельный класс/файл с использованием `Component\Parameters\Builder`;
  - используйте трейты `UseIblock`, `UseForm`, `UseAgreement` для типовых наборов параметров.

- Для UI‑форм:
  - конфигурируйте все параметры формы через `Bitrix\UIForm\Parameters` и сохраняйте результат в `$arResult`, чтобы избежать «магических» массивов в шаблонах;
  - следите за согласованностью между `ENTITY_FIELDS`, `ENTITY_CONFIG` и `ENTITY_DATA`.

Такой подход делает код компонентов более читаемым, типобезопасным и предсказуемым, а также упрощает рефакторинг и поддержку.

