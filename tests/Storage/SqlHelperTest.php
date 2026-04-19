<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Storage;

use Bitrix\Main\DB\Connection;
use InvalidArgumentException;
use MB\Bitrix\Storage\SqlHelper;
use PHPUnit\Framework\TestCase;

/**
 * UC-4 (mixed keys in {@see \MB\Bitrix\Storage\Concerns\BatchUpsertTrait::addBatch}): document in `docs/storage-and-highloadblock.md`;
 * normalize row keys — the first row defines the INSERT column list.
 */
final class SqlHelperTest extends TestCase
{
    public function testPostgresUpsertThrowsWhenPrimaryEmptyAndUpdateRequested(): void
    {
        $connection = new Connection('pgsql');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Postgres upsert requires conflict target');

        SqlHelper::buildCrossPlatformUpsertSql(
            $connection,
            'items',
            '"a","b"',
            '(1,2)',
            [],
            ['b'],
            true
        );
    }

    public function testMysqlBatchUpdateSqlUsesRowConstructor(): void
    {
        $connection = new Connection('mysql');
        $sql = SqlHelper::buildCrossPlatformUpdateSql(
            $connection,
            't',
            ['id'],
            ['id', 'name'],
            ['(1, \'a\')', '(2, \'b\')']
        );

        self::assertStringContainsString('ROW(1, \'a\')', $sql);
        self::assertStringContainsString('VALUES', $sql);
    }

    public function testPostgresBatchUpdateUsesValuesClause(): void
    {
        $connection = new Connection('pgsql');
        $sql = SqlHelper::buildCrossPlatformUpdateSql(
            $connection,
            't',
            ['id'],
            ['id', 'name'],
            ['(1, \'a\')', '(2, \'b\')']
        );

        self::assertStringContainsString('FROM (VALUES', $sql);
        self::assertStringContainsString('AS updates', $sql);
    }

}
