<?php

namespace MB\Bitrix\EntityView;

use Bitrix\Main\Context;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM;
use Bitrix\Main\Web\Uri;

Loc::loadMessages(__FILE__);

abstract class Base
{
    protected ORM\Entity $entity;
    protected HttpRequest $request;
    protected Uri $uri;

    protected Parameters\Common $parameters;

    abstract public function render(): void;

    public function __construct(ORM\Entity|string $entity)
    {
        if (is_string($entity) && method_exists($entity, 'getEntity'))  {
            $entity = $entity::getEntity();
        }

        $this->entity = $entity;
        $this->request = Context::getCurrent()->getRequest();
        $this->uri = new Uri($this->request->getScriptFile());

        $this->parameters = new Parameters\Common($this->entity);
    }

    public function getEntity(): ORM\Entity
    {
        return $this->entity;
    }

    public function getParameters(): Parameters\Common
    {
        return $this->parameters;
    }

    public function configureSetTitle(bool $value = true): static
    {
        $this->parameters->set('COMMON_SET_TITLE', $value);
        return $this;
    }

    public function isSetTitle()
    {
        return $this->parameters->get('COMMON_SET_TITLE', true);
    }

    public function setPathToAdd(string $path, $disableAddPrimary = false): static
    {
        $this->parameters->set('COMMON_PATH_TO_ADD', ($disableAddPrimary ? $path : $this->prepareEditPath($path, true)));
        return $this;
    }

    public function getPathToAdd()
    {
        $this->uri->addParams(['action' => Actions::ADD->value]);
        return $this->parameters->get(
            'COMMON_PATH_TO_ADD',
            $this->prepareEditPath($this->uri->getUri(), true)
        );
    }

    public function setPathToView(string $path, $disableAddPrimary = false): static
    {
        $this->parameters->set('COMMON_PATH_TO_VIEW', ($disableAddPrimary ? $path : $this->prepareEditPath($path, true)));
        return $this;
    }

    public function getPathToView()
    {
        $this->uri->addParams(['action' => Actions::VIEW->value]);
        return $this->parameters->get(
            'COMMON_PATH_TO_VIEW',
            $this->prepareEditPath($this->uri->getUri())
        );
    }

    public function setPathToEdit(string $path, $disableAddPrimary = false): static
    {
        $this->parameters->set('COMMON_PATH_TO_EDIT', ($disableAddPrimary ? $path : $this->prepareEditPath($path)));
        return $this;
    }

    public function getPathToEdit()
    {
        $this->uri->addParams(['action' => Actions::EDIT->value]);
        return $this->parameters->get(
            'COMMON_PATH_TO_EDIT',
            $this->prepareEditPath($this->uri->getUri())
        );
    }

    public function setPathToList(string $path): static
    {
        $this->parameters->set('COMMON_PATH_TO_LIST', $path);
        return $this;
    }

    public function getPathToList()
    {
        return $this->parameters->get('COMMON_PATH_TO_LIST',  $this->uri->getUri());
    }

    /**
     * Check permission for entity view action (list, add, edit, delete, view).
     * Fires onEntityViewCheckPermission event; then checks module right.
     *
     * @param string|null $action list|add|edit|delete|view, or null to take from request
     */
    public function checkPermissions(?string $action = null): void
    {
        global $APPLICATION;

        $action = $action ?? $this->request->getQuery('action') ?? 'list';
        $event = new Event(
            'mb.core',
            'onEntityViewCheckPermission',
            [
                'entity' => $this->entity,
                'action' => $action,
            ]
        );
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

    public function configureCanEdit(bool $value = true): static
    {
        $this->parameters->set('COMMON_CAN_EDIT', $value);
        return $this;
    }

    public function isCanEdit()
    {
        return $this->parameters->get('COMMON_CAN_EDIT', true);
    }

    public function configureAdminMode(bool $value = true): static
    {
        $this->parameters->set('COMMON_ADMIN_MODE', $value);
        return $this;
    }

    public function isAdminMode()
    {
        return $this->parameters->get('COMMON_ADMIN_MODE', true);
    }

    protected function prepareEditPath(string $path, $setZero = false)
    {
        $uri = new Uri($path);
        $params = [];
        foreach ($this->entity->getPrimaryArray() as $primary) {
            $params[$primary] = $setZero ? 0 : "#{$primary}#";
        }

        $uri->addParams($params);

        return urldecode($uri->getUri());
    }
}
