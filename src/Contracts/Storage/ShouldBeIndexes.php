<?php

namespace MB\Bitrix\Contracts\Storage;

use Bitrix\Main\DB\Connection;
use Bitrix\Main\Result;

interface ShouldBeIndexes
{
    /**
     * Return indexed schema for storage
     *
     * <code>
     * array(
     *     'index_name_1' => ['COLUMN_1', 'COLUMN_2'],
     *     'index_name_2' => 'COLUMN_3'
     * )
     * </code>
     *
     * @return array
     */
    public static function getIndexes(): array;

    /**
     * Create Indexes for storage
     *
     * @param Connection $connection
     * @return Result
     */
    public static function createIndexes(Connection $connection): Result;

    /**
     * Drop indexes for storage
     *
     * @param Connection $connection
     * @return Result
     */
    public static function dropIndexes(Connection $connection): Result;
}