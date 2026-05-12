<?php

declare(strict_types=1);

/**
 * Minimal Bitrix DB stubs for package unit tests (loaded before Composer autoload).
 */
namespace Bitrix\Main\DB {
    if (! class_exists(\Bitrix\Main\DB\SqlHelper::class, false)) {
        class SqlHelper
        {
            public function quote(string $identifier): string
            {
                return '"' . str_replace('"', '""', $identifier) . '"';
            }

            /**
             * @param array<string,mixed> $data
             * @return array{0:string,1:string}
             */
            public function prepareInsert(string $tableName, array $data, string $prefix = ''): array
            {
                $columns = [];
                $values = [];
                foreach ($data as $column => $value) {
                    $columns[] = $this->quote((string) $column);
                    $values[] = is_numeric($value) ? (string) $value : "'" . addslashes((string) $value) . "'";
                }

                return [implode(', ', $columns), implode(', ', $values)];
            }

            /**
             * @param array<string,mixed> $data
             * @return array{0:string,1:array<string,mixed>}
             */
            public function prepareUpdate(string $tableName, array $data): array
            {
                $set = [];
                foreach ($data as $column => $value) {
                    $set[] = $this->quote((string) $column) . " = '" . addslashes((string) $value) . "'";
                }

                return [implode(', ', $set), []];
            }

            public function convertToDb(mixed $value, mixed $field = null): string
            {
                if ($value === null) {
                    return 'NULL';
                }
                if (is_numeric($value)) {
                    return (string) $value;
                }

                return "'" . addslashes((string) $value) . "'";
            }
        }
    }

    if (! class_exists(\Bitrix\Main\DB\Connection::class, false)) {
        class Connection
        {
            /**
             * @param string|array<string,mixed> $type
             */
            public function __construct(private string|array $type = 'mysql') {}

            public function getType(): string
            {
                if (is_string($this->type)) {
                    return $this->type;
                }

                return isset($this->type['type']) && is_string($this->type['type'])
                    ? $this->type['type']
                    : 'mysql';
            }

            public function getSqlHelper(): SqlHelper
            {
                return new SqlHelper();
            }

            public function queryExecute(string $sql, array $binds = []): void
            {
            }

            public function queryScalar(string $sql): mixed
            {
                return null;
            }

            public function startTransaction(): void
            {
            }

            public function commitTransaction(): void
            {
            }

            public function rollbackTransaction(): void
            {
            }

            public function getInsertedId(): int
            {
                return 1;
            }

            /**
             * @return array<string,mixed>
             */
            public function getTableFields(string $tableName): array
            {
                return [];
            }
        }
    }
}
