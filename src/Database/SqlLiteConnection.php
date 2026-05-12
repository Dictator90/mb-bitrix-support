<?php

declare(strict_types=1);

namespace MB\Bitrix\Database;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\ConnectionException;
use Bitrix\Main\DB\DuplicateEntryException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\DB\TransactionException;
use Bitrix\Main\Diag\SqlTrackerQuery;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\ScalarField;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Соединение с SQLite через PDO по контракту {@see Connection} (аналог {@see \Bitrix\Main\DB\PgsqlConnection} / {@see \Bitrix\Main\DB\MysqliConnection}).
 *
 * В конфигурации пула Bitrix в {@code className} укажите этот класс; путь к файлу БД — в ключе {@code database}
 * ({@code :memory:} для in-memory).
 *
 * @method PDO getResource()
 */
class SqlLiteConnection extends Connection
{
    protected int $transactionLevel = 0;

    /** @var PDOStatement|false|null */
    protected $lastQueryResult;

    private int $lastRowsAffected = 0;

    protected function connectInternal(): void
    {
        if ($this->isConnected) {
            return;
        }

        $database = $this->database;
        if ($database === ':memory:' || $database === '') {
            $dsn = 'sqlite::memory:';
        } else {
            $dsn = 'sqlite:' . $database;
        }

        try {
            $flags = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
            if ($this->isPersistent()) {
                $flags[\PDO::ATTR_PERSISTENT] = true;
            }
            $pdo = new PDO($dsn, null, null, $flags);
        } catch (PDOException $e) {
            throw new ConnectionException('SQLite connect error', $e->getMessage(), $e);
        }

        $pdo->sqliteCreateFunction(
            'regexp',
            static function ($pattern, $value): int {
                $pattern = (string) $pattern;
                $value = $value === null ? '' : (string) $value;
                set_error_handler(static fn (): bool => true);
                try {
                    return @preg_match($pattern, $value) === 1 ? 1 : 0;
                } finally {
                    restore_error_handler();
                }
            },
            2
        );

        $pdo->sqliteCreateFunction(
            'sha1',
            static fn ($value): string => hash('sha1', (string) $value),
            1
        );

        $this->resource = $pdo;
        $this->isConnected = true;

        $this->afterConnected();
    }

    protected function disconnectInternal(): void
    {
        if (!$this->isConnected) {
            return;
        }

        $this->isConnected = false;
        $this->resource = null;
    }

    protected function createSqlHelper(): SqlLiteSqlHelper
    {
        return new SqlLiteSqlHelper($this);
    }

    protected function queryInternal($sql, ?array $binds = null, ?SqlTrackerQuery $trackerQuery = null)
    {
        $this->connectInternal();

        $trackerQuery?->startQuery($sql, $binds);

        try {
            /** @var PDO $pdo */
            $pdo = $this->resource;
            $statement = $pdo->query($sql);
        } catch (PDOException $e) {
            $trackerQuery?->finishQuery();
            throw $this->createQueryException($e->getCode(), $e->getMessage(), $sql);
        }

        $trackerQuery?->finishQuery();

        $this->lastQueryResult = $statement;
        $this->lastRowsAffected = 0;
        if ($statement instanceof PDOStatement && $statement->columnCount() === 0) {
            $this->lastRowsAffected = max(0, $statement->rowCount());
        }

        return $statement;
    }

    protected function createResult($result, ?SqlTrackerQuery $trackerQuery = null): SqlLiteResult
    {
        return new SqlLiteResult($result, $this, $trackerQuery);
    }

    /**
     * Без пула {@see \Bitrix\Main\Data\ConnectionPool} и {@see \Bitrix\Main\Application} — соединение пригодно для unit-тестов и CLI без prolog.
     */
    public function query($sql)
    {
        [$sql, $binds, $offset, $limit] = self::parseQueryFunctionArgs(func_get_args());

        if ($limit > 0) {
            $sql = $this->getSqlHelper()->getTopSql($sql, $limit, $offset);
        }

        $trackerQuery = null;

        if ($this->queryExecutingEnabled) {
            if ($this->trackSql) {
                $trackerQuery = $this->sqlTracker->getNewTrackerQuery();
                $trackerQuery->setNode($this->getNodeId());
            }

            $result = $this->queryInternal($sql, $binds, $trackerQuery);
        } else {
            if ($this->disabledQueryExecutingDump === null) {
                $this->disabledQueryExecutingDump = [];
            }

            $this->disabledQueryExecutingDump[] = $sql;
            $result = true;
        }

        return $this->createResult($result, $trackerQuery);
    }

