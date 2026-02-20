<?php

namespace MB\Bitrix\EntityView\Grid\Row\Assembler;

use Bitrix\Main\EventResult;
use Bitrix\Main\Grid\Row\FieldAssembler;
use Bitrix\Main\Grid\Row\RowAssembler;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\ORM\Fields\DateField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use MB\Bitrix\EntityView\Grid;
use MB\Bitrix\EntityView\Helper;
use MB\Bitrix\Traits\BitrixEventsObservableTrait;


class BaseRowAssembler extends RowAssembler
{
    use BitrixEventsObservableTrait;

    /** @var string Delimiter for composite primary in row id (must match Helper::PRIMARY_DELIMITER). */
    const PRIMARY_DELIMITER = '|';

    protected Entity $entity;
    protected array $customRowsAssemblers = [];

    public function __construct(array $visibleColumnIds, Entity $entity)
    {
        $this->entity = $entity;

        $this->attach('mb.core', Grid\Events::ON_AFTER_PREPARE_GRID_ROWS);

        parent::__construct($visibleColumnIds);
    }

    protected function getDateColumnIds()
    {
        $result = [];

        foreach ($this->entity->getFields() as $field) {
            if ($field instanceof DatetimeField || $field instanceof DateField) {
                $result[] = $field->getName();
            }
        }

        return $result;
    }

    protected function prepareFieldAssemblers(): array
    {
        return [
            new Field\DateFieldAssembler($this->getDateColumnIds()),
            ...$this->customRowsAssemblers
        ];
    }

    /**
     * Подготовка строки + функционал для множественных primary
     * @param array $rowsList
     * @return array|array[]
     */
    public function prepareRows(array $rowsList): array
    {
        $rowsList = parent::prepareRows($rowsList);

        foreach ($rowsList as $i => $row) {
            $primaryValues = Helper::getPrimaryValues($this->entity, $row['data']);
            if (!isset($row['id'])) {
                $rowsList[$i]['id'] = implode(Helper::PRIMARY_DELIMITER, array_values($primaryValues));
            }
        }

        $this->sendEventAfterPrepareGridRows($rowsList);

        return $rowsList;
    }

    public function setCustomRowAssemblers(array $assemblers)
    {
        foreach ($assemblers as $assembler) {
            if (is_object($assembler) && is_subclass_of($assembler, FieldAssembler::class)) {
                $this->customRowsAssemblers[] = $assembler;
            }
        }

        return $this;
    }

    /**
     * Событие после всех приготовлений строк
     *
     * @moduleid mb.core
     * @event Grid\Events::ON_AFTER_PREPARE_GRID_ROWS
     *
     * @param $rows
     * @return void
     */
    protected function sendEventAfterPrepareGridRows(&$rows)
    {
        $this->notify(
            Grid\Events::ON_AFTER_PREPARE_GRID_ROWS,
            [
                'entity' => $this->entity,
                'rows' => $rows
            ],
            function ($results) use (&$rows) {
                if (!empty($results) && is_array($results)) {
                    foreach ($results as $eventResult) {
                        if ($eventResult->getType() === EventResult::SUCCESS) {
                            $eventRows = $eventResult->getParameters()['rows'] ?? [];
                            if ($eventRows) {
                                $rows = $eventRows;
                            }
                        }
                    }
                }
            }
        );
    }
}
