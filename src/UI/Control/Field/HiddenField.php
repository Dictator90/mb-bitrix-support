<?php

namespace MB\Bitrix\UI\Control\Field;

use MB\Bitrix\UI\Base\Field;

class HiddenField extends Field\AbstractInputField
{
    public static function getType(): string
    {
        return 'hidden';
    }
}
