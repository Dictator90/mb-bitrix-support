<?php

declare(strict_types=1);

namespace MB\Bitrix\Migration\Facades;

use Bitrix\Main\DB\Connection;
use Bitrix\Main\Error;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\Result;

/**
 * ORM storage table maintenance used by {@see \MB\Bitrix\Storage\Base::migrate()}.
 */
final class Storage
{
    public static function addNewFields(Connection $connection, Entity $entity): Result
    {
        $result = new Result();

        try {
            $tableName = $entity->getDBTableName();
            if (! $connection->isTableExists($tableName)) {
                $entity->createDbTable();
            } elseif (method_exists($entity, 'updateDbTable')) {
                $entity->updateDbTable();
            }
        } catch (\Throwable $e) {
            $result->addError(new Error($e->getMessage(), (int) $e->getCode()));
        }

        return $result;
    }

    /**
     * @param string[] $indexNames
     */
    public static function dropIndexes(Connection $connection, Entity $entity, array $indexNames): Result
    {
        $result = new Result();
        $table = $entity->getDBTableName();

        foreach ($indexNames as $name) {
            try {
                $connection->dropIndex($table, $name);
            } catch (\Throwable $e) {
                $result->addError(new Error($e->getMessage(), (int) $e->getCode()));
            }
        }

        return $result;
    }
}
