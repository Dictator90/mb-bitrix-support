<?php

namespace MB\Bitrix\UI\Control\Field;

use MB\Bitrix\UI\Control\Traits\HasClass;
use MB\Bitrix\UI\Control\Traits\HasDisabled;
use MB\Bitrix\UI\Control\Traits\HasMultiple;
use MB\Bitrix\UI\Control\Traits\HasStyle;
use MB\Bitrix\UI\Base\Field\AbstractOptionsField;

class DropDownField extends AbstractOptionsField
{
    use HasMultiple;
    use HasStyle;
    use HasClass;
    use HasDisabled;

    public function getHtml(): string
    {
        $iconNode = $this->isMultiple() ? '' : '<div class="ui-ctl-after ui-ctl-icon-angle"></div>';
        return <<<DOC
            <div class="{$this->getContainerClass()}">
                {$iconNode}
                <select class="ui-ctl-element" 
                        id="{$this->getId()}" 
                        name="{$this->getName()}" 
                        {$this->getMultipleHtml()}
                        {$this->getStyle()}
                        class="{$this->getClass()}"
                        {$this->getDisabled()}
                >
                    {$this->getOptionsHtml()}
                </select>
            </div>
DOC;
    }

    protected function getContainerClass()
    {
        $disabledClass = $this->isDisabled() ? 'ui-ctl-disabled' : '';
        $class = $this->isMultiple() ? 'ui-ctl ui-ctl-multiple-select' : 'ui-ctl ui-ctl-after-icon ui-ctl-dropdown';
        return "{$class} {$disabledClass}";
    }

    protected function getMultipleHtml(): string
    {
        return $this->isMultiple() ? 'multiple' : '';
    }

    protected function getOptionsHtml(): string
    {
        $result = '';
        $options = $this->isMultiple() ? $this->getRawOptions() : $this->getOptions();
        foreach ($options as $value => $optionName) {
            $selected = $this->getValue() == $value ? 'selected' : null;
            $result .= "<option value=\"{$value}\" {$selected}>{$optionName}</option>";
        }

        return $result;
    }

}
