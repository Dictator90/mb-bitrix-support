<?php

namespace MB\Bitrix\UI\Control\Field;

use MB\Bitrix\UI\Base\Field;

class NonEditableField extends Field\AbstractBaseField
{
    protected bool $renderInput = false;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getHtml(): string
    {
        $input = "";
        if ($this->renderInput) {
            $input = <<<DOC
                <input type="hidden" name="{$this->getName()}" value="{$this->getValue()}">
DOC;
        }
        return <<<DOC
        <div class="ui-ctl ui-ctl-textbox">
            {$this->getValue()}
            {$input}
        </div>
DOC;
    }

    public function configureRenderInput(bool $value = true)
    {
        $this->renderInput = $value;
        return $this;
    }
}
