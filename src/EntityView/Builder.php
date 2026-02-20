<?php

namespace MB\Bitrix\EntityView;

use Bitrix\Main\Context;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Filter\Filter as FilterBase;
use Bitrix\Main\Grid\Column\Columns as ColumnsBase;
use Bitrix\Main\Grid\Grid as GridBase;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\Web\Uri;
use MB\Bitrix\EntityView\Grid\Column;
use MB\Bitrix\EntityView\Grid\Grid;

Loc::loadMessages(__DIR__);
/**
 * Класс для быстрого построения Грида
 */
class Builder
{
    const EVENT_ON_PREPARE_RAW_ROWS = 'onPrepareGridRawRows';

    protected Entity $entity;
    protected GridBase $grid;
    protected FilterBase $filter;
    protected array $customComponentParams = [];

    public function __construct(Entity|string $entity)
    {
        if (is_string($entity) && method_exists($entity, 'getEntity'))  {
            $entity = $entity::getEntity();
        }

        $this->entity = $entity;
        $this->grid = new Grid($this->entity);
    }

    /**
     * Отрисовывает Toolbar и Grid с помощью mb:admin.entityview
     * Устанавливает Raw Rows для Grid
     *
     * @return void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    /**
     * Check permission for current action. Call before render() when using Builder directly.
     *
     * @param string|null $action list|add|edit|delete|view, or null to take from request
     */
    public function checkPermissions(?string $action = null): void
    {
        global $APPLICATION;

        $request = Context::getCurrent()->getRequest();
        $action = $action ?? $request->getQuery('action') ?? 'list';

        $event = new Event('mb.core', 'onEntityViewCheckPermission', [
            'entity' => $this->entity,
            'action' => $action,
        ]);
        $event->send();
        foreach ($event->getResults() as $result) {
            if ($result->getType() === EventResult::ERROR) {
                $APPLICATION->AuthForm($result->getParameters()['message'] ?? Loc::getMessage('MB_CORE_ENTITYVIEW_ACCESS_DENIED'));
            }
        }

        $postRight = $APPLICATION->GetGroupRight('mb.core');
        if ($postRight === 'D') {
            $APPLICATION->AuthForm(Loc::getMessage('MB_CORE_ENTITYVIEW_ACCESS_DENIED'));
        }
    }

    public function render()
    {
        global $APPLICATION;

        $this->fillGrid();
        $this->setRawRows();

        $APPLICATION->IncludeComponent(
            'mb:admin.entityview',
            '',
            $this->getComponentParams()
        );

        require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php";
    }

    protected function getComponentParams(): array
    {
        $request = Context::getCurrent()->getRequest();
        $listUri = (new Uri($request->getRequestUri()))->getUri();

        return [
                'GRID_ENTITY' => $this->grid,
                'ENTITY' => $this->entity,
                'SET_TITLE' => false,
                'TOOLBAR_SHOW_CREATE_BUTTON' => true,
                'COMMON_PATH_TO_ADD' => $this->grid->getSettings()->getCreateUrl(),
                'COMMON_PATH_TO_EDIT' => $this->grid->getSettings()->getEditUrl(),
                'COMMON_PATH_TO_VIEW' => $this->grid->getSettings()->getViewUrl(),
                'COMMON_PATH_TO_LIST' => $listUri,
            ] + $this->getCustomComponentParams();
    }

    /**
     * Установить кастомные сборщики строк
     *
     * @param array $array
     * @return $this
     */
    public function setCustomRowAssemblers(array $array): static
    {
        $this->getGrid()->addAdditionalFieldAssembler(...$array);
        return $this;
    }

    /**
     * Установить пользовательские параметры компонента mb:admin.entityview
     *
     * @param array $array
     * @return $this
     */
    public function setCustomComponentParams(array $array): static
    {
        $this->customComponentParams = $array;
        return $this;
    }

    /**
     * Ограничить действия в строке грида (например 'edit', 'delete', 'view').
     *
     * @param array|null $listActions null = все доступные
     * @return $this
     */
    public function setListActions(?array $listActions): static
    {
        $this->getGrid()->getSettings()->setListActions($listActions);
        return $this;
    }

    /**
     * Ограничить действия в панели грида (например 'edit', 'delete').
     *
     * @param array|null $groupActions null = все доступные
     * @return $this
     */
    public function setGroupActions(?array $groupActions): static
    {
        $this->getGrid()->getSettings()->setGroupActions($groupActions);
        return $this;
    }

