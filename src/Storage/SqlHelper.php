<?php

namespace MB\Bitrix\Storage;

use Bitrix\Main;

/**
 * Вспомогательный класс для построения SQL-запросов,
 * используемых batch‑операциями в {@see Base}.
 *
 * Не предназначен для прямого использования прикладным кодом.
 */
final class SqlHelper
{
    /**
     * Построение SQL для кросс-платформенного batch‑обновления.
     *
     * @param string $tableName
     * @param string[] $primaryFields
     * @param string[] $allFields
     * @param string[] $values строки вида "(val1, val2, ...)"
     * @return string
     */
    public static function buildCrossPlatformUpdateSql(
        string $tableName,
        array $primaryFields,
        array $allFields,
        array $values
    ): string {
        return match (Main\Application::getConnection()->getType()) {
            'pgsql' => self::buildPostgresUpdateSql($tableName, $primaryFields, $allFields, $values),
            default => self::buildMysqlUpdateSql($tableName, $primaryFields, $allFields, $values),
        };
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
        string $tableName,
        array $primaryFields,
        array $allFields,
        array $values
    ): string {
        $helper = Main\Application::getConnection()->getSqlHelper();
        $quotedAllFields = array_map([$helper, 'quote'], $allFields);
        $fieldsStr = implode(', ', $quotedAllFields);
        $valuesStr = implode(', ', $values);

        $setParts = [];
        foreach ($allFields as $field) {
            if (in_array($field, $primaryFields, true)) {
                continue;
            }

            $quotedField = $helper->quote($field);
            $setParts[] = "{$quotedField} = COALESCE(updates.{$quotedField}, {$tableName}.{$quotedField})";
        }
        $setStr = implode(', ', $setParts);

        $whereConditions = [];
        foreach ($primaryFields as $field) {
            $quotedField = $helper->quote($field);
            $whereConditions[] = "{$tableName}.{$quotedField} = updates.{$quotedField}";
        }
        $whereConditionStr = implode(' AND ', $whereConditions);

        return "
            UPDATE {$helper->quote($tableName)}
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
        string $tableName,
        array $primaryFields,
        array $allFields,
        array $values
    ): string {
        $helper = Main\Application::getConnection()->getSqlHelper();
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

