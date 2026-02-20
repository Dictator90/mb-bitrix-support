<?php

namespace MB\Bitrix\EntityView\Filter;

use Bitrix\Main\EventResult;
use Bitrix\Main\Filter;
use Bitrix\Main\Filter\Settings;
use Bitrix\Main\Grid\Column\Type;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\ORM\Fields\Field;
use MB\Bitrix\EntityView\Helper;
use MB\Bitrix\Traits\BitrixEventsObservableTrait;

/**
 * Дата провайдер для фильтра.
 * Отдает и обрабатывает поля для фильтра грида
 *
 * @event onFilterDataProviderPrepareFields
 * @event onFilterDataProviderPrepareFieldData
 *
 * @package MB\Bitrix
 */
class DataProvider extends Filter\DataProvider
{
    use BitrixEventsObservableTrait;

    protected Entity $entity;
    protected Settings $settings;

    protected ?array $fields = null;

    public function __construct(Entity $entity)
    {
        $this->entity = $entity;
        $this->settings = new Settings(['ID' => Helper::getFilterIdByEntity($this->entity)]);

        $this->attach('mb.core', Events::EVENT_ON_PREPARE_FIELD_DATA);
        $this->attach('mb.core', Events::EVENT_ON_PREPARE_FIELDS);
    }

    /**
     * @return string
     */
    protected function getTableClass()
    {
        return $this->entity->getDataClass();
    }

    /**
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     */
    public function getFields()
    {
        if($this->fields === null) {
            $fields = array_keys($this->getEntity()->getFields());
            $hiddenFields = $this->getHiddenFields();
            $fields = array_diff($fields, $hiddenFields);
            foreach($fields as $placeholder) {
                $this->fields[$placeholder] = [
                    'TITLE' => $this->getEntity()->getField($placeholder)->getTitle(),
                ];
            }
        }

        return $this->fields;
    }

