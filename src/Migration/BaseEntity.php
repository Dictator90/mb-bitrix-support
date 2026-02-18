<?php

namespace MB\Bitrix\Migration;

use MB\Bitrix\Contracts\Module\Entity as ModuleEntity;

abstract class BaseEntity
{
    protected ModuleEntity $module;

    public function __construct(ModuleEntity $module)
    {
        $this->module = $module;
    }

    public function getModule(): ModuleEntity
    {
        return $this->module;
    }
}