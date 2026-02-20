<?php

namespace MB\Bitrix\EntityView\Parameters;

use Bitrix\Main\ORM\Entity as ORMEntity;

class Entity extends Base
{
    protected ORMEntity $entity;

    public function __construct(ORMEntity $entity)
    {
        $this->entity = $entity;
        parent::__construct();
    }

    protected function getDefault(): array
    {
        return [

        ];
    }
}
