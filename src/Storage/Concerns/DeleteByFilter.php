<?php

namespace MB\Bitrix\Storage\Concerns;

use Bitrix\Main;
use Bitrix\Main\ORM\Fields\Relations\Relation;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;

trait DeleteByFilter
{
    abstract public static function getTableName();
    abstract public static function getEntity();

    /**
     * @param array|ConditionTree $filter
     * @param array<string, array>|array<array-key, Relation> $runtime
     * @return Main\Entity\DeleteResult
     */
    public static function deleteWhere(array|ConditionTree $filter, array $runtime = [])
    {
        $result = new Main\Entity\DeleteResult();

        $query = static::query();

        if ($filter instanceof ConditionTree) {
            $query->where($filter);
        } else {
            $query->setFilter($filter);
        }

        if ($runtime) {
            foreach ($runtime as $name => $data) {
                if (is_array($data)) {
                    $query->registerRuntime($name, $data);
                } else {
                    $query->registerRuntime($data);
                }
            }
        }

        try {
            $selectSql = $query->getQuery();
            if (preg_match('/^SELECT\s.*?\s(FROM\s.*)$/si', $selectSql, $match)) {
                $entity = static::getEntity();
                $connection = $entity->getConnection();
                $helper = $connection->getSqlHelper();
                $sql = 'DELETE ' . $helper->quote($query->getInitAlias()) . ' ' . $match[1];

                static::onBeforeDeleteByFilter($sql, $connection);

                $connection->queryExecute($sql);
            } else {
                throw new Main\SystemException('Invalid deleteWhere query');
            }
        } catch (\Exception $exception) {
            $result->addError(new Main\Error($exception->getMessage(), $exception->getCode()));
        }

        return $result;
    }

    protected static function onBeforeDeleteByFilter(string $sql, Main\DB\Connection $connection)
    {
        //
    }
}