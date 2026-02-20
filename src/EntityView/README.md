# EntityView

Вывод ORM-сущностей Bitrix в админку и управление ими: список (bitrix:main.ui.grid), добавление, редактирование, удаление.

## Сценарии использования

- **EntityViewPage** — рекомендуемый сценарий для страниц настроек модуля. Наследуете класс, реализуете `getEntity()` и `prepareParams(Builder $builder)`, в `render()` вызывается Builder с заполненным гридом.
- **Builder** — программное построение грида и рендер. Используйте, когда нужен полный контроль (кастомные колонки, фильтры, сборщики строк). Вызовы: `new Builder($entity)`, при необходимости `setListActions()` / `setGroupActions()`, `fillGrid()`, `setRawRows()`, `render()`. При использовании без Entity можно вызвать `checkPermissions()` перед `render()`.
- **Entity** — параметрический сценарий с автоматическим созданием грида внутри. Подходит, когда достаточно настроек через параметры (пути, действия). Внутри `render()` создаётся Builder, заполняется грид и вызывается компонент. Рекомендуется вызывать через наследника, задавая пути и действия.

Данные в список попадают только при использовании Builder или Entity (оба вызывают `setRawRows()`).

## Компоненты

| Компонент | Назначение |
|-----------|------------|
| `mb:admin.entityview` | Роутер по `action`: list / edit / view. Подключает список, форму редактирования или просмотр. |
| `mb:admin.entityview.list` | Тулбар (фильтр + кнопка «Создать») и `bitrix:main.ui.grid`. Данные и параметры получает от родителя. |
| `mb:admin.entityview.edit` | Форма добавления/редактирования через `UI\Control\Form\EntityBitrix` и табы. |
| `mb:admin.entityview.form` | Отдельный компонент на базе Bitrix UI Entity Editor; не интегрирован с list/edit. Используйте для сложных форм с конфигурируемым редактором; для типового CRUD — `admin.entityview.edit`. |

## События (mb.core)

| Событие | Параметры | Назначение |
|---------|-----------|------------|
| `onEntityViewCheckPermission` | entity, action | Проверка прав по сущности и действию (list/add/edit/delete/view). Вернуть EventResult::ERROR для запрета. |
| `onPrepareGridRawRows` | params, entity | Изменение параметров getList перед загрузкой строк грида. |
| `onBeforeGridSetPaginationFilter` | filter, entity | Изменение фильтра перед подсчётом для пагинации. |
| `onAfterPrepareGridRows` | entity, rows | Изменение подготовленных строк грида. |
| `onGridRowDeleteAction` | entity, primary | Удаление одной строки (действие в меню). ERROR — отменить. |
| `onEntityViewFormBeforeSave` | entity, primaryValues, fields, isEdit | Перед сохранением формы. Можно изменить fields или вернуть ERROR. |
| `onEntityViewFormAfterSave` | entity, primary, fields, isEdit | После успешного сохранения формы. |
| `onEntityViewPanelBeforeDelete` | entity, rowIds | Перед групповым удалением в панели. Вернуть ERROR для отмены. |

## Составной первичный ключ

Идентификатор строки грида при нескольких полях первичного ключа формируется как значения, объединённые разделителем `|` (см. `BaseRowAssembler::PRIMARY_DELIMITER`). Панель удаления и разбор id обрабатывают такой формат через `Helper::parseRowIdToPrimary()`.
