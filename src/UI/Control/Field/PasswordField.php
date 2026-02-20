<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\UI\Extension;

/**
 * Поле ввода пароля с возможностью отображения/скрытия значения.
 * Использует иконку глаза для переключения видимости пароля.
 *
 * @package MB\Bitrix\UI\Control\Field
 */
class PasswordField extends TextField
{
    protected $iconAfter = self::ICON_EYE_OPENED;

    public static function getType(): string
    {
        return 'password';
    }

    protected function getIconAfterHtml()
    {
        $class = self::ICON_AFTER_CLASS;
        return <<<DOC
            <button id="{$this->getId()}_{$this::getType()}" class="{$class} {$this->getIconAfter()}"></button>
DOC;
    }

    protected function afterHtml(): string
    {
        Extension::load('mb.ui.controls');
        $parent = parent::afterHtml();
        return <<<DOC
        <script>
            new MB.UI.Controls.PasswordSwitcher({
                inputId: '{$this->getId()}',
                targetId: '{$this->getId()}_{$this::getType()}'
            });
        </script>  
        {$parent}
DOC;
    }
}
