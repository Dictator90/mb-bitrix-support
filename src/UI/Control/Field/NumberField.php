<?php

namespace MB\Bitrix\UI\Control\Field;

use MB\Bitrix\UI\Control\Traits\HasIcon;
use MB\Bitrix\UI\Base\Field;

class NumberField extends Field\AbstractInputField
{
    use HasIcon;

    public static function getType(): string
    {
        return 'number';
    }

    protected function beforeHtml(): string
    {
        return <<<DOC
        <div class="ui-ctl ui-ctl-textbox ui-ctl-w33 {$this->getContainerClass()}">
        {$this->getIconsHtml()}
DOC;
    }

    protected function afterHtml(): string
    {
        return '</div>';
    }
}
