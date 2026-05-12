<?php

declare(strict_types=1);

namespace MB\Bitrix\Database;

use Bitrix\Main;
use Bitrix\Main\ORM;
use Bitrix\Main\ORM\Fields\ScalarField;
use Bitrix\Main\Type;
use Bitrix\Main\DB\SqlHelper;

/**
 * SQL-хелпер для {@see SqlLiteConnection} (по мотивам {@see \Bitrix\Main\DB\PgsqlSqlHelper} / {@see \Bitrix\Main\DB\MysqliSqlHelper}).
 *
 * @property SqlLiteConnection $connection
 */
class SqlLiteSqlHelper extends SqlHelper
{
    public const FULLTEXT_MAXIMUM_LENGTH = 1000000;

    public function getLeftQuote(): string
    {
        return '"';
    }

    public function getRightQuote(): string
    {
        return '"';
    }

    public function getAliasLength(): int
    {
        return 63;
    }

    public function values($identifier): string
    {
        return 'excluded.' . $this->quote($identifier);
    }

    public function getQueryDelimiter(): string
    {
        return ';';
    }

    public function forSql($value, $maxLength = 0): string
    {
        if ($maxLength > 0) {
            $value = mb_substr($value, 0, $maxLength);
        }

        $pdo = $this->connection->getResource();
        $quoted = $pdo->quote($value);
        if ($quoted === false) {
            return str_replace("'", "''", $value);
        }

        $quoted = (string) $quoted;

        return mb_substr($quoted, 1, mb_strlen($quoted) - 2);
    }

    public function convertToDbBinary($value): string
    {
        return "x'" . bin2hex((string) $value) . "'";
    }

    public function convertToFullText($value, $maxLength = 0): string
    {
        $fulltextLength = $maxLength ? min($maxLength, static::FULLTEXT_MAXIMUM_LENGTH) : static::FULLTEXT_MAXIMUM_LENGTH;

        return "'" . $this->forSql($value, $fulltextLength) . "'";
    }

    public function getCurrentDateTimeFunction(): string
    {
        return "datetime('now')";
    }

    public function getCurrentDateFunction(): string
    {
        return "date('now')";
    }

    public function addSecondsToDateTime($seconds, $from = null): string
    {
        if ($from === null) {
            $from = static::getCurrentDateTimeFunction();
        }

        return "datetime(" . $from . ", '+" . (int) $seconds . " seconds')";
    }

    public function addDaysToDateTime($days, $from = null): string
    {
        if ($from === null) {
            $from = static::getCurrentDateTimeFunction();
        }

        return "datetime(" . $from . ", '+" . (int) $days . " days')";
    }

    public function getDatetimeToDateFunction($value): string
    {
        return 'date(' . $value . ')';
    }

