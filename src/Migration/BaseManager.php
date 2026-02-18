<?php

namespace MB\Bitrix\Migration;

use MB\Bitrix\Contracts\Module\Entity;

abstract class BaseManager
{
    public function __construct(protected Entity $module)
    {}

    public static function create(Entity $module)
    {
        return new static($module);
    }
}
