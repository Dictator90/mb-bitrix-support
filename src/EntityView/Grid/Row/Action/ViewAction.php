<?php

namespace MB\Bitrix\EntityView\Grid\Row\Action;

use Bitrix\Main\Localization\Loc;

class ViewAction extends OpenAction
{
    public static function getId(): ?string
    {
        return 'view';
    }

    protected function getText(): string
    {
        return 'Просмотр';
    }
}
