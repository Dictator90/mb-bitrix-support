<?php

namespace MB\Bitrix\Migration;

use MB\Bitrix\Contracts\Migration\Entity as MigrationEntityContract;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntity;

abstract class BaseEntity implements MigrationEntityContract
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

    abstract public function check(): bool;

    abstract public function up(): Result;

    abstract public function down(): Result;
}
