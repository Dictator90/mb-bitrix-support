<?php

namespace MB\Bitrix\Component;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;

abstract class ControllerComponent extends BaseComponent implements Controllerable
{
    protected ErrorCollection $errorCollection;

    public function __construct($component = null)
    {
        $this->errorCollection = new ErrorCollection();
        parent::__construct($component);
    }

    abstract public function configureActions(): array;

    protected function listKeysSignedParameters(): array
    {
        return [];
    }

    protected function getDefaultPreFilters(): array
    {
        return [
            new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET, ActionFilter\HttpMethod::METHOD_POST]),
            new ActionFilter\Csrf(),
        ];
    }

    public function addError(string $text, int|string $code = 0, $customData = null): static
    {
        $this->errorCollection->setError(new Error($text, $code, $customData));
        return $this;
    }

    /** @param Error[] $errors */
    public function addErrors(array $errors): static
    {
        $this->errorCollection->add($errors);
        return $this;
    }

    /** @return Error[] */
    public function getErrors(): array
    {
        return $this->errorCollection->toArray();
    }

    public function getErrorByCode($code): ?Error
    {
        return $this->errorCollection->getErrorByCode($code);
    }

    public function hasErrors(): bool
    {
        return !$this->errorCollection->isEmpty();
    }

    public function showErrors(): void
    {
        foreach ($this->getErrors() as $error) {
            $this->__showError($error->getMessage(), $error->getCode());
        }
    }

    protected function getAlllistKeysSignedParameters(): array
    {
        return array_keys($this->arParams);
    }
}
