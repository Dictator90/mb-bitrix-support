<?php

declare(strict_types=1);

namespace MB\Bitrix\Storage\Concerns;

use Bitrix\Main;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity\UpdateResult;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;
use MB\Bitrix\Storage\Query;
use MB\Support\Arr;

trait UpdateByWhereTrait
{
    /**
     * Safe batch update using ORM primary lookup and per-row update in transaction.
     *
     * @param array<string,mixed>|Query|ConditionTree $parameters
     * @param array<string,mixed> $data
     */
    public static function updateWhere(array|Query|ConditionTree $parameters, array $data): UpdateResult
    {
        if ($parameters instanceof ConditionTree) {
            $parameters = static::query()->where($parameters);
        }

        $query = $parameters instanceof Query
            ? $parameters
            : static::createBatchQuery($parameters);
        /** @var Query $query */

        if (!Arr::isAssoc($data)) {
            throw new \InvalidArgumentException('data must be an associative array');
        }

        $entity = static::getEntity();
        $primaryFields = $entity->getPrimaryArray();
        if ($primaryFields === []) {
            throw new ArgumentException('Entity has no primary fields.');
        }

        $selectQuery = clone $query;
        $selectQuery->setSelect($primaryFields);
        $rows = $selectQuery->fetchAll();

        $result = new UpdateResult();
        if ($rows === []) {
            return $result;
        }

        $connection = $entity->getConnection();
        $affected = 0;

        $connection->startTransaction();
        try {
            foreach ($rows as $row) {
                $primary = self::resolvePrimaryFromRow($row, $primaryFields);
                $updateResult = static::update($primary, $data);
                if (!$updateResult->isSuccess()) {
                    $result->addErrors($updateResult->getErrors());
                    continue;
                }
                $affected++;
            }

            if ($result->getErrors() !== []) {
                $connection->rollbackTransaction();
                return $result;
            }

            $connection->commitTransaction();
            if (method_exists($result, 'setAffectedRowsCount')) {
                $result->setAffectedRowsCount($affected);
            }
        } catch (\Throwable $exception) {
            $connection->rollbackTransaction();
            throw $exception;
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $primaryFields
     * @return int|string|array<string,mixed>
     */
    private static function resolvePrimaryFromRow(array $row, array $primaryFields): int|string|array
    {
        if (count($primaryFields) === 1) {
            $field = $primaryFields[0];
            $value = self::extractRowValue($row, $field);
            if ($value === null) {
                throw new Main\SystemException(sprintf('Cannot resolve primary `%s` from query row.', $field));
            }

            return $value;
        }

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
}
