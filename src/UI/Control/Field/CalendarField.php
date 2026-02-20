<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Security\Random;
use Bitrix\Main\Type\DateTime;
use MB\Bitrix\UI\Control\Traits\HasDisabled;
use MB\Bitrix\UI\Control\Traits\HasPlaceholder;
use MB\Bitrix\UI\Control\Traits\HasRequired;
use MB\Bitrix\UI\Base\Field;

class CalendarField extends Field\AbstractBaseField
{
    use HasPlaceholder;
    use HasRequired;
    use HasDisabled;

    public function __construct($name)
    {
        $this->setName($name);
        $this->setId("{$name}_" . Random::getString(10));
    }

    public function getHtml(): string
    {
        return <<<DOC
        <div class="ui-ctl ui-ctl-after-icon ui-ctl-datetime ui-ctl-w33">
            <div class="ui-ctl-after ui-ctl-icon-calendar"></div>
            <input 
                id="{$this->getId()}"
                name="{$this->getName()}" 
                class="ui-ctl-element" 
                type="text" 
                readonly="readonly" 
                value="{$this->getValue()}"
                placeholder="{$this->getPlaceholder()}"
                onclick="BX.calendar({node: this, field: this})"
            >
        </div>
DOC;
    }

    public function beforeSave(&$value)
    {
        $value = DateTime::tryParse($value) ?: $value;
    }
}
