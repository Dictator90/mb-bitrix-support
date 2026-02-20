<?php

namespace MB\Bitrix\EntityView\Grid\Row\Assembler;

use Bitrix\Main\Grid\Row\FieldAssembler;

class BaseFieldAssembler extends FieldAssembler
{
    protected string $type = 'text';

    /**
     * Вид значения ячейки поля
     * @param $value
     * @return mixed|string
     */
    protected function prepareColumn($value)
    {
        return htmlspecialcharsbx((string)$value);
    }

    protected function prepareData($value, $columnId)
    {
        return $value;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    protected function prepareRow(array $row): array
    {
        if (empty($this->getColumnIds())) {
            return $row;
        }

        $row['columns'] ??= [];

        foreach ($this->getColumnIds() as $columnId) {
            $row['columns'][$columnId] = $this->prepareColumn($row['data'][$columnId] ?? null);
            $row['data'][$columnId] = $this->prepareData($row['data'][$columnId] ?? null, $columnId);
        }

        return $row;
    }
}
