<?php

namespace MB\Bitrix\Component;

use Bitrix\Main;
use Bitrix\Main\Engine\CurrentUser;
use MB\Bitrix\Contracts\Component\LangProviderInterface;

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

    public function setLangProvider(?LangProviderInterface $langProvider): static
    {
        $this->langProvider = $langProvider;
        return $this;
    }

    protected function getRequiredModules(): array
    {
        return [];
    }

    protected function loadModules(): void
    {
        foreach ($this->getRequiredModules() as $requiredModule) {
            if (!Main\Loader::includeModule($requiredModule)) {
                $message = $this->getLang('COMP_ERROR_MODULE_NOT_INSTALLED', ['#MODULE_ID#' => $requiredModule]);
                throw new Main\SystemException($message);
            }
        }
    }

    protected function getParameter(string $key): string
    {
        return isset($this->arParams[$key]) ? (string) $this->arParams[$key] : '';
    }

    protected function getLang(string $code, $replace = null, $language = null): string
    {
        if ($this->langProvider !== null) {
            return $this->langProvider->getLang($code, $replace !== null ? (array) $replace : null, $language);
        }
        return $code;
    }

    public function addError(string $text, int|string $code = 0, $customData = null): void
    {
        $this->errorCollection->setError(new Main\Error($text, $code, $customData));
    }

    /** @return Main\Error[] */
    public function getErrors(): array
    {
        return $this->errorCollection->toArray();
    }

    public function getErrorByCode($code): ?Main\Error
    {
        return $this->errorCollection->getErrorByCode($code);
    }

    public function hasErrors(): bool
    {
        return !$this->errorCollection->isEmpty();
    }

    public function showErrors(): void
    {
        if (CurrentUser::get()->isAdmin()) {
            foreach ($this->getErrors() as $error) {
                $this->__showError($error->getMessage(), $error->getCode());
            }
        }
    }
}
