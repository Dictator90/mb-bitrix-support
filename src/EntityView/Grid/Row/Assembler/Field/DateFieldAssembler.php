<?php

namespace MB\Bitrix\EntityView\Grid\Row\Assembler\Field;

use Bitrix\Main\Diag\Helper;
use Bitrix\Main\Type;
use Bitrix\Main\Context;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Grid\Row\FieldAssembler;

class DateFieldAssembler extends FieldAssembler
{
    protected function prepareColumn($value)
    {
        return $value;
    }

    protected function prepareData($value)
    {
        if ($value instanceof Type\DateTime || $value instanceof Type\Date) {
            $value = $value->toString();
        }

        return $value;
    }

    protected function prepareRow(array $row): array
    {
        if (empty($this->getColumnIds())) {
            return $row;
        }

        $row['columns'] ??= [];

        foreach ($this->getColumnIds() as $columnId) {
            $row['columns'][$columnId] = $this->prepareColumn($row['data'][$columnId] ?? null);
            $row['data'][$columnId] = $this->prepareData($row['data'][$columnId] ?? null);
        }

        return $row;
    }
}
