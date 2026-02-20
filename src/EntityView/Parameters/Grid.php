<?php
namespace MB\Bitrix\EntityView\Parameters;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ORM\Entity as ORMEntity;
use Bitrix\Main\ORM\Fields\Field;
use Bitrix\Main\ORM\Fields\Relations;
use Bitrix\Main\ORM\Fields\ScalarField;
use MB\Bitrix\EntityView\Helper;
use MB\Bitrix\EntityView\Parameters\Item\GridColumn;

class Grid extends Base
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
            'GRID_ID' => Helper::getGridIdByEntity($this->entity),
            'COLUMNS' => $this->getColumnsByEntity(),
            'AJAX_MODE' => 'Y',
            'AJAX_OPTION_JUMP' => 'N',
            'AJAX_OPTION_HISTORY' => 'N',
            'PAGE_SIZES' => [
                [
                    'NAME' => 20,
                    'VALUE' => 20,
                ],
                [
                    'NAME' => 40,
                    'VALUE' => 40,
                ],
                [
                    'NAME' => 40,
                    'VALUE' => 40,
                ],
            ]
        ];
    }


    public function setColumns(array $arColumnsId)
    {
        $columns = [];
        foreach ($arColumnsId as $column) {
            try {
                if (!$this->hasColumn($column)) {
                    $field = $this->entity->getField($column);
                    $columns[] = $field;
                }
            } catch (ArgumentException) {}
        }
        if (!empty($columns)) {
            $this->set('COLUMNS', $columns);
        }

        return $this;
    }

    /**
     * @return GridColumn[]
     */
    protected function getColumnsByEntity(): array
    {
        $result = [];
        foreach ($this->entity->getFields() as $field) {
            $result[] = $this->createColumnFromField($field);
        }

        return $result;
    }

    /**
     * @param Field $field
     * @return GridColumn
     */
    protected function createColumnFromField(Field $field): GridColumn
    {
        return new GridColumn([
            'id' => $field->getName(),
            'name' => $field->getTitle(),
            'default' => $field instanceof ScalarField && ($field->isRequired() || $field->isPrimary()),
            'sort' => $field instanceof Relations\Relation ? false : $field->getName()
        ]);
    }

    /**
     * @return Item\GridColumn[]
     */
    public function getColumnCollection(): array
    {
        return $this->get('COLUMNS', []);
    }

    /**
     * @param string $id
     * @return GridColumn|null
     */
    public function getColumnById(string $id): ?Item\GridColumn
    {
        foreach ($this->getColumnCollection() as $column) {
            if ($column->get('id') === $id) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param string $id
     * @return bool
     */
    protected function hasColumn(string $id)
    {
        foreach ($this->getColumnCollection() as $column) {
            if ($column->get('id') === $id) {
                return true;
            }
        }

        return false;
    }
}
