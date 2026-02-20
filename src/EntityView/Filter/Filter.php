<?php

namespace MB\Bitrix\EntityView\Filter;

use Bitrix\Main\EventResult;
use Bitrix\Main\Filter\Filter as FilterBase;
use Bitrix\Main\ORM\Entity;
use MB\Bitrix\EntityView\Helper;
use MB\Bitrix\Traits;

/**
 *
 * @event Bitrix\Main\EventResult onAfterFilterGetFields
 */
class Filter extends FilterBase
{
    use Traits\BitrixEventsObservableTrait;

    protected Entity $entity;
    protected ?array $availableFields = null;

    public function __construct(Entity $entity, $extraDataProviders = null, $params = null) {

        $this->entity = $entity;

        $id = Helper::getFilterIdByEntity($this->entity);
        $dataProvider = new DataProvider($this->entity);

        $this->attach(module()->getId(), Events::EVENT_ON_AFTER_GET_FIELDS);

        parent::__construct($id, $dataProvider, $extraDataProviders, $params);
    }

    public function setAvailableFields(array $fields)
    {
        $this->availableFields = $fields;
        return $this;
    }

    public function getFields()
    {
        $fields = parent::getFields();
        if ($this->availableFields) {
            foreach ($fields as $i => $field) {
                if (!in_array($field->getId(), $this->availableFields)) {
                    unset($fields[$i]);
                }
            }
        }
        $this->onAfterGetFieldsProcess($fields);

        return $fields;
    }

    protected function onAfterGetFieldsProcess(&$fields)
    {
        $this->notify(
            Events::EVENT_ON_AFTER_GET_FIELDS,
            [
                'provider' => $this->getEntityDataProvider(),
                'fields' => $fields
            ],
            function (array $eventResults) use (&$fields) {
                foreach ($eventResults as $eventResult) {
                    if ($eventResult->getType() === EventResult::SUCCESS) {
                        $params = $eventResult->getParameters();
                        if ($params['fields']) {
                            $fields = $params['fields'];
                        }
                    }
                }
            }
        );
    }
}
