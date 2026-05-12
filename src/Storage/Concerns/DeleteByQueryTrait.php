<?php

declare(strict_types=1);

namespace MB\Bitrix\Storage\Concerns;

use Bitrix\Main;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;
use MB\Bitrix\Storage\Query;
use MB\Support\Arr;

trait DeleteByQueryTrait
{
    /**
     * Safe batch delete using ORM primary lookup instead of regex SQL rewriting.
     *
     * @param array<string,mixed>|Query|ConditionTree $parameters
     */
    public static function deleteWhere(array|Query|ConditionTree $parameters): Main\Entity\DeleteResult
    {
        if ($parameters instanceof ConditionTree) {
            $parameters = static::query()->where($parameters);
        }

        $query = $parameters instanceof Query
            ? $parameters
            : static::createBatchQuery($parameters);
        /** @var Query $query */

        $entity = static::getEntity();
        $primaryFields = $entity->getPrimaryArray();
        if ($primaryFields === []) {
            throw new Main\ArgumentException('Entity has no primary fields.');
        }

        $selectQuery = clone $query;
        $selectQuery->setSelect($primaryFields);
        $rows = $selectQuery->fetchAll();

        if ($rows === []) {
            return new Main\Entity\DeleteResult();
        }

        // Preserve the fast path for a simple primary key list.
        if (count($primaryFields) === 1) {
            $primary = $primaryFields[0];
            $values = [];

            foreach ($rows as $row) {
                $value = self::extractRowValue($row, $primary);
                if ($value !== null) {
                    $values[] = $value;
                }
            }

            if ($values === []) {
                return new Main\Entity\DeleteResult();
            }

            return static::deleteWherePrimary($values);
        }

        $result = new Main\Entity\DeleteResult();
        $connection = $entity->getConnection();
        $affected = 0;

        $connection->startTransaction();
        try {
            foreach ($rows as $row) {
                $primary = self::buildCompositePrimary($row, $primaryFields);
                $deleteResult = static::delete($primary);
                if (!$deleteResult->isSuccess()) {
                    $result->addErrors($deleteResult->getErrors());
                    continue;
                }
                $affected++;
            }

            if ($result->getErrors() !== []) {
                $connection->rollbackTransaction();
                return $result;
            }

            $connection->commitTransaction();
            self::setAffectedRows($result, $affected);
        } catch (\Throwable $exception) {
            $connection->rollbackTransaction();
            throw $exception;
        }

        return $result;
    }

    /**
     * @param int|array<int,mixed>|array<string,mixed> $data
     */
    public static function deleteWherePrimary(int|array $data): Main\Entity\DeleteResult
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
                continue;
            }

            $values[] = $item;
        }

        if ($values === []) {
            return new Main\Entity\DeleteResult();
        }

        return static::deleteWhere(static::query()->whereIn($primaryField, $values));
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $primaryFields
     * @return array<string,mixed>
     */
    private static function buildCompositePrimary(array $row, array $primaryFields): array
    {
        $primary = [];

        foreach ($primaryFields as $field) {
            $value = self::extractRowValue($row, $field);
            if ($value === null) {
                throw new Main\SystemException(sprintf('Cannot resolve composite primary `%s` from query row.', $field));
            }
            $primary[$field] = $value;
        }

        return $primary;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function extractRowValue(array $row, string $field): mixed
    {
        if (array_key_exists($field, $row)) {
            return $row[$field];
        }

        $upper = mb_strtoupper($field);
        if (array_key_exists($upper, $row)) {
            return $row[$upper];
        }

        $lower = mb_strtolower($field);
        if (array_key_exists($lower, $row)) {
            return $row[$lower];
        }

        return null;
    }

    private static function setAffectedRows(Main\Entity\DeleteResult $result, int $affected): void
    {
        if (method_exists($result, 'setAffectedRowsCount')) {
            $result->setAffectedRowsCount($affected);
        }
    }
}
