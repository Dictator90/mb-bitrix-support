<?php

namespace MB\Bitrix\EntityView\Grid\Row;

use Bitrix\Main\Grid\Row\Action\Action as ActionInterface;
use Bitrix\Main\Grid\Row\Action\DataProvider as BaseDataProvider;

use Bitrix\Main\ORM\Entity;
use MB\Bitrix\EntityView\Grid\Settings;

class DataProvider extends BaseDataProvider
{
    protected Entity $entity;

    /**
     * @var ActionInterface[]|array
     */
    protected array $rawActions = [];

    public function __construct(Settings $settings)
    {
        $this->entity = $settings->getEntity();
        parent::__construct($settings);
    }

    public function prepareActions(): array
    {
        return $this->getRawActions() ?: [];
    }

    public function addAction(ActionInterface $action): static
    {
        $this->rawActions[] = $action;
        return $this;
    }

    public function deleteAction(string $id): static
    {
        foreach ($this->rawActions as $k => $action) {
            if ($action::getId() === $id) {
                unset($this->rawActions[$k]);
                $this->rawActions = array_values($this->rawActions);
            }
        }

        return $this;
    }

    public function getAction(string $id): ?ActionInterface
    {
        foreach ($this->rawActions as $action) {
            if ($action::getId() === $id) {
                return $action;
            }
        }

        return null;
    }

    public function getRawActions(): array
    {
        return $this->rawActions ?: $this->getDefaultActions();
    }

    public function getDefaultActions(): array
    {
        $result = [];

        if ($this->getSettings()->isCanView() && !$this->getSettings()->isCanEdit()) {
            $result[] = new Action\ViewAction($this->entity, $this->getSettings()->getViewUrl());
        } elseif ($this->getSettings()->isCanEdit()) {
            $result[] = new Action\EditAction($this->entity, $this->getSettings()->getEditUrl());
            $result[] = new Action\DeleteAction($this->entity, $this->getSettings());
        }

        $allowed = $this->getSettings()->getListActions();
        if ($allowed !== null) {
            $result = array_values(array_filter($result, function ($action) use ($allowed) {
                return in_array($action::getId(), $allowed, true);
            }));
        }

        return $result;
    }
}