    public function formatDate($format, $field = null): string
    {
        static $translation = [
            'YYYY' => '%Y',
            'MMMM' => '%B',
            'MI' => '%M',
            'HH' => '%H',
            'GG' => '%I',
            'TT' => '',
            'M' => '%b',
            'H' => '%H',
            'G' => '%I',
            'T' => '',
            'W' => '%w',
        ];

        $dbFormat = '';
        foreach (preg_split('/(YYYY|MMMM|MM|MI|DD|HH|GG|SS|TT|M|H|G|T|W)/', $format, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
            $dbFormat .= $translation[$part] ?? $part;
        }

        if ($field === null) {
            return $dbFormat;
        }

        return "strftime('" . str_replace("'", "''", $dbFormat) . "', " . $field . ")";
    }

    public function getRegexpOperator($field, $regexp): string
    {
        return $field . ' REGEXP ' . $regexp;
    }

    public function getIlikeOperator($field, $value): string
    {
        return $field . ' LIKE ' . $value . " ESCAPE '\\'";
    }

    public function getConcatFunction(): string
    {
        return implode(' || ', func_get_args());
    }

    public function getRandomFunction(): string
    {
        return '(abs(random()) % 1000000000)';
    }

    public function getSha1Function($field): string
    {
        return 'sha1(' . $field . ')';
    }

    public function getIsNullFunction($expression, $result): string
    {
        return 'COALESCE(' . $expression . ', ' . $result . ')';
    }

    public function getLengthFunction($field): string
    {
        return 'LENGTH(' . $field . ')';
    }

    public function getMatchFunction($field, $value): string
    {
        return '(' . $field . ' LIKE ' . $value . ')';
    }

    public function getMatchAndExpression($values, $prefixSearch = false): string
    {
        if ($prefixSearch) {
            foreach ($values as $i => $searchTerm) {
                $values[$i] = $searchTerm . '%';
            }
        }

        return implode(' AND ', $values);
    }

    public function getMatchOrExpression($values, $prefixSearch = false): string
    {
        if ($prefixSearch) {
            foreach ($values as $i => $searchTerm) {
                $values[$i] = $searchTerm . '%';
            }
        }

        return implode(' OR ', $values);
    }

    public function getCharToDateFunction($value): string
    {
        return "datetime('" . str_replace("'", "''", $value) . "')";
    }

    public function getDateToCharFunction($fieldName): string
    {
        return "strftime('%Y-%m-%d %H:%M:%S', " . $fieldName . ')';
    }

    public function getConverter(ScalarField $field)
    {
        if ($field instanceof ORM\Fields\DatetimeField) {
            return [$this, 'convertFromDbDateTime'];
        }
        if ($field instanceof ORM\Fields\DateField) {
            return [$this, 'convertFromDbDate'];
        }

        return parent::getConverter($field);
    }

    public function convertFromDbDateTime($value)
    {
        if ($value !== null && $value != '0000-00-00 00:00:00') {
            return new Type\DateTime($value, 'Y-m-d H:i:s');
        }

        return null;
    }

    public function convertFromDbDate($value)
    {
        if ($value !== null && $value != '0000-00-00') {
            return new Type\Date($value, 'Y-m-d');
        }

        return null;
    }

    public function castToChar($fieldName): string
    {
        return 'CAST(' . $fieldName . ' AS TEXT)';
    }

    public function softCastTextToChar($fieldName): string
    {
        return $fieldName;
    }

    public function getColumnTypeByField(ScalarField $field): string
    {
        if ($field instanceof ORM\Fields\IntegerField) {
            return 'INTEGER';
        }
        if ($field instanceof ORM\Fields\DecimalField) {
            $defaultPrecision = 18;
            $defaultScale = 2;

            $precision = $field->getPrecision() > 0 ? $field->getPrecision() : $defaultPrecision;
            $scale = $field->getScale() > 0 ? $field->getScale() : $defaultScale;

            if ($scale >= $precision) {
                $precision = $defaultPrecision;
                $scale = $defaultScale;
            }

            return "NUMERIC($precision, $scale)";
        }
        if ($field instanceof ORM\Fields\FloatField) {
            return 'REAL';
        }
        if ($field instanceof ORM\Fields\DatetimeField) {
            return 'TEXT';
        }
        if ($field instanceof ORM\Fields\DateField) {
            return 'TEXT';
        }
        if ($field instanceof ORM\Fields\TextField) {
            return 'TEXT';
        }
        if ($field instanceof ORM\Fields\BooleanField) {
            $values = $field->getValues();

            if (preg_match('/^[0-9]+$/', $values[0]) && preg_match('/^[0-9]+$/', $values[1])) {
                return 'INTEGER';
            }

            $falseLen = mb_strlen($values[0]);
            $trueLen = mb_strlen($values[1]);
            if ($falseLen === 1 && $trueLen === 1) {
                return 'TEXT';
            }

            return 'TEXT';
        }
        if ($field instanceof ORM\Fields\EnumField) {
            return 'TEXT';
        }

        $defaultLength = false;
        foreach ($field->getValidators() as $validator) {
            if ($validator instanceof ORM\Fields\Validators\LengthValidator) {
                if ($defaultLength === false || $defaultLength > $validator->getMax()) {
                    $defaultLength = $validator->getMax();
                }
            }
        }

        return 'TEXT';
    }

    public function getFieldByColumnType($name, $type, ?array $parameters = null): ScalarField
    {
        $t = mb_strtolower((string) $type);

        switch ($t) {
            case 'bigint':
            case 'int64':
            case 'integer':
            case 'int':
            case 'smallint':
            case 'tinyint':
                $field = (new ORM\Fields\IntegerField($name))->configureSize(8);
                break;
            case 'real':
            case 'float':
            case 'double':
            case 'numeric':
                $field = new ORM\Fields\FloatField($name);
                break;
            case 'blob':
                $field = new ORM\Fields\StringField($name, ['binary' => true]);
                break;
            case 'timestamp':
            case 'datetime':
                $field = new ORM\Fields\DatetimeField($name);
                break;
            case 'date':
                $field = new ORM\Fields\DateField($name);
                break;
            default:
                $field = new ORM\Fields\StringField($name);
        }

        $field->setConnection($this->connection);

        return $field;
    }

    public function getTopSql($sql, $limit, $offset = 0): string
    {
        $offset = (int) $offset;
        $limit = (int) $limit;

        if ($offset > 0 && $limit <= 0) {
            throw new Main\ArgumentException('Limit must be set if offset is set');
        }

        if ($limit > 0) {
            $sql .= "\nLIMIT " . $limit;
        }

        if ($offset > 0) {
            $sql .= ' OFFSET ' . $offset;
        }

        $sql .= "\n";

        return $sql;
    }

    public function getInsertIgnore($tableName, $fields, $sql): string
    {
        return 'INSERT OR IGNORE INTO ' . $tableName . $fields . $sql;
    }

    public function getAscendingOrder(): string
    {
        return 'ASC';
    }

    public function getDescendingOrder(): string
    {
        return 'DESC';
    }

    public function prepareMerge($tableName, array $primaryFields, array $insertFields, array $updateFields): array
    {
        $insert = $this->prepareInsert($tableName, $insertFields);
        $update = $this->prepareUpdate($tableName, $updateFields);

        if (
            !empty($insert[0]) && !empty($insert[1])
            && !empty($update[0])
            && $primaryFields
        ) {
            $sql = 'INSERT INTO ' . $this->quote($tableName) . ' (' . $insert[0] . ')
				VALUES (' . $insert[1] . ')
				ON CONFLICT (' . implode(',', array_map([$this, 'quote'], $primaryFields)) . ')
				DO UPDATE SET ' . $update[0];
        } else {
            $sql = '';
        }

        return [$sql];
    }

    public function prepareMergeMultiple($tableName, array $primaryFields, array $insertRows): array
    {
        $result = [];
        $head = '';
        $tail = '';
        $maxBodySize = 1024 * 1024;
        $body = [];
        $bodySize = 0;
        foreach ($insertRows as $insertFields) {
            $insert = $this->prepareInsert($tableName, $insertFields, true);
            if (!$head && $insert && $insert[0]) {
                $head = 'INSERT INTO ' . $this->quote($tableName) . ' (' . implode(', ', $insert[0]) . ') VALUES ';
                $setParts = [];
                foreach ($insert[0] as $qc) {
                    $setParts[] = $qc . ' = excluded.' . $qc;
                }
                $tail = ' ON CONFLICT (' . implode(',', array_map([$this, 'quote'], $primaryFields)) . ') DO UPDATE SET '
                    . implode(', ', $setParts);
            }
            if ($insert && $insert[1]) {
                $values = '(' . implode(', ', $insert[1]) . ')';
                $bodySize += mb_strlen($values) + 4;
                $body[] = $values;
                if ($bodySize > $maxBodySize) {
                    $result[] = $head . implode(', ', $body) . $tail;
                    $body = [];
                    $bodySize = 0;
                }
            }
        }
        if ($body) {
            $result[] = $head . implode(', ', $body) . $tail;
        }

        return $result;
    }

    public function prepareMergeSelect($tableName, array $primaryFields, array $selectFields, $select, $updateFields): string
    {
        $updateColumns = [];
        $updateValues = [];

        $tableFields = $this->connection->getTableFields($tableName);
        $tableFields = array_change_key_case($tableFields, CASE_UPPER);
        $updateFields = array_change_key_case($updateFields, CASE_UPPER);
        foreach ($updateFields as $columnName => $value) {
            if (isset($tableFields[$columnName])) {
                $updateColumns[] = $this->quote($columnName);
                $updateValues[] = $this->convertToDb($value, $tableFields[$columnName]);
            } else {
                trigger_error("Column `{$columnName}` is not found in the `{$tableName}` table", E_USER_WARNING);
            }
        }

        $sql = 'INSERT INTO ' . $this->quote($tableName) . ' (' . implode(',', array_map([$this, 'quote'], $selectFields)) . ') ';
        $sql .= $select;
        $sql .= ' ON CONFLICT (' . implode(',', array_map([$this, 'quote'], $primaryFields)) . ') DO UPDATE SET ';
        $pairs = [];
        foreach ($updateColumns as $idx => $col) {
            $pairs[] = $col . ' = ' . $updateValues[$idx];
        }
        $sql .= implode(', ', $pairs);

        return $sql;
    }

    public function prepareDeleteLimit($tableName, array $primaryFields, $where, array $order, $limit): string
    {
        $primaryColumns = [];
        foreach ($primaryFields as $columnName) {
            $primaryColumns[] = $this->quote($columnName);
        }
        $sqlPrimary = implode(', ', $primaryColumns);

        $orderColumns = [];
        foreach ($order as $columnName => $sort) {
            $orderColumns[] = $this->quote($columnName) . ' ' . $sort;
        }
        $sqlOrder = $orderColumns ? ' ORDER BY ' . implode(', ', $orderColumns) : '';

        return 'DELETE FROM ' . $this->quote($tableName) . ' WHERE (' . $sqlPrimary . ') IN (SELECT ' . $sqlPrimary
            . ' FROM ' . $this->quote($tableName) . ' WHERE ' . $where . $sqlOrder . ' LIMIT ' . (int) $limit . ')';
    }

    public function initRowNumber($variableName): string
    {
        return '';
    }

    public function getRowNumber($variableName): string
    {
        return 'row_number() OVER ()';
    }

    public function prepareCorrelatedUpdate($tableName, $tableAlias, $fields, $from, $where): string
    {
        $dml = 'UPDATE ' . $tableName . ' AS ' . $tableAlias . " SET\n";

        $set = '';
        foreach ($fields as $fieldName => $fieldValue) {
            $set .= ($set ? ',' : '') . $fieldName . ' = ' . $fieldValue . "\n";
        }
        $dml .= $set;
        $dml .= 'FROM ' . $from . "\n";
        $dml .= 'WHERE ' . $where . "\n";

        return $dml;
    }

    protected function getOrderByField(string $field, array $values, callable $callback, bool $quote = true): string
    {
        $field = $quote ? $this->quote($field) : $field;
        $when = [];
        $i = 1;
        foreach ($values as $value) {
            $when[] = 'WHEN ' . $field . ' = ' . $callback($value) . ' THEN ' . $i;
            $i++;
        }

        return 'CASE ' . implode(' ', $when) . ' ELSE 999999 END';
    }

    public function isBigType($type): bool
    {
        if (is_string($type)) {
            return mb_strtoupper($type) === 'BLOB';
        }

        return false;
    }
}