    protected function getCustomComponentParams(): array
    {
        return $this->customComponentParams;
    }

    /**
     * Возаращает объект Grid
     *
     * @return \MB\Bitrix\EntityView\Grid\Grid
     */
    final public function getGrid(): Grid
    {
        return $this->grid;
    }

    final public function getFilter(): FilterBase
    {
        return $this->getGrid()->getFilter();
    }

    final public function getColumns(): ColumnsBase
    {
        return $this->getGrid()->getColumns();
    }

    /** @deprecated Use getColumns() */
    final public function getColums(): ColumnsBase
    {
        return $this->getColumns();
    }

    /**
     * Установить доступные поля для Грида
     *
     * @param array $columns
     * @return $this
     */
    public function setGridAvailableColumns(array $columns): static
    {
        $this->getGrid()->setAvailableColumns($columns);
        return $this;
    }

    /** @deprecated Use setGridAvailableColumns() */
    public function setGridAvailableColums(array $colums): static
    {
        return $this->setGridAvailableColumns($colums);
    }

    /**
     * Установить доступные поля для фильтра
     *
     * @param array $fields
     * @return $this
     */
    public function setFilterAvailableFields(array $fields)
    {
        $this->getFilter()->setAvailableFields($fields);
        return $this;
    }

    /**
     * Установить редактируемые поля для Грида
     * @param array $columns
     * @return $this
     */
    public function setGridEditableColumns(array $columns): static
    {
        $this->getGrid()->getDataProvider()->setEditableColumns($columns);
        return $this;
    }

    /** @deprecated Use setGridEditableColumns() */
    public function setGridEditableColums(array $colums): static
    {
        return $this->setGridEditableColumns($colums);
    }

    /**
     * Отключение возможности редактирования поля для Грида
     * @param array $columns
     * @return $this
     */
    public function setGridNonEditableColumns(array $columns): static
    {
        $this->getGrid()->getDataProvider()->setNonEditableColumns($columns);
        return $this;
    }

    /** @deprecated Use setGridNonEditableColumns() */
    public function setGridNonEditableColums(array $colums): static
    {
        return $this->setGridNonEditableColumns($colums);
    }

    /**
     * Изменить существующие колонки Грида
     *
     * @param array $columns
     * @return $this
     */
    public function changeGridColumns(array $columns)
    {
        $dataProvider = $this->getGrid()->getDataProvider();
        $dataProvider->prepareColumns();
        foreach ($columns as $id => $params) {
            $dataProvider->deleteColumn($id);
            $dataProvider->addColumn($id, $params);
        }
        return $this;
    }

    public function addGridColumn(\Bitrix\Main\Grid\Column\Column $column)
    {
        $dataProvider = $this->getGrid()->getDataProvider();
        $dataProvider->addColumn($column);
        return $this;
    }

    /**
     * Возвращает объект ColumnsDataProvider
     * Где можно добавить/изменить/удалить колонки Column
     *
     * @return Column\DataProvider
     */
    public function getColumnsDataProvider(): Column\DataProvider
    {
        return $this->getGrid()->getDataProvider();
    }

    /**
     * @return array|\Bitrix\Main\Grid\Column\DataProvider[]
     */
    public function getColumnsAdditionalDataProviders(): array
    {
        return $this->getGrid()->getAdditionalDataProviders();
    }

    public function fillGrid(): void
    {
        $this->getGrid()->start();
    }

    /**
     * Заполнение массива rawRows для дальнейшего вывода данных в grid
     * @return void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function setRawRows(): void
    {
        $params = $this->getGrid()->getOrmParams();
        $this->prepareRawRowsOrmParams($params);

        $event = new Event(
            'mb.core',
            self::EVENT_ON_PREPARE_RAW_ROWS,
            [
                'params' => $params,
                'entity' => $this->entity
            ]
        );
        $event->send();
        $results = $event->getResults();
        foreach ($results as $result) {
            $resParams = $result->getParameters();
            if ($resParams['params'] && is_array($resParams['params'])) {
                $params = $resParams['params'];
            }
        }

        $this->grid->setRawRows($this->entity->getDataClass()::getList($params));
    }

    protected function prepareRawRowsOrmParams(array &$params)
    {
        $params['select'] = array_merge($params['select'], $this->entity->getPrimaryArray());
    }
}
