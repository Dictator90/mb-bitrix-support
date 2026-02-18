<?php

namespace MB\Bitrix\Migration;

use MB\Bitrix\Contracts\Module\Entity;

abstract class BaseEntityManager
{
    abstract public function getEntityClass(): string;
    abstract public function update(): Result;
    abstract public function deleteAll(): Result;

    public function __construct(protected Entity $module)
    {}

    public static function create(Entity $module): static
    {
        return new static($module);
    }
}
