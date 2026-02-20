<?php

namespace MB\Bitrix\EntityView\Grid\Row\Action;

use Bitrix\Main\Localization\Loc;
use MB\Bitrix\EntityView\Helper;

class EditAction extends OpenAction
{
    public static function getId(): ?string
    {
        return 'edit';
    }

    protected function getText(): string
    {
        return 'Редактировать';
    }
}
