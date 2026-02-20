<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;
use MB\Bitrix\UI\Control\Traits\HasMultiple;
use MB\Bitrix\UI\Base\Field;
use MB\Bitrix\UI\Control\Traits\HasIcon;

abstract class InputField extends Field\AbstractInputField
{
    use HasIcon;
    use HasMultiple;

    protected function beforeHtml(): string
    {
        if ($this->isMultiple()) {
            Extension::load('mb.ui.multi-input');
            return <<<DOC
            <div class="ui-form" style="padding:0!important;">
                <div class="ui-form-row ui-form-row-middle-input">
                    <div class="ui-form-content" id="form-content_{$this->getId()}">
DOC;
        }

        return <<<DOC
        <div class="ui-ctl ui-ctl-textbox ui-ctl-w33 {$this->getContainerClass()}">
            {$this->getIconsHtml()}
DOC;
    }

    protected function afterHtml(): string
    {
        $value = $this->getValue() ?: [];
        if (!is_array($value)) {
            $value = [$value];
        }
        $value = array_filter($value);
        $value = Json::encode($value);

        if ($this->isMultiple()) {
            return <<<DOC
                    </div>
                </div>
                <div class="ui-form-row">
                    <label class="ui-ctl ui-ctl-file-btn">
                        <div id="form-add-btn_{$this->getId()}" class="ui-ctl-label-text">+ Добавить</div>
                    </label>
                </div>
            </div>
            <script>
                window.MB.UI.MultiInput?.create('#form-content_{$this->getId()}', {
                    rowSelector: ".ui-form-row",
                    removeBtnSelector: "button.ui-ctl-icon-clear",
                    addBtnSelector: "#form-add-btn_{$this->getId()}",
                    data: {
                        name: '{$this->getName()}[]',
                        items: {$value}
                    }
                });
            </script>
DOC;

        }
        return '</div>';
    }

    public function getHtml(): string
    {
        if ($this->isMultiple()) {
            return <<<DOC
            {$this->beforeHtml()}
            <div class="ui-form-row">
                <div class="ui-ctl ui-ctl-textbox ui-ctl-ext-before-icon ui-ctl-ext-after-icon">
                    <button onclick="return false" class="ui-ctl-before mb-sortable-handle">☰</button>
                    <button onclick="return false" class="ui-ctl-ext-after ui-ctl-icon-clear"></button>
                    <input
                        type="{$this->getType()}" 
                        class="ui-ctl-element {$this->getClass()}"
                        {$this->getStyle()}
                        placeholder="{$this->getPlaceholder()}" 
                        {$this->getExAttributes()}
                    />
                </div>
            </div>
            {$this->afterHtml()}
DOC;
        }

        return parent::getHtml();
    }

}
