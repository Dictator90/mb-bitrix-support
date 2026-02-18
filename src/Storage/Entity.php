<?php

namespace MB\Bitrix\Storage;

use Bitrix\Main\ORM\Entity as BitrixEntity;

class Entity extends BitrixEntity
{
    public function createObject($setDefaultValues = true)
    {
        $objectClass = $this->getObjectClass();
        $entityObjectClass = new $objectClass($setDefaultValues);
        $entityObjectClass::$dataClass = $this->getDataClass();
        return $entityObjectClass;
    }
}