    /**
     * @return Entity
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return array
     */
    protected function getHiddenFields()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getGetListParameters()
    {
        $result = [
            'select' => ['*'],
        ];

        return $result;
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function prepareFields(): array
    {
        $result = [];

        foreach ($this->getEntity()->getFields() as $field) {
            if (!$field instanceof Fields\ScalarField) {
                continue;
            }

            $result[] = $this->prepareField($field);
        }


        $this->onPrepareFieldsProcess($result);
        return $result;
    }

    /**
     * Transform filter value for a field into getList filter condition.
     * Override in subclasses for custom field types or complex filter logic.
     *
     * @param array $filter filter array to modify (by reference)
     * @param string $fieldID field identifier
     */
    public function prepareListFilterParam(array &$filter, $fieldID)
    {
        // Default: no transformation. Override to map UI filter values to ORM filter.
    }

    /**
     * Возвращает доп опции по field ID
     * @param $fieldID
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     */
    public function prepareFieldData($fieldID)
    {
        $field = $this->entity->getField($fieldID);
        $result = [];
        if ($field instanceof Fields\StringField) {
            $result = [
                'additionalFilter' => ['isEmpty', 'hasAnyValue']
            ];
        }
        elseif ($field instanceof Fields\BooleanField) {
            $result = [
                'valueType' => 'numeric'
            ];
        }
        elseif ($field instanceof Fields\DateField) {
            $result = [
                'time' => $field instanceof \Bitrix\Main\ORM\Fields\DateTimeField,
                'additionalFilter' => ['isEmpty', 'hasAnyValue']
            ];
        }
        elseif ($field instanceof Fields\EnumField) {
            $valAr = $field->getValues();
            $items = [];
            $dataClass = $field->getEntity()->getDataClass();
            foreach ($valAr as $v) {
                $title = $v;
                if (method_exists($dataClass, 'getEnumTitle')) {
                    $title = $dataClass::getEnumTitle($v);
                }
                $items[$v] = $title;
            }
            if (empty($items)) {
                return null;
            }
            $result = [
                'items' => $items
            ];
        }
        elseif ($field instanceof Fields\FloatField || $field instanceof Fields\IntegerField) {
            if (
                $field instanceof Fields\IntegerField
                && isset($options['type'])
                && $options['type'] == 'user'
            ) {
                $result = [
                    'additionalFilter' => [
                        'isEmpty',
                        'hasAnyValue',
                    ],
                ];
            } elseif (
                $field instanceof Fields\IntegerField
                && isset($options['type'])
                && $options['type'] == 'group') {
                if (!empty($options['items'])) {
                    $result = [
                        'items' => $options['items']
                    ];
                } else {
                    $result = [
                        'additionalFilter' => [
                            'isEmpty',
                            'hasAnyValue',
                        ],
                    ];
                }
            } else {
                $selectParams = ['isMulti' => false];
                $values = [
                    "_from" => "",
                    "_to" => ""
                ];
                $subTypes = [];

                $sourceSubtypes = \Bitrix\Main\UI\Filter\NumberType::getList();
                foreach ($sourceSubtypes as $subtype) {
                    $subTypes[] = ['name' => $subtype, 'value' => $subtype];
                }

                $subTypeType = ['name' => 'exact', 'value' => 'exact'];
                $result = [
                    'SUB_TYPE' => $subTypeType,
                    'SUB_TYPES' => $subTypes,
                    'VALUES' => $values,
                    'SELECT_PARAMS' => $selectParams,
                    'additionalFilter' => ['isEmpty', 'hasAnyValue'],
                ];
            }
        }

        $this->onPrepareFieldDataProcess($fieldID, $field, $result);
        return $result;
    }

    /**
     * Возвращает доп HTML
     * @param \Bitrix\Main\Filter\Field $field
     * @return string
     */
    public function prepareFieldHtml(Filter\Field $field): string
    {
        return parent::prepareFieldHtml($field);
    }

    /**
     * Модификация значения фильтра для getList
     *
     * @param array $rawFilterValue
     * @return array
     */
    public function prepareFilterValue(array $rawFilterValue): array
    {
        return parent::prepareFilterValue($rawFilterValue);
    }

    public function prepareField(Fields\Field $field): Filter\Field
    {
        $result = new Filter\Field($this, $field->getName(), [
            'name' => $field->getTitle() ?: $field->getColumnName(),
            //'title' => $field->getTitle() ?: $field->getColumnName(),
            'default' => ($field->isRequired() || $field->isPrimary()),
            'data' => [
                'name' => $field->getTitle() ?: $field->getColumnName(),
                'selected' => ($field->isRequired() || $field->isPrimary()),
            ]

        ]);

        if ($field instanceof Fields\StringField || $field instanceof Fields\Relations\Reference) {
            $result->setType(Type::TEXT);
        } elseif ($field instanceof Fields\BooleanField) {
            $result->setType(Type::CHECKBOX);
        } elseif ($field instanceof Fields\DateField) {
            $result->setType(Type::DATE);
        } elseif ($field instanceof Fields\EnumField) {
            $result->setType(Type::DROPDOWN);
        } elseif ($field instanceof Fields\FloatField || $field instanceof Fields\IntegerField) {
            $result->setType(Type::NUMBER);
        }

        return $result;
    }

    protected function onPrepareFieldsProcess(&$fields) {

        $this->notify(
            Events::EVENT_ON_PREPARE_FIELDS,
            [
                'dataProvider' => $this,
                'fields' => $fields
            ],
            function ($results) use (&$fields) {
                foreach ($results as $eventResult) {
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

    protected function onPrepareFieldDataProcess(string $fieldId, Field $field, array &$fieldData)
    {
        $this->notify(
            Events::EVENT_ON_PREPARE_FIELD_DATA,
            [
                'dataProvider' => $this,
                'fieldId' => $fieldId,
                'field' => $field,
                'fieldData' => $fieldData
            ],
            function ($results) use (&$fieldData) {
                foreach ($results as $eventResult) {
                    if ($eventResult->getType() === EventResult::SUCCESS) {
                        $params = $eventResult->getParameters();
                        if ($params['fieldData']) {
                            $fieldData = $params['fieldData'];
                        }
                    }
                }
            }
        );
    }
}
