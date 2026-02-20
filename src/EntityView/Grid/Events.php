<?php

namespace MB\Bitrix\EntityView\Grid;

class Events
{
    public const ON_AFTER_PREPARE_GRID_ROWS = 'onAfterPrepareGridRows';

    /**
     * Событие перед установкой фильтра в ::getCount()
     * Устанавливает новый фильтр если EventResult::SUCCESS и присутствует параметр 'filter'
     *
     * @see Grid::buildPagination
     * @see Grid::beforeSetPaginationFilterProcess
     */
    public const ON_BEFORE_SET_PAGINATION_FILTER = 'onBeforeGridSetPaginationFilter';
}
