<?php

namespace MB\Bitrix\Storage\Concerns;

use Bitrix\Main;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;
use MB\Support\Arr;
use MB\Bitrix\Storage\Query;

trait DeleteByQueryTrait
{
    /**
     * Пакетное удаление записей по условию.
     *
     * @param array|Query|ConditionTree $parameters
     * @return Main\Entity\DeleteResult
     * @throws Main\SystemException
     */
    public static function deleteWhere(array|Query|ConditionTree $parameters)
    {
        $result = new Main\Entity\DeleteResult();

        if ($parameters instanceof ConditionTree) {
            $parameters = static::query()->where($parameters);
        }

        $query = $parameters instanceof Query
            ? $parameters
            : static::createBatchQuery($parameters);

        try {
            $selectSql = $query->getQuery();

            if (preg_match('/^SELECT\s.*?\s(FROM\s.*)$/si', $selectSql, $match)) {
                $entity = static::getEntity();
                $connection = $entity->getConnection();
                $helper = $connection->getSqlHelper();
                $sql = 'DELETE ' . $helper->quote($query->getInitAlias()) . ' ' . $match[1];
                $connection->queryExecute($sql);
            } else {
                throw new Main\SystemException('invalid deleteBatch query');
            }
        } catch (\Exception $exception) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Удаляет по первичным ключам.
     *
     * Для списка поддерживаются только простые (не составные) первичные ключи.
     *
     * @param int|array $data
     */
    public static function deleteWherePrimary(int|array $data)
    {
        $entity = static::getEntity();
        $primaryFields = $entity->getPrimaryArray();

        if (is_int($data) || Arr::isAssoc($data)) {
            return static::delete($data);
        }

        if ($primaryFields === []) {
            throw new Main\ArgumentException('Entity has no primary fields.');
        }

        if (count($primaryFields) !== 1) {
            throw new Main\ArgumentException('deleteWherePrimary supports only simple primary keys for lists.');
        }

        $primaryField = $primaryFields[0];
        $values = [];

        foreach ($data as $item) {
            if (is_array($item) && Arr::isAssoc($item)) {
                if (!array_key_exists($primaryField, $item)) {
                    throw new Main\ArgumentException(sprintf('Missing primary field `%s` in primary list item.', $primaryField));
                }
                $values[] = $item[$primaryField];
            } else {
                $values[] = $item;
            }
        }

        if ($values === []) {
            return new Main\Entity\DeleteResult();
        }

        return static::deleteWhere(static::query()->whereIn($primaryField, $values));
    }
}

