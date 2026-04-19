<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Database;

use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use MB\Bitrix\Database\SqlLite;
use MB\Bitrix\Database\SqlLiteConnection;
use PHPUnit\Framework\TestCase;

final class SqlLiteConnectionTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is not available');
        }

        $dir = dirname(__DIR__) . '/.runtime/sqlite-phpunit';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $this->dbPath = $dir . '/test-' . uniqid('', true) . '.sqlite';
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->dbPath) && is_file($this->dbPath)) {
            @unlink($this->dbPath);
        }
    }

    public function test_connect_execute_select_and_version(): void
    {
        $connection = SqlLite::connect($this->dbPath);

        self::assertInstanceOf(SqlLiteConnection::class, $connection);
        self::assertSame('sqlite', $connection->getType());

        $connection->queryExecute('CREATE TABLE IF NOT EXISTS t1 (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $connection->queryExecute("INSERT INTO t1 (name) VALUES ('one')");
        self::assertSame(1, $connection->getInsertedId());

        $row = $connection->query('SELECT id, name FROM t1 WHERE id = 1')->fetch();
        self::assertIsArray($row);
        self::assertSame(1, (int) $row['ID']);
        self::assertSame('one', $row['NAME']);

        [$ver] = $connection->getVersion();
        self::assertMatchesRegularExpression('/^\d+\.\d+/', (string) $ver);
    }

    public function test_is_table_exists_and_table_fields(): void
    {
        $connection = new SqlLiteConnection(SqlLite::configuration($this->dbPath));
        $connection->queryExecute('CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY, title TEXT)');

        self::assertTrue($connection->isTableExists('items'));
        self::assertFalse($connection->isTableExists('missing'));

        $fields = $connection->getTableFields('items');
        self::assertArrayHasKey('ID', $fields);
        self::assertArrayHasKey('TITLE', $fields);
    }

    public function test_create_table_via_bitrix_api(): void
    {
        $connection = SqlLite::connect($this->dbPath);
        $connection->createTable('u', [
            'ID' => new IntegerField('ID'),
            'NAME' => new StringField('NAME'),
        ], [], ['ID']);

        self::assertTrue($connection->isTableExists('u'));
    }

    public function test_transaction_commit_and_rollback(): void
    {
        $connection = SqlLite::connect($this->dbPath);
        $connection->queryExecute('CREATE TABLE IF NOT EXISTS tx (id INTEGER PRIMARY KEY, v INTEGER NOT NULL DEFAULT 0)');

        $connection->startTransaction();
        $connection->queryExecute('INSERT INTO tx (id, v) VALUES (1, 1)');
        $connection->commitTransaction();

        self::assertSame(1, (int) $connection->queryScalar('SELECT v FROM tx WHERE id = 1'));

        $connection->startTransaction();
        $connection->queryExecute('UPDATE tx SET v = 2 WHERE id = 1');
        $connection->rollbackTransaction();

        self::assertSame(1, (int) $connection->queryScalar('SELECT v FROM tx WHERE id = 1'));
    }

    public function test_settings_template_uses_sql_lite_connection_class(): void
    {
        $settingsFile = dirname(__DIR__) . '/.runtime/bitrix-docroot/local/.settings.php';
        self::assertFileExists($settingsFile);
        $settings = include $settingsFile;
        self::assertIsArray($settings);
        $default = $settings['connections']['value']['default'] ?? null;
        self::assertIsArray($default);
        self::assertStringContainsString('SqlLiteConnection', (string) ($default['className'] ?? ''));
        self::assertArrayHasKey('database', $default);
    }
}
