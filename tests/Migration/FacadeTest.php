<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Migration;

use Bitrix\Main\Error;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Migration\BaseEntity;
use MB\Bitrix\Migration\Facade;
use MB\Bitrix\Migration\Result;
use PHPUnit\Framework\TestCase;

final class FacadeTest extends TestCase
{
    protected function tearDown(): void
    {
        TestMigrationEntityState::$calls = [];
    }

    public function test_up_all_runs_default_pipeline_in_install_order(): void
    {
        $facade = new Facade($this->module(), [
            'file' => TestFileEntity::class,
            'storage' => TestStorageEntity::class,
            'event' => TestEventEntity::class,
        ]);

        $result = $facade->upAll();

        self::assertTrue($result->isSuccess());
        self::assertSame(
            ['file.up', 'storage.up', 'event.up'],
            TestMigrationEntityState::$calls
        );
        self::assertSame(['file', 'storage', 'event'], array_keys($result->getData()));
    }

    public function test_down_all_runs_reverse_pipeline_order(): void
    {
        $facade = new Facade($this->module(), [
            'file' => TestFileEntity::class,
            'storage' => TestStorageEntity::class,
            'event' => TestEventEntity::class,
        ]);

        $result = $facade->downAll();

        self::assertTrue($result->isSuccess());
        self::assertSame(
            ['event.down', 'storage.down', 'file.down'],
            TestMigrationEntityState::$calls
        );
        self::assertSame(['event', 'storage', 'file'], array_keys($result->getData()));
    }

    public function test_up_all_aggregates_step_errors(): void
    {
        $facade = new Facade($this->module(), [
            'file' => TestFileEntity::class,
            'storage' => TestBrokenEntity::class,
            'event' => TestEventEntity::class,
        ]);

        $result = $facade->upAll();

        self::assertFalse($result->isSuccess());
        self::assertCount(1, $result->getErrors());
        self::assertSame('broken-up', $result->getErrors()[0]->message);
        self::assertSame(
            ['file.up', 'storage.up', 'event.up'],
            TestMigrationEntityState::$calls
        );
    }

    public function test_up_agents_runs_optional_entity_outside_default_pipeline(): void
    {
        $facade = new Facade($this->module(), [
            'file' => TestFileEntity::class,
            'storage' => TestStorageEntity::class,
            'event' => TestEventEntity::class,
            'agent' => TestAgentEntity::class,
        ]);

        $result = $facade->upAgents();

        self::assertTrue($result->isSuccess());
        self::assertSame(['agent.up'], TestMigrationEntityState::$calls);
    }

    private function module(): ModuleEntityContract
    {
        return $this->createStub(ModuleEntityContract::class);
    }
}

final class TestMigrationEntityState
{
    /** @var list<string> */
    public static array $calls = [];
}

abstract class TestEntity extends BaseEntity
{
    public function check(): bool
    {
        return true;
    }

    protected function ok(string $operation): Result
    {
        TestMigrationEntityState::$calls[] = static::name() . '.' . $operation;

        return new Result();
    }

    abstract protected static function name(): string;
}

final class TestFileEntity extends TestEntity
{
    protected static function name(): string
    {
        return 'file';
    }

    public function up(): Result
    {
        return $this->ok('up');
    }

    public function down(): Result
    {
        return $this->ok('down');
    }
}

final class TestStorageEntity extends TestEntity
{
    protected static function name(): string
    {
        return 'storage';
    }

    public function up(): Result
    {
        return $this->ok('up');
    }

    public function down(): Result
    {
        return $this->ok('down');
    }
}

final class TestEventEntity extends TestEntity
{
    protected static function name(): string
    {
        return 'event';
    }

    public function up(): Result
    {
        return $this->ok('up');
    }

    public function down(): Result
    {
        return $this->ok('down');
    }
}

final class TestBrokenEntity extends TestEntity
{
    protected static function name(): string
    {
        return 'storage';
    }

    public function up(): Result
    {
        TestMigrationEntityState::$calls[] = static::name() . '.up';

        return (new Result())->addError(new Error('broken-up'));
    }

    public function down(): Result
    {
        return $this->ok('down');
    }
}

final class TestAgentEntity extends TestEntity
{
    protected static function name(): string
    {
        return 'agent';
    }

    public function up(): Result
    {
        return $this->ok('up');
    }

    public function down(): Result
    {
        return $this->ok('down');
    }
}
