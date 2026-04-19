<?php

declare(strict_types=1);

namespace MB\Bitrix\Database;

use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\Result;
use Bitrix\Main\Diag\SqlTrackerQuery;
use Bitrix\Main\ORM\Fields\ScalarField;
use PDOStatement;

/**
 * Результат запроса SQLite: строки буферизуются, чтобы корректно работали {@see Result::getSelectedRowsCount()} и повторные обходы.
 */
class SqlLiteResult extends Result
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var array<string, ScalarField>|null */
    private ?array $resultFields = null;

    /**
     * @param PDOStatement|list<array<string, mixed>> $result
     */
    public function __construct($result, Connection $dbConnection = null, SqlTrackerQuery $trackerQuery = null)
    {
        if ($result instanceof PDOStatement) {
            if ($result->columnCount() > 0) {
                $this->resultFields = $this->buildFieldsFromStatement($result, $dbConnection);
                while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                    $this->rows[] = array_change_key_case($row, CASE_UPPER);
                }
            }
            $result->closeCursor();
            $result = $this->rows;
        } elseif (is_array($result)) {
            $this->rows = array_map(
                static fn (array $row): array => array_change_key_case($row, CASE_UPPER),
                $result
            );
            $result = $this->rows;
        }

        parent::__construct($result, $dbConnection, $trackerQuery);
    }

    /**
     * @return array<string, ScalarField>
     */
    public function getFields(): array
    {
        if ($this->resultFields === null) {
            $this->resultFields = [];
        }

        return $this->resultFields;
    }

    public function getSelectedRowsCount(): int
    {
        return count($this->resource);
    }

    public function getFieldsCount(): int
    {
        return count($this->getFields());
    }

    protected function fetchRowInternal(): array|false
    {
        $val = current($this->resource);
        if ($val === false) {
            return false;
        }
        next($this->resource);

        return $val;
    }

    private function buildFieldsFromStatement(PDOStatement $statement, ?Connection $connection): array
    {
        $fields = [];
        $count = $statement->columnCount();
        if ($count === 0 || !$connection) {
            return $fields;
        }

        $helper = $connection->getSqlHelper();
        for ($i = 0; $i < $count; $i++) {
            $meta = $statement->getColumnMeta($i);
            if ($meta === false) {
                continue;
            }
            $name = $meta['name'] ?? '';
            $fieldName = mb_strtoupper((string) $name);
            $native = $meta['sqlite:decl_type'] ?? $meta['native_type'] ?? 'string';
            $fields[$fieldName] = $helper->getFieldByColumnType($fieldName, is_string($native) ? $native : 'string');
        }

        return $fields;
    }
}
