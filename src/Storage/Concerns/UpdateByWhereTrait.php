<?php

namespace MB\Bitrix\Storage\Concerns;

use Bitrix\Main;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;
use Bitrix\Main\Entity\UpdateResult;
use Bitrix\Main\ORM\Query\Expression\ExpressionField;
use MB\Support\Arr;
use MB\Bitrix\Storage\Query;

trait UpdateByWhereTrait
{
    /**
     * Пакетное обновление записей по условию.
     *
     * @param array|Query|ConditionTree $parameters
     * @param array $data
     * @return UpdateResult
     *
     * @throws Main\SystemException
     */
    public static function updateWhere(array|Query|ConditionTree $parameters, array $data): UpdateResult
    {
        $result = new UpdateResult();

        if ($parameters instanceof ConditionTree) {
            $parameters = static::query()->where($parameters);
        }

        $query = $parameters instanceof Query
            ? $parameters
            : static::createBatchQuery($parameters);

        if (!Arr::isAssoc($data)) {
            throw new \InvalidArgumentException('data must be an associative array');
        }

        try {
            $selectSql = $query->getQuery();
            if (preg_match(
                '/^SELECT\s.*?\sFROM(\s.*?)(\s(?:LEFT |RIGHT |INNER )?JOIN\s.*?)?(\sWHERE\s.*?)?$/si',
                $selectSql,
                $match
            )) {
                $entity = static::getEntity();
                $connection = $entity->getConnection();
                $helper = $connection->getSqlHelper();

                $tableName = $entity->getDBTableName();
                $tableAlias = $helper->quote($query->getInitAlias());
                $dataReplacedColumn = static::replaceFieldName($data);
                $update = $helper->prepareUpdate($tableName, $dataReplacedColumn);
                $update[0] = $tableAlias . '.' . str_replace(', ', ', ' . $tableAlias . '.', $update[0]);

                $sql = 'UPDATE ' . $match[1] . $match[2] . ' SET ' . $update[0] . $match[3];
                $connection->queryExecute($sql, $update[1]);
                $result->setAffectedRowsCount($connection);
            } else {
                throw new Main\SystemException('invalid updateBatch query');
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $result;
    }
}

