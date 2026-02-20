<?php

namespace MB\Bitrix\EntityView\Parameters;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ORM\Entity as ORMEntity;
use Bitrix\Main\ORM\Fields\Field;
use Bitrix\Main\ORM\Fields\Relations;
use Bitrix\Main\ORM\Fields\ScalarField;
use MB\Bitrix\EntityView\Helper;
use MB\Bitrix\EntityView\Parameters\Item\GridColumn;

class Common extends Base
{
    protected ORMEntity $entity;

    public function __construct(ORMEntity $entity)
    {
        $this->entity = $entity;
        parent::__construct();
    }

    public function getDefault(): array
    {
        return [
            'COMMON_CAN_EDIT' => true,
            'COMMON_ENTITY' => $this->entity,
            'COMMON_SET_TITLE' => false,
            'COMMON_ADMIN_MODE' => true,

            'COMMON_PATH_TO_ADD' => null,
            'COMMON_PATH_TO_VIEW' => null,
            'COMMON_PATH_TO_EDIT' => null,
            'COMMON_PATH_TO_LIST' => null,

            'GRID_ID' => Helper::getGridIdByEntity($this->entity),
            'GRID_SHOW_MORE_BUTTON' => false,
            'GRID_COLUMNS' => $this->getColumnsByEntity(),
            'GRID_ENABLE_COLLAPSIBLE_ROWS' => true, # Включает группировку строк с разворачиванием.
            'GRID_SETTINGS_WINDOW_TITLE' => null, # Заголовок попапа настройки грида.
            'GRID_SHOW_GRID_SETTINGS_MENU' => true, # Разрешает отображение меню настройки грида (кнопка с шестеренкой).
            'GRID_COLUMNS_ALL_WITH_SECTIONS' => [], # 	Список колонок с разделами, для попапа настройки грида.
            'GRID_HEADERS_SECTIONS' => [],
            'GRID_ENABLE_FIELDS_SEARCH' => true,
            'GRID_TOP_ACTION_PANEL_RENDER_TO' => null,
            'GRID_TOP_ACTION_PANEL_PINNED_MODE' => null,
            'GRID_TOP_ACTION_PANEL_CLASS' => null,
            'GRID_ACTION_PANEL' => $this->getDefaultActionPanel(),
            'GRID_DEFAULT_PAGE_SIZE' => null,
            'GRID_STUB' =>  null, # Заглушка пустого грида. Может принимать HTML-строку, либо массив. Отображается всегда...
            'GRID_LAZY_LOAD' => null,
            'GRID_SHOW_CHECK_ALL_CHECKBOXES' => true,
            'GRID_SHOW_ROW_CHECKBOXES' => true,
            'GRID_SHOW_ROW_ACTIONS_MENU' => true,
            'GRID_SHOW_NAVIGATION_PANEL' => true,
            'GRID_SHOW_PAGINATION' => true,
            'GRID_SHOW_SELECTED_COUNTER' => true,
            'GRID_SHOW_TOTAL_COUNTER' => true,
            'GRID_SHOW_PAGESIZE' => true,
            'GRID_PAGE_SIZES' => [
                [
                    'NAME' => 5,
                    'VALUE' => 5,
                ],
                [
                    'NAME' => 10,
                    'VALUE' => 10,
                ],
                [
                    'NAME' => 20,
                    'VALUE' => 20,
                ],
                [
                    'NAME' => 50,
                    'VALUE' => 50,
                ],
                [
                    'NAME' => 100,
                    'VALUE' => 100,
                ],
            ],
            'GRID_SHOW_ACTION_PANEL' => true,
            'GRID_SHOW_GROUP_EDIT_BUTTON' => true,
            'GRID_SHOW_GROUP_DELETE_BUTTON' => true,
            'GRID_SHOW_SELECT_ALL_RECORDS_CHECKBOX' => true, # Выводит или скрывает чекбокс выбора всех строк, на всех страницах.
            'GRID_SHOW_ALLOW_COLUMNS_SORT' => true, # Разрешает сортировку колонок перетаскиванием.
            'GRID_ALLOW_ROWS_SORT' => false, # Разрешает сортировку строк перетаскиванием.
            'GRID_ALLOW_ROWS_SORT_IN_EDIT_MODE' => true,
            'GRID_ALLOW_EDIT_SELECTION' => false, # Разрешает выделение строк в режиме инлайн-редактирования.
            'GRID_ALLOW_ROWS_SORT_INSTANT_SAVE' => false, # Разрешает сохранение сортировки строк сразу, по окончании перетаскивания.
            'GRID_ALLOW_STICKED_COLUMNS' => true, # Разрешает закрепление колонок с параметром sticked при горизонтальной прокрутке.
            'GRID_ALLOW_COLUMNS_RESIZE' => true, # Разрешает изменение размера колонок.
            'GRID_ALLOW_HORIZONTAL_SCROLL' => true, # Разрешает горизонтальную прокрутку, если грид не помещается по ширине.
            'GRID_ALLOW_SORT' => true, # Разрешает сортировку по клику на заголовок колонки.
            'GRID_ALLOW_PIN_HEADER' => true, # Разрешает закрепление шапки грида к верху окна браузера при прокрутке.
            'GRID_ALLOW_INLINE_EDIT' => true, # Разрешает инлайн-редактирование строк.
            'GRID_ALLOW_CONTEXT_MENU' => true, # Разрешает вывод контекстного меню по клику правой кнопкой на строку.
            'GRID_HANDLE_RESPONSE_ERRORS' => true, # Включает режим дополнительной обработки ответа. В этом режиме грид проверяет, что если в ответе бэкенда будет массив messages, то грид выведет эти сообщения в попапе.
            'GRID_ALLOW_VALIDATE' => false, # Включает режим валидации сохраняемых значений при инлайн-редактировании. В этом режиме грид, перед тем как отправить запрос на сохранение, отправляет дополнительный запрос validate. Если в ответе нет массива messages или он пустой, то грид выполняет следующий запрос на сохранение. В противном случае грид выведет попап с сообщениями из messages.
            'GRID_TILE_GRID_MODE' => false, # Включает режим отображения грида в виде сетки.
            'GRID_JS_CLASS_TILE_GRID_ITEM' => false, # Позволяет переопределить JS-класс для элемента.
            'GRID_ROW_LAYOUT' => null, # Декларативный шаблон строки. Позволяет выводить в грид строки со сложной разметкой, с rowspan и colspan

            'FILTER_ID' => Helper::getFilterIdByEntity($this->entity),
            'FILTER_FILTER' => [],
            'FILTER_ENABLE_LABEL' => true,
            'FILTER_ENABLE_LIVE_SEARCH' => true,
            'FILTER_FILTER_PRESETS' => null,
            'FILTER_ENABLE_FIELDS_SEARCH' => true,
            'FILTER_HEADERS_SECTIONS' => [],
            'FILTER_DISABLE_SEARCH' => false,
        ];
    }

