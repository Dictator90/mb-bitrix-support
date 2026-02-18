<?php
namespace MB\Bitrix\Storage\Concerns;

use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\SqlQueryException;
use MB\Bitrix\Migration\Facades\Storage as StorageFacade;

trait BuildIndexes
{
    public static function getIndexes(): array
    {
        return [];
    }

    /**
     * Создает индексы колонок указанных в методе getIndexes()
     *
     * Используется в MB\Core\Reference\Storage\Controller::createTable()
     *
     * @see self::getIndexes()
     *
     * @param Connection $connection
     * @return array
     * @throws SqlQueryException
     */
    public static function createIndexes(Connection $connection)
    {
        $result = [];
        foreach (self::getIndexes() as $index => $columns) {
            $result[$index] = $connection->createIndex(self::getTableName(), $index, $columns);
        }

        return $result;
    }

    public static function dropIndexes(Connection $connection)
    {
        return StorageFacade::dropIndexes($connection, self::getEntity(), array_keys(self::getIndexes()));
    }
}