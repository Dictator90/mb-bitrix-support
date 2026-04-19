<?php

namespace MB\Bitrix\EntityView\Parameters;

use Bitrix\Main\ORM\Entity as ORMEntity;
use MB\Bitrix\EntityView\Helper;

class Filter extends Base
{
    protected ORMEntity $entity;

    /**
     * @param ORMEntity $entity
     */
    public function __construct(ORMEntity $entity)
    {
        $this->entity = $entity;
        parent::__construct();
    }

    protected function getDefault(): array
    {
        return [
            'FILTER_ID' => Helper::getFilterIdByEntity($this->entity),
            'FILTER_FILTER' => [],
            'FILTER_ENABLE_LABEL' => true,
            'FILTER_ENABLE_LIVE_SEARCH' => true,
            'FILTER_FILTER_PRESETS' => null,
            'FILTER_ENABLE_FIELDS_SEARCH' => true,
            'FILTER_HEADERS_SECTIONS' => [],
            'FILTER_DISABLE_SEARCH' => false,
        ];
    }
}
