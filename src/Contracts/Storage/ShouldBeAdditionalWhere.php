<?php

namespace MB\Bitrix\Contracts\Storage;

use Bitrix\Main\ORM\Query\Query;

interface ShouldBeAdditionalWhere
{
    /**
     * Where Batch condition with logic and
     *
     * @param Query $query
     * @param ...$args
     * @return void
     */
    public static function whereAll(Query $query, ...$args): void;

    /**
     * Where Batch condition with logic or
     *
     * @param Query $query
     * @param ...$args
     * @return void
     */
    public static function whereAny(Query $query, ...$args): void;

    /**
     * Where Batch condition with negative logic and
     *
     * @param Query $query
     * @param ...$args
     * @return void
     */
    public static function whereNone(Query $query, ...$args): void;
}