    public function setColumns(array $arColumnsId)
    {
        $columns = [];
        foreach ($arColumnsId as $column) {
            try {
                if (!$this->hasColumn($column)) {
                    $field = $this->entity->getField($column);
                    $columns[] = $field;
                }
            } catch (ArgumentException) {}
        }
        if (!empty($columns)) {
            $this->set('GRID_COLUMNS', $columns);
        }

        return $this;
    }

    /**
     * @return GridColumn[]
     */
    protected function getColumnsByEntity(): array
    {
        $result = [];
        foreach ($this->entity->getFields() as $field) {
            $result[] = $this->createColumnFromField($field);
        }

        return $result;
    }

    protected function getDefaultActionPanel()
    {
        $snippet = new \Bitrix\Main\Grid\Panel\Snippet();
        return [
            'GROUPS' => [
                [
                    'ITEMS' => [
                        $snippet->getEditButton(),
                        $snippet->getRemoveButton()
                    ]
                ]
            ]
        ];
    }

    /**
     * @param Field $field
     * @return GridColumn
     */
    protected function createColumnFromField(Field $field): GridColumn
    {
        $column = new GridColumn([
            'id' => $field->getName(),
            'name' => $field->getTitle(),
            'default' => $field instanceof ScalarField && ($field->isRequired() || $field->isPrimary()),
            'sort' => $field instanceof Relations\Relation ? false : $field->getName()
        ]);


        return $column;
    }

    /**
     * @return Item\GridColumn[]
     */
    public function getColumnCollection(): array
    {
        return $this->get('GRID_COLUMNS', []);
    }

    /**
     * @param string $id
     * @return GridColumn|null
     */
    public function getColumnById(string $id): ?Item\GridColumn
    {
        foreach ($this->getColumnCollection() as $column) {
            if ($column->get('id') === $id) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param string $id
     * @return bool
     */
    protected function hasColumn(string $id)
    {
        foreach ($this->getColumnCollection() as $column) {
            if ($column->get('id') === $id) {
                return true;
            }
        }

        return false;
    }

    public function toArray()
    {
        $result = $this->values;
        $result['GRID_COLUMNS'] = [];

        foreach ($this->getColumnCollection() as $column) {
            $result['GRID_COLUMNS'][] = $column->getValues();
        }

        return $result;
    }
}
