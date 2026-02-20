<?php

namespace MB\Bitrix\UI\Base\Grid;

use MB\Bitrix\Reference\Common;

class TemplateCollection extends Common\Collection
{
    public static function getItemReference()
    {
        return Template::class;
    }

    public function getString()
    {
        $ids = [];
        foreach ($this->collection as $obj) {
            $ids[] = $obj->getId();
        }
        return implode(' ', $ids);
    }
}
