<?php

namespace MB\Bitrix\EntityView;

class Entity extends Base
{
    protected ?array $listActions = ['edit', 'delete'];
    protected ?array $groupActions = ['edit', 'delete'];

    public function disableListActions(): static
    {
        $this->listActions = null;
        return $this;
    }

    public function disableGroupActions(): static
    {
        $this->groupActions = null;
        return $this;
    }

    public function configureListActions(array $listActions): static
    {
        $this->listActions = $listActions;
        return $this;
    }

    public function configureGroupActions(array $groupActions): static
    {
        $this->groupActions = $groupActions;
        return $this;
    }

    public function setGridId(string $value): static
    {
        $this->parameters->set('GRID_ID', $value);
        return $this;
    }

    public function getGridId()
    {
        if (!$this->parameters->get('GRID_ID')) {
            $this->parameters->set('GRID_ID', 'GRID_' . $this->entity->getDBTableName());
        }
        return $this->parameters->get('GRID_ID');
    }

    public function setFilterId(string $value): static
    {
        $this->parameters->set('FILTER_ID', $value);
        return $this;
    }

    public function getFilterId()
    {
        if (!$this->parameters->get('FILTER_ID')) {
            $this->parameters->set('FILTER_ID', Helper::getGridIdByEntity($this->entity));
        }
        return $this->parameters->get('FILTER_ID');
    }

    public function getComponentParams(): array
    {
        $result = $this->parameters->toArray();
        $result['COMMON_PATH_TO_ADD'] = $this->getPathToAdd();
        $result['COMMON_PATH_TO_VIEW'] = $this->getPathToView();
        $result['COMMON_PATH_TO_EDIT'] = $this->getPathToEdit();
        $result['COMMON_PATH_TO_LIST'] = $this->getPathToList();

        return $result;
    }

    public function render(): void
    {
        global $APPLICATION;

        $this->checkPermissions($this->request->getQuery('action') ?: 'list');

        $builder = new Builder($this->entity);
        if ($this->listActions !== null) {
            $builder->setListActions($this->listActions);
        }
        if ($this->groupActions !== null) {
            $builder->setGroupActions($this->groupActions);
        }
        $builder->fillGrid();
        $builder->setRawRows();
        $params = array_merge($this->getComponentParams(), [
            'GRID_ENTITY' => $builder->getGrid(),
            'ENTITY' => $this->entity,
            'TOOLBAR_SHOW_CREATE_BUTTON' => $this->parameters->get('TOOLBAR_SHOW_CREATE_BUTTON', true),
        ]);

        $APPLICATION->IncludeComponent(
            'mb:admin.entityview',
            '',
            $params
        );

        require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php";
    }
}
