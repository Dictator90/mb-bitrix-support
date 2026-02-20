<?php

namespace MB\Bitrix\EntityView\Grid;

use Bitrix\Main\Grid\Settings as SettingsBase;
use Bitrix\Main\ORM\Entity;
use MB\Bitrix\Traits;
use MB\Bitrix\EntityView\Helper;

class Settings extends SettingsBase
{
    use Traits\FluentTrait;

    protected bool $canView;
    protected bool $canEdit;
    protected string $editUrl;
    protected string $viewUrl;
    protected string $createUrl;

    /** @var string[]|null allowed row action ids (e.g. 'edit', 'delete', 'view'), null = all */
    protected ?array $listActions = null;
    /** @var string[]|null allowed panel action ids (e.g. 'edit', 'delete'), null = all */
    protected ?array $groupActions = null;

    protected Entity $entity;

    public function __construct(Entity $entity, $params = [])
    {
        $this->entity = $entity;
        $this->canView = (bool)($params['CAN_VIEW'] ?? true);
        $this->canEdit = (bool)($params['CAN_EDIT'] ?? true);
        $this->editUrl = (string)($params['EDIT_URL'] ?? '');
        $this->viewUrl = (string)($params['VIEW_URL'] ?? '');

        $this->configure([
            'allowed_properties' => ['column_settings', 'panel_settings', 'row_settings', 'filter_settings'],
            'validation' => [
                'column_settings' => fn($val) => !is_array($val) || empty($val) ? [] : $val,
                'panel_settings' => fn($val) => !is_array($val) || empty($val) ? [] : $val,
                'row_settings' => fn($val) => !is_array($val) || empty($val) ? [] : $val,
                'filter_settings' => fn($val) => !is_array($val) || empty($val) ? [] : $val,
            ]
        ]);

        parent::__construct(['ID' => Helper::getGridIdByEntity($this->entity)]);
    }

    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * Устанавливает возможность просмотра элемента сущности
     *
     * @param bool $value
     * @return $this
     */
    public function configureCanView(bool $value = true): static
    {
        $this->canView = $value;
        return $this;
    }

    /**
     * Возвращает возможность просмотра элемента сущности
     *
     * @return bool
     */
    public function isCanView(): bool
    {
        return $this->canView;
    }

    /**
     * Устанавливает возможность редактирования элемента сущности
     *
     * @param bool $value
     * @return $this
     */
    public function configureCanEdit(bool $value = true): static
    {
        $this->canEdit = $value;
        return $this;
    }

    /**
     * Возвращает возможность редактирования элемента сущности
     *
     * @return bool
     */
    public function isCanEdit(): bool
    {
        return $this->canEdit;
    }

    /**
     * Устанавливает ссылку на редактирование элемента сущности
     *
     * @param string $url
     * @return $this
     */
    public function setEditUrl(string $url): static
    {
        $this->editUrl = $url;
        return $this;
    }

    public function setCreateUrl(string $url): static
    {
        $this->createUrl = $url;
        return $this;
    }

    /**
     * Возвращает ссылку на редактирование элемента сущности
     *
     * @return string
     */
    public function getEditUrl(): string
    {
        return $this->editUrl;
    }

    public function getCreateUrl(): string
    {
        return $this->createUrl;
    }

    /**
     * @return string
     */
    public function getViewUrl(): string
    {
        return $this->viewUrl;
    }

    /**
     * @param string $viewUrl
     * @return $this
     */
    public function setViewUrl(string $viewUrl): static
    {
        $this->viewUrl = $viewUrl;
        return $this;
    }

    public function setListActions(?array $listActions): static
    {
        $this->listActions = $listActions;
        return $this;
    }

    public function getListActions(): ?array
    {
        return $this->listActions;
    }

    public function setGroupActions(?array $groupActions): static
    {
        $this->groupActions = $groupActions;
        return $this;
    }

    public function getGroupActions(): ?array
    {
        return $this->groupActions;
    }
}
