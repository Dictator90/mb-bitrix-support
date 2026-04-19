<?php

declare(strict_types=1);

/**
 * Minimal Bitrix DB stubs for package unit tests (loaded before Composer autoload).
 */
namespace Bitrix\Main\DB {
    if (! class_exists(\Bitrix\Main\DB\SqlHelper::class, false)) {
        final class SqlHelper
        {
            public function quote(string $identifier): string
            {
                return '"' . str_replace('"', '""', $identifier) . '"';
            }
        }
    }

    if (! class_exists(\Bitrix\Main\DB\Connection::class, false)) {
        class Connection
        {
            public function __construct(private string $type = 'mysql') {}

            public function getType(): string
            {
                return $this->type;
            }

            public function getSqlHelper(): SqlHelper
            {
                return new SqlHelper();
            }
        }
    }
}