    public function getInsertedId(): int
    {
        return (int) $this->getResource()->lastInsertId();
    }

    public function getAffectedRowsCount(): int
    {
        return $this->lastRowsAffected;
    }

    public function isTableExists($tableName): bool
    {
        $name = $this->getSqlHelper()->forSql($tableName);
        $row = $this->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = '" . $name . "' LIMIT 1"
        )->fetch();

        return is_array($row);
    }

    public function isIndexExists($tableName, array $columns): bool
    {
        return $this->getIndexName($tableName, $columns) !== null;
    }

    public function getIndexName($tableName, array $columns, $strict = false): ?string
    {
        if ($columns === []) {
            return null;
        }

        $helper = $this->getSqlHelper();
        $quotedTable = $helper->quote($tableName);

        $r = $this->query('PRAGMA index_list(' . $quotedTable . ')');
        $indexes = [];
        while ($a = $r->fetch()) {
            $indexName = $a['NAME'] ?? null;
            if ($indexName === null || ($a['ORIGIN'] ?? '') === 'pk') {
                continue;
            }

            $info = $this->query('PRAGMA index_info(' . $helper->quote((string) $indexName) . ')');
            $cols = [];
            while ($b = $info->fetch()) {
                $seq = (int) ($b['SEQNO'] ?? $b['seqno'] ?? 0);
                $cols[$seq] = mb_strtoupper((string) ($b['NAME'] ?? $b['name'] ?? ''));
            }
            ksort($cols);
            $indexes[(string) $indexName] = $cols;
        }

        return static::findIndex($indexes, $columns, $strict);
    }

    public function getTableFields($tableName): array
    {
        if (!isset($this->tableColumnsCache[$tableName]) || $this->tableColumnsCache[$tableName] === []) {
            $helper = $this->getSqlHelper();
            $quoted = $helper->quote($tableName);

            $query = $this->query('PRAGMA table_info(' . $quoted . ')');
            $this->tableColumnsCache[$tableName] = [];
            while ($fieldInfo = $query->fetch()) {
                $fieldName = mb_strtoupper((string) ($fieldInfo['NAME'] ?? $fieldInfo['name'] ?? ''));
                $fieldType = (string) ($fieldInfo['TYPE'] ?? $fieldInfo['type'] ?? 'text');
                $field = $helper->getFieldByColumnType($fieldName, $fieldType);
                $this->tableColumnsCache[$tableName][$fieldName] = $field;
            }
        }

        return $this->tableColumnsCache[$tableName];
    }

    public function createTable($tableName, $fields, $primary = [], $autoincrement = []): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->getSqlHelper()->quote($tableName) . ' (';
        $sqlFields = [];

        foreach ($fields as $columnName => $field) {
            if (!($field instanceof ScalarField)) {
                throw new ArgumentException(sprintf(
                    'Field `%s` should be an Entity\ScalarField instance',
                    $columnName
                ));
            }

            $realColumnName = $field->getColumnName();

            if (in_array($columnName, $autoincrement, true)) {
                $type = 'INTEGER PRIMARY KEY AUTOINCREMENT';
                if ($field instanceof IntegerField) {
                    switch ($field->getSize()) {
                        case 2:
                            $type = 'INTEGER PRIMARY KEY AUTOINCREMENT';
                            break;
                        case 8:
                            $type = 'INTEGER PRIMARY KEY AUTOINCREMENT';
                            break;
                    }
                }
            } else {
                $type = $this->getSqlHelper()->getColumnTypeByField($field);
            }

            $sqlFields[] = $this->getSqlHelper()->quote($realColumnName)
                . ' ' . $type
                . ($field->isNullable() ? '' : ' NOT NULL');
        }

        $sql .= implode(', ', $sqlFields);

        if ($primary !== []) {
            foreach ($primary as &$primaryColumn) {
                $realColumnName = $fields[$primaryColumn]->getColumnName();
                $primaryColumn = $this->getSqlHelper()->quote($realColumnName);
            }

            $sql .= ', PRIMARY KEY(' . implode(', ', $primary) . ')';
        }

        $sql .= ')';

        $this->query($sql);
    }

    public function createIndex($tableName, $indexName, $columnNames, $columnLengths = null, $indexType = null)
    {
        if (!is_array($columnNames)) {
            $columnNames = [$columnNames];
        }

        $sqlHelper = $this->getSqlHelper();

        foreach ($columnNames as &$columnName) {
            $columnName = $sqlHelper->quote($columnName);
        }
        unset($columnName);

        if ($indexType === static::INDEX_UNIQUE) {
            return $this->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS ' . $sqlHelper->quote($indexName)
                . ' ON ' . $sqlHelper->quote($tableName) . '(' . implode(',', $columnNames) . ')'
            );
        }

        if ($indexType === static::INDEX_FULLTEXT) {
            return $this->query(
                'CREATE INDEX IF NOT EXISTS ' . $sqlHelper->quote($indexName)
                . ' ON ' . $sqlHelper->quote($tableName) . '(' . implode(',', $columnNames) . ')'
            );
        }

        return $this->query(
            'CREATE INDEX IF NOT EXISTS ' . $sqlHelper->quote($indexName)
            . ' ON ' . $sqlHelper->quote($tableName) . '(' . implode(',', $columnNames) . ')'
        );
    }

    public function renameTable($currentName, $newName): void
    {
        $h = $this->getSqlHelper();
        $this->query(
            'ALTER TABLE ' . $h->quote($currentName) . ' RENAME TO ' . $h->quote($newName)
        );
    }

    public function dropTable($tableName): void
    {
        $this->query('DROP TABLE IF EXISTS ' . $this->getSqlHelper()->quote($tableName));
    }

    public function truncateTable($tableName)
    {
        return $this->query('DELETE FROM ' . $this->getSqlHelper()->quote($tableName));
    }

    public function startTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            $this->query('BEGIN');
        } else {
            $this->query('SAVEPOINT TRANS' . $this->transactionLevel);
        }

        $this->transactionLevel++;
    }

    public function commitTransaction(): void
    {
        $this->transactionLevel--;

        if ($this->transactionLevel < 0) {
            throw new TransactionException('Transaction was not started.');
        }

        if ($this->transactionLevel === 0) {
            $this->query('COMMIT');
        }
    }

    public function rollbackTransaction(): void
    {
        $this->transactionLevel--;

        if ($this->transactionLevel < 0) {
            throw new TransactionException('Transaction was not started.');
        }

        if ($this->transactionLevel === 0) {
            $this->query('ROLLBACK');
        } else {
            $this->query('ROLLBACK TO SAVEPOINT TRANS' . $this->transactionLevel);
        }
    }

    public function getType(): string
    {
        return 'sqlite';
    }

    public function getVersion(): array
    {
        if ($this->version === null) {
            $this->connectInternal();
            $v = (string) $this->queryScalar('SELECT sqlite_version()');
            if (preg_match('#^(\d+\.\d+(?:\.\d+)?)#', $v, $m)) {
                $this->version = $m[1];
            } else {
                $this->version = $v;
            }
        }

        return [$this->version, $this->versionExpress];
    }

    public function getErrorMessage(): string
    {
        if (!$this->isConnected || !$this->resource instanceof PDO) {
            return '';
        }

        $info = $this->resource->errorInfo();

        return ($info[2] ?? $info[0] ?? '') . '';
    }

    public function getErrorCode()
    {
        if ($this->resource instanceof PDO) {
            return $this->resource->errorCode() ?: 0;
        }

        return 0;
    }

    public function createQueryException($code = 0, $databaseMessage = '', $query = '')
    {
        $msg = (string) $databaseMessage;
        if (str_contains($msg, 'UNIQUE constraint')
            || str_contains($msg, 'unique constraint')
            || $code === '23000'
            || $code === 23000
        ) {
            return new DuplicateEntryException('Sqlite query error', $databaseMessage, $query);
        }

        return new SqlQueryException('Sqlite query error', $databaseMessage, $query);
    }
}
