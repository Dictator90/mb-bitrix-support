<?php

namespace MB\Bitrix\Storage;

use Bitrix\Main\DB\Connection;

/**
 * Вспомогательный класс для построения SQL-запросов,
 * используемых batch‑операциями в {@see Base}.
 *
 * Поддерживаемые СУБД для массовых операций: MySQL и PostgreSQL.
 *
 * Не предназначен для прямого использования прикладным кодом.
 */
final class SqlHelper
{
    /**
     * Построение SQL для кросс-платформенного batch‑обновления.
     *
     * @param Connection $connection соединение сущности (тип и SqlHelper должны совпадать с тем, где выполняется запрос)
     * @param string $tableName
     * @param string[] $primaryFields
     * @param string[] $allFields
     * @param string[] $values строки вида "(val1, val2, ...)"
     * @return string
     */
    public static function buildCrossPlatformUpdateSql(
        Connection $connection,
        string $tableName,
        array $primaryFields,
        array $allFields,
        array $values
    ): string {
        return match ($connection->getType()) {
            'pgsql' => self::buildPostgresUpdateSql($connection, $tableName, $primaryFields, $allFields, $values),
            default => self::buildMysqlUpdateSql($connection, $tableName, $primaryFields, $allFields, $values),
        };
    }

    /**
     * Cross-platform SQL builder for batch INSERT / UPSERT.
     *
     * Expected inputs are already pre-built SQL fragments for fields and values
     * (typically from {@see \Bitrix\Main\DB\SqlHelper::prepareInsert()}):
     * - $sqlFieldPart: quoted comma-separated field list
     * - $sqlValuePart: comma-separated "(...)" rows
     *
     * @param Connection $connection Bitrix DB connection
     * @param string $tableName
     * @param string $sqlFieldPart quoted field list, without surrounding parentheses
     * @param string $sqlValuePart values list, e.g. "(1,2),(3,4)" including parentheses
     * @param string[] $primaryFields conflict target columns (primary key columns)
     * @param string[] $duplicateFields columns to update on conflict; empty means DO NOTHING / INSERT IGNORE
     * @param bool $doUpdate whether upsert/update branch should be used
     */
    public static function buildCrossPlatformUpsertSql(
        Connection $connection,
        string $tableName,
        string $sqlFieldPart,
        string $sqlValuePart,
        array $primaryFields,
        array $duplicateFields,
        bool $doUpdate
    ): string {
        return match ($connection->getType()) {
            'pgsql' => self::buildPostgresUpsertSql($connection, $tableName, $sqlFieldPart, $sqlValuePart, $primaryFields, $duplicateFields, $doUpdate),
            default => self::buildMysqlUpsertSql($connection, $tableName, $sqlFieldPart, $sqlValuePart, $primaryFields, $duplicateFields, $doUpdate),
        };
    }

    /**
     * PostgreSQL UPSERT builder.
     */
    public static function buildPostgresUpsertSql(
        Connection $connection,
        string $tableName,
        string $sqlFieldPart,
        string $sqlValuePart,
        array $primaryFields,
        array $duplicateFields,
        bool $doUpdate
    ): string {
        $helper = $connection->getSqlHelper();
        $tableNameQuoted = $helper->quote($tableName);

        $insertSql = "INSERT INTO {$tableNameQuoted}({$sqlFieldPart}) VALUES {$sqlValuePart}";

        if (! $doUpdate) {
            return $insertSql;
        }

        if ($primaryFields === []) {
            throw new \InvalidArgumentException('Postgres upsert requires conflict target (primary fields).');
        }

        $conflictCols = implode(', ', array_map([$helper, 'quote'], $primaryFields));

        if ($duplicateFields === []) {
            return $insertSql . " ON CONFLICT ({$conflictCols}) DO NOTHING";
        }

        $setParts = [];
        foreach ($duplicateFields as $field) {
            $quotedField = $helper->quote($field);
            $setParts[] = "{$quotedField} = EXCLUDED.{$quotedField}";
        }

        return $insertSql
            . " ON CONFLICT ({$conflictCols}) DO UPDATE SET "
            . implode(', ', $setParts);
    }

