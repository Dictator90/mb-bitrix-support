<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\UserTable;

class UserField extends InputField
{
    public static function getType(): string
    {
        return 'hidden';
    }

    protected function beforeHtml(): string
    {
        $this->configureReadonly();
        $this->setJsEvents([
            'onchange' => "window['{$this->getForm()->getId()}']['host_{$this->id}'].value=this.value"
        ]);
        //$this->setIconAfter(HasIcon::ICON_SEARCH);

        $value = null;
        if ($id = $this->getValue()) {
            $user = UserTable::query()->setSelect(['ID', 'NAME', 'LAST_NAME'])->where('ID', $id)->fetch();
            $value = "[{$user['ID']}] {$user['NAME']} {$user['LAST_NAME']}";
        }
        return <<<DOC
        <div class="ui-ctl-inline ui-ctl-textbox ui-ctl-w33 {$this->getContainerClass()}">
            {$this->getIconsHtml()}
            <input class="ui-ctl-element" type="text" style="min-width:180px" id="host_{$this->id}" readonly value="{$value}">
            
DOC;
    }

    protected function afterHtml(): string
    {
        $languageId = LANGUAGE_ID;
        $formName = $this->getForm()->getId();

        return <<<DOC
        <div 
            class="ui-btn ui-btn-sm ui-btn-primary" 
            value="..."
            onclick="window.open('/bitrix/admin/user_search.php?lang={$languageId}&FN={$formName}&FC={$this->getName()}', '', 'scrollbars=yes,resizable=yes,width=760,height=560,top='+Math.floor((screen.height - 560)/2-14)+',left='+Math.floor((screen.width - 760)/2-5))"
        >
            <span class="ui-btn-text">Выбрать</span>
        </div>
        </div>

DOC;
    }
}
