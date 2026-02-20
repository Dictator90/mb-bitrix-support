<?php

namespace MB\Bitrix\EntityView\Grid\Column;

use Bitrix\Main\Grid\Column;
use Bitrix\Main\Grid\Column\Type;
use Bitrix\Main\Grid\Settings;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Fields\Field;
use Bitrix\Main\ORM\Fields\Relations;
use Bitrix\Main\ORM\Fields\ScalarField;
use MB\Bitrix\EntityView\Helper;

class DataProvider extends Column\DataProvider
{
    protected Entity $entity;

    /**
     * @var Column\Column[]|array
     */
    public array $columns = [];

    public function __construct(Entity $entity, ?Settings $settings = null)
    {
        $this->entity = $entity;
        parent::__construct($settings);
    }

    public function getColumn(string $id): ?Column\Column
    {
        foreach ($this->columns as $k => $column) {
            if ($column->getId() === $id) {
                return $column;
            }
        }

        return null;
    }

    public function setColumns(array $columns)
    {
        $columns = array_values($columns);
        if (empty($columns)) {
            return $this;
        }

        $this->columns = [];

        foreach ($columns as $column) {
            if ($this->getColumn($column)) {
                $this->deleteColumn($column);
            }

            $this->addColumn($column);
        }

        return $this;
    }

    public function addColumn(Column\Column|string $column, $params = null): static
    {
        if (is_string($column) || $column instanceof \Stringable) {
            $column = explode('.', $column);
            $isRef = false;
            if (count($column) == 2) {
                $isRef = true;
            }

            if ($field = $this->entity->getField($column[0])) {
                $this->columns[] = $this->createColumnFromField($field, $column);
            } elseif (is_array($params) || !empty($params)) {
                $params['type'] = $params['type'] ?: Type::TEXT;
                $params['title'] = $params['title'] ?: $column[0];
                $params['name'] = $params['name'] ?: $params['title'];
                $this->columns[] = new Column\Column(($isRef ? "{$column[0]}_{$column[1]}" : $column), $params);
            }
        } else {
            $this->columns[] = $column;
        }

        return $this;
    }

    public function deleteColumn(string $id)
    {
        foreach ($this->columns as $k => $column) {
            if ($column->getId() === $id) {
                unset($this->columns[$k]);
                $this->columns = array_values($this->columns);
            }
        }

        return $this;
    }

    public function setEditableColumns(array $columns)
    {
        $this->prepareColumns();

        $fields = $this->entity->getFields();
        foreach ($fields as $field) {
            $this->getColumn($field->getName())?->setEditable(false);
        }

        foreach ($columns as $columnId) {
            $column = $this->getColumn($columnId);
            $column?->setEditable(true);
        }

        return $this;
    }

    public function setNonEditableColumns(array $columns)
    {
        $this->prepareColumns();

        foreach ($columns as $columnId) {
            $column = $this->getColumn($columnId);
            $column?->setEditable(false);
        }

        return $this;
    }

    protected function fillColums()
    {
        $this->columns = [];
        foreach ($this->entity->getFields() as $field) {
            $this->columns[] = $this->createColumnFromField($field);
        }

        return $this;
    }

    public function prepareColumns(): array
    {
        if (empty($this->columns)) {
            $this->fillColums();
        }

        return $this->columns;
    }

    protected function createColumnFromField(Field $field, $column = null): Column\Column
    {
        $isRef = $field instanceof Relations\Relation;
        $isExpression = $field instanceof ExpressionField;
        $fieldName = $isRef ? "{$column[0]}_{$column[1]}" : $field->getName();
        $editable = !($isExpression || $isRef);

        $column = new Column\Column($fieldName);
        $column
            ->setType(Helper::getColumnTypeByField($field))
            ->setName($field->getTitle())
            ->setSort($isRef ? $fieldName : $field->getName())
            ->setDefault($field instanceof ScalarField && ($field->isRequired() || $field->isPrimary()))
            ->setTitle($field->getTitle() ?? null)
            ->setEditable($editable)
        ;

        if (in_array($column->getType(), [Type::DROPDOWN, Type::MULTISELECT])) {
            if ($editable) {
                $items = $field->getValues();
                $items = array_combine($items, $items);
                if (method_exists($field->getEntity()->getDataClass(), 'getEnumTitle')) {
                    foreach ($items as $val => &$title) {
                        $title = $field->getEntity()->getDataClass()::getEnumTitle($val);
                    }
                }

                //todo: add Type to ListConfig

                $column->setEditable(new Column\Editable\ListConfig($fieldName, $items));
            }
        }

        if (method_exists($field, 'isPrimary') && $field->isPrimary()) {
            $column->setEditable(false);
        }

        return $column;
    }
}
