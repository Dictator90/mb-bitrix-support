<?php

namespace MB\Bitrix\EntityView\Grid\Row\Assembler\Field;

use Bitrix\Main\Localization\Loc;
use MB\Bitrix\UI\Control\Field\SwitcherField;
use MB\Bitrix\EntityView\Grid\Row\Assembler\BaseFieldAssembler;
use MB\Bitrix\Config;

Loc::loadLanguageFile(__FILE__);

class SwitcherFieldAssembler extends BaseFieldAssembler
{
    protected string $type = 'custom';

    protected function prepareColumn($value)
    {
        return $value == SwitcherField::SWITCH_ON
            ? (module('mb.core')->getLang('UI_FIELD_SWITCHER_ON') ?: 'Y')
            : (module('mb.core')->getLang('UI_FIELD_SWITCHER_OF') ?: 'N');
    }

    protected function prepareData($value, $columnId)
    {
        $userField = new SwitcherField($columnId);
        $userField->configureSize('small');
        $userField->setStyle([
            'margin-top' => '15px',
        ]);
        $userField->setValue($value);

        ob_start();
        $userField->render();

        return ob_get_clean();
    }
}
