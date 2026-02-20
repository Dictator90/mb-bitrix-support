<?php

namespace MB\Bitrix\EntityView\Grid;

use Bitrix\Main\ORM\Entity;

/**
 * Alias for Grid for backward compatibility.
 * Accepts Entity and delegates to Grid.
 */
class EntityGrid extends Grid
{
    public function __construct(Entity $entity)
    {
        parent::__construct($entity);
    }
}
