<?php

declare(strict_types=1);

namespace MB\Bitrix\Storage\Concerns;

use Bitrix\Main;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Entity\AddResult;
use MB\Bitrix\Storage\SqlHelper;

trait BatchUpsertTrait
{
    /**
     * Cross-platform batch add/upsert with deterministic field normalization.
     *
     * @param array<int, array<string,mixed>> $dataList
     * @param bool|array<int,string> $updateOnDuplicate
     * @throws SqlQueryException|Main\SystemException|ArgumentException
     */
    public static function addBatch(array $dataList, bool|array $updateOnDuplicate = false): AddResult
    {
        $result = new AddResult();

        if ($dataList === []) {
            return $result;
        }

        $entity = static::getEntity();
        $fields = $entity->getFields();
        $connection = $entity->getConnection();
        $helper = $connection->getSqlHelper();
        $tableName = $entity->getDBTableName();

        $usedFields = self::collectAndValidateFields($dataList, $fields, $entity->getName());
        if ($usedFields === []) {
            return $result;
        }

        [$sqlFieldPart, $sqlValuePart] = self::buildInsertParts(
            $dataList,
            $usedFields,
            $fields,
            $helper,
            $tableName
        );

        $primaryArray = $entity->getPrimaryArray();
        $duplicateFields = self::resolveDuplicateFields(
            $updateOnDuplicate,
            $usedFields,
            $primaryArray,
            $connection,
            $tableName
        );
        $doUpdate = $updateOnDuplicate !== false;

        $sql = SqlHelper::buildCrossPlatformUpsertSql(
            $connection,
            $tableName,
            $sqlFieldPart,
            $sqlValuePart,
            $primaryArray,
            $duplicateFields,
            $doUpdate
        );

        $connection->queryExecute($sql);

        if (count($dataList) === 1 && !$doUpdate && method_exists($connection, 'getInsertedId') && method_exists($result, 'setId')) {
            $insertedId = $connection->getInsertedId();
            if (is_int($insertedId) && $insertedId > 0) {
                $result->setId($insertedId);
            }
        }

        return $result;
    }

    /**
     * @param array<int, array<string,mixed>> $dataList
     * @param array<string,mixed> $fields
     * @return list<string>
     */
    private static function collectAndValidateFields(array $dataList, array $fields, string $entityName): array
    {
        $usedFields = [];

        foreach ($dataList as $rowIndex => $row) {
            foreach ($row as $fieldName => $_value) {
                if (!isset($fields[$fieldName])) {
                    throw new ArgumentException(
                        sprintf('%s Entity has no `%s` field (row %d).', $entityName, $fieldName, $rowIndex),
                        $fieldName
                    );
                }

                if (!in_array($fieldName, $usedFields, true)) {
                    $usedFields[] = $fieldName;
                }
            }
        }

        return $usedFields;
    }

    /**
     * @param array<int, array<string,mixed>> $dataList
     * @param list<string> $usedFields
     * @param array<string,mixed> $fields
     * @return array{0:string,1:string}
     */
    private static function buildInsertParts(
        array $dataList,
        array $usedFields,
        array $fields,
        object $helper,
        string $tableName
    ): array {
        $sqlFieldPart = '';
        $sqlValueParts = [];

        foreach ($dataList as $row) {
            $normalizedRow = [];

            foreach ($usedFields as $fieldName) {
                $field = $fields[$fieldName];
                $value = $row[$fieldName] ?? null;
                $normalizedRow[$fieldName] = $field->modifyValueBeforeSave($value, $row);
            }

            $insert = $helper->prepareInsert($tableName, $normalizedRow);
            if ($sqlFieldPart === '') {
                $sqlFieldPart = $insert[0];
            }
            $sqlValueParts[] = '(' . $insert[1] . ')';
        }

        return [$sqlFieldPart, implode(',' . PHP_EOL, $sqlValueParts)];
    }

    /**
     * @param bool|array<int,string> $updateOnDuplicate
     * @param list<string> $usedFields
     * @param list<string> $primaryArray
     * @return list<string>
     */
    private static function resolveDuplicateFields(
        bool|array $updateOnDuplicate,
        array $usedFields,
        array $primaryArray,
        object $connection,
        string $tableName
    ): array {
        if ($updateOnDuplicate === false) {
            return [];
        }

        if (is_array($updateOnDuplicate)) {
            return array_values(array_unique($updateOnDuplicate));
        }

        $tableFields = $connection->getTableFields($tableName);
        $primaryMap = array_flip($primaryArray);
        $duplicateFields = [];

        foreach ($usedFields as $fieldName) {
            if (!isset($primaryMap[$fieldName]) && isset($tableFields[$fieldName])) {
                $duplicateFields[] = $fieldName;
            }
        }

        return $duplicateFields;
    }
}

