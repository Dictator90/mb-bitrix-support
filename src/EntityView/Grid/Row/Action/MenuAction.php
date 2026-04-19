<?php

namespace MB\Bitrix\EntityView\Grid\Row\Action;

use Bitrix\Main\Grid\Row;

/**
 * Класс Экшена Подменю в пункте меню
 */
class MenuAction extends Row\Action\MenuAction
{

    /**
     * @internal Extend in project code for real admin UX; empty default keeps grid render valid.
     */
    protected function getText(): string
    {
        return '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getMenu(): array
    {
        return [];
    }
}
