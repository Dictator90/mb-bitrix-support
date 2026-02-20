<?php

namespace MB\Bitrix\UI\Traits;

use Bitrix\Main\HttpRequest;
use Bitrix\Main\Request;

trait HasRequest
{
    protected Request|HttpRequest $request;

    public string $formRequestName = 'form_id';
    public string $actionRequestName = 'action';

    public function isSaveActionRequest(): bool
    {
        return $this->request->isPost() && ($this->request->getPost($this->actionRequestName) === 'save' || 'apply');
    }

    public function isResetActionRequest(): bool
    {
        return $this->request->isPost() && $this->request->getPost($this->actionRequestName) === 'reset';
    }

    public function isDeleteActionRequest(): bool
    {
        return $this->request->isPost() && $this->request->getPost($this->actionRequestName) === 'delete';
    }

    public function isCurrentActionRequest(): bool
    {
        return static::getId() === $this->request->getPost($this->formRequestName);
    }
}