    /**
     * MySQL UPSERT builder.
     */
    public static function buildMysqlUpsertSql(
        Connection $connection,
        string $tableName,
        string $sqlFieldPart,
        string $sqlValuePart,
        array $primaryFields,
        array $duplicateFields,
        bool $doUpdate
    ): string {
        $helper = $connection->getSqlHelper();
        $tableNameQuoted = $helper->quote($tableName);

        $insertSql = "INSERT INTO {$tableNameQuoted}({$sqlFieldPart}) VALUES {$sqlValuePart}";

        if (! $doUpdate) {
            return $insertSql;
        }

        if ($duplicateFields === []) {
            return "INSERT IGNORE INTO {$tableNameQuoted}({$sqlFieldPart}) VALUES {$sqlValuePart}";
        }

        $duplicateSqlParts = [];
        foreach ($duplicateFields as $fieldName) {
            $fieldNameQuoted = $helper->quote($fieldName);
            $duplicateSqlParts[] = "{$fieldNameQuoted} = VALUES({$fieldNameQuoted})";
        }

        return $insertSql . " ON DUPLICATE KEY UPDATE " . implode(', ', $duplicateSqlParts);
    }

    /**
     * PostgreSQL‑совместимый SQL для batch‑обновления.
     *
     * @param string   $tableName
     * @param string[] $primaryFields
     * @param string[] $allFields
     * @param string[] $values
     */
    public static function buildPostgresUpdateSql(
        Connection $connection,
        string $tableName,
        array $primaryFields,
        array $allFields,
        array $values
    ): string {
        $helper = $connection->getSqlHelper();
        $tableQuoted = $helper->quote($tableName);
        $quotedAllFields = array_map([$helper, 'quote'], $allFields);
        $fieldsStr = implode(', ', $quotedAllFields);
        $valuesStr = implode(', ', $values);

        $setParts = [];
        foreach ($allFields as $field) {
            if (in_array($field, $primaryFields, true)) {
                continue;
            }

            $quotedField = $helper->quote($field);
            $setParts[] = "{$quotedField} = COALESCE(updates.{$quotedField}, {$tableQuoted}.{$quotedField})";
        }
        $setStr = implode(', ', $setParts);

        $whereConditions = [];
        foreach ($primaryFields as $field) {
            $quotedField = $helper->quote($field);
            $whereConditions[] = "{$tableQuoted}.{$quotedField} = updates.{$quotedField}";
        }
        $whereConditionStr = implode(' AND ', $whereConditions);

        return "
            UPDATE {$tableQuoted}
            SET {$setStr}
            FROM (VALUES {$valuesStr}) AS updates({$fieldsStr})
            WHERE {$whereConditionStr}
        ";
    }

    /**
     * MySQL‑совместимый SQL для batch‑обновления.
     *
     * @param string   $tableName
     * @param string[] $primaryFields
     * @param string[] $allFields
     * @param string[] $values
     */
    public static function buildMysqlUpdateSql(
        Connection $connection,
        string $tableName,
        array $primaryFields,
        array $allFields,
        array $values
    ): string {
        $helper = $connection->getSqlHelper();
        $quotedAllFields = array_map([$helper, 'quote'], $allFields);
        $fieldsStr = implode(', ', $quotedAllFields);

        $valuesRows = [];
        foreach ($values as $valueRow) {
            $valuesRows[] = "ROW{$valueRow}";
        }
        $valuesStr = implode(', ', $valuesRows);

        $setParts = [];
        foreach ($allFields as $field) {
            if (in_array($field, $primaryFields, true)) {
                continue;
            }

            $quotedField = $helper->quote($field);
            $setParts[] = "t.{$quotedField} = COALESCE(updates.{$quotedField}, t.{$quotedField})";
        }
        $setStr = implode(', ', $setParts);

        $joinConditions = [];
        foreach ($primaryFields as $field) {
            $quotedField = $helper->quote($field);
            $joinConditions[] = "t.{$quotedField} = updates.{$quotedField}";
        }
        $joinConditionStr = implode(' AND ', $joinConditions);

        return "
            UPDATE {$helper->quote($tableName)} AS t
            INNER JOIN (
                VALUES {$valuesStr}
            ) AS updates({$fieldsStr})
                ON {$joinConditionStr}
            SET {$setStr}
        ";
    }
}

