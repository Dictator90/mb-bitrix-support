<?php

namespace MB\Bitrix\EntityView\Grid\Column;

use Bitrix\Main\Grid\Column\Columns as ColumnsBase;
use Bitrix\Main\Grid\Column\Type;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;

class Columns extends ColumnsBase
{
    public function prepareEditableColumnsValues(array $values): array
    {
        $result = [];

        foreach ($this->getColumns() as $column) {
            if (!$column->isEditable()) {
                continue;
            }

            $id = $column->getId();
            if (array_key_exists($id, $values)) {
                switch ($column->getType()) {
                    case Type::CALENDAR || Type::DATE:
                        if ($values[$id] instanceof Date || $values[$id] instanceof DateTime) {
                            $values[$id] = $values[$id]->toString();
                        }
                        break;
                }

                $result[$id] = $values[$id];
            }
        }


        return $result;
    }
}
