<?php

namespace MB\Bitrix\UI\Control\Field;

use MB\Bitrix\UI\Base\Field;

class StringField extends Field\AbstractBaseField
{
    public function __construct($value = null)
    {
        $this->value = $value;
    }

    public function getHtml(): string
    {
        return <<<DOC
            <div class="ui-ctl ui-ctl-textbox ui-ctl-w33">{$this->getValue()}</div>            
DOC;
    }
}
