<?php

namespace MB\Bitrix\UI\Base\Field;

use Bitrix\Main\Security\Random;
use MB\Bitrix\UI\Traits\HasClass;
use MB\Bitrix\UI\Traits\HasDisabled;
use MB\Bitrix\UI\Traits\HasJsEvent;
use MB\Bitrix\UI\Traits\HasName;
use MB\Bitrix\UI\Traits\HasPlaceholder;
use MB\Bitrix\UI\Traits\HasReadonly;
use MB\Bitrix\UI\Traits\HasRequired;
use MB\Bitrix\UI\Traits\HasSize;

abstract class AbstractInputField extends AbstractBaseField
{
    use HasName;
    use HasPlaceholder;
    use HasJsEvent;
    use HasClass;
    use HasRequired;
    use HasDisabled;
    use HasReadonly;

    abstract public static function getType(): string;

    public function __construct($name)
    {
        $this->setName($name);
        $this->setId("{$name}_" . Random::getString(10));
    }

    public function getHtml(): string
    {
        return <<<DOC
        {$this->beforeHtml()}
        <input
            id="{$this->getId()}" 
            type="{$this->getType()}" 
            name="{$this->getName()}" 
            class="ui-ctl-element {$this->getClass()}"
            {$this->getStyle()}
            placeholder="{$this->getPlaceholder()}" 
            value="{$this->getValue()}"
            {$this->getJsEvents()}
            {$this->getReadonly()}
            {$this->getRequired()}
            {$this->getDisabled()}
            {$this->getExAttributes()}
        />
        {$this->afterHtml()}
DOC;
    }

    protected function getExAttributes(): string
    {
        $result = [];
        foreach ($this->exAttributes() as $name => $value) {
            $result[] = "$name=\"$value\"";
        }
        return implode(' ', $result);
    }

    protected function beforeHtml(): string
    {
        return '';
    }

    protected function afterHtml(): string
    {
        return '';
    }

    protected function exAttributes(): array
    {
        return [];
    }

    public function toArray()
    {
        $result = [
            'entityClass' => get_called_class(),
            'name' => $this->getName(),
            'placeholder' => $this->getPlaceholder(),
            'type' => $this->getType(),
            'classList' => $this->getClassArray(),
            'events' => $this->getJsEventsArray(),
            'required' => $this->isRequired(),
            'readonly' => $this->isReadonly(),
            'disabled' => $this->isDisabled(),
            'exAttributes' => $this->exAttributes(),
            'beforeInput' => $this->beforeHtml(),
            'afterInput' => $this->afterHtml(),
        ];

        return array_merge(parent::toArray(), $result);
    }
}
