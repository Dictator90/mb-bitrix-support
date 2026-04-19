<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Migration;

use Bitrix\Main\Error;
use MB\Bitrix\Contracts\Config\Entity as ConfigEntityContract;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Migration\Entities\File;
use MB\Bitrix\Migration\Result;
use PHPUnit\Framework\TestCase;

final class FileEntityTest extends TestCase
{
    public function test_up_processes_known_actions_and_replaces_module_placeholder(): void
    {
        $entity = new TestFileMigrationEntity($this->module([
            'copy-dir' => ['admin' => '/bitrix/admin/{{moduleId}}'],
            'copy-dir-files' => ['components' => '/local/components/{{moduleId}}'],
        ]));
        $entity->existingSources = [
            '/module/install/admin' => true,
            '/module/install/components' => true,
        ];

        $result = $entity->up();

        self::assertTrue($result->isSuccess());
        self::assertSame(
            [
                ['copy-dir', '/module/install/admin', '/bitrix/admin/vendor.test'],
                ['copy-dir-files', '/module/install/components', '/local/components/vendor.test'],
            ],
            $entity->calls
        );
        self::assertSame(
            [
                '/bitrix/admin/vendor.test' => 'success',
                '/local/components/vendor.test' => 'success',
            ],
            $result->getData()
        );
    }

    public function test_up_reports_unknown_action_as_error(): void
    {
        $entity = new TestFileMigrationEntity($this->module([
            'mystery' => ['admin' => '/bitrix/admin/{{moduleId}}'],
        ]));
        $entity->existingSources = [
            '/module/install/admin' => true,
        ];

        $result = $entity->up();

        self::assertFalse($result->isSuccess());
        self::assertSame('/bitrix/admin/vendor.test', array_key_first($result->getData()));
        self::assertSame('error', $result->getData()['/bitrix/admin/vendor.test']);
        self::assertSame('Unknown install action `mystery`', $result->getErrors()[0]->message);
    }

    public function test_down_uses_reverse_target_and_skips_missing_target(): void
    {
        $entity = new TestFileMigrationEntity($this->module([
            'copy-dir' => ['admin' => '/bitrix/admin/{{moduleId}}'],
            'copy-dir-files' => ['components' => '/local/components/{{moduleId}}'],
        ]));
        $entity->existingTargets = [
            '/bitrix/admin/vendor.test' => true,
            '/local/components/vendor.test' => false,
        ];

        $result = $entity->down();

        self::assertTrue($result->isSuccess());
        self::assertSame(
            [
                ['delete-dir', '/bitrix/admin/vendor.test'],
            ],
            $entity->calls
        );
        self::assertSame(
            ['/bitrix/admin/vendor.test' => 'success'],
            $result->getData()
        );
    }

    /**
     * @param array<string, array<string, string>> $installConfig
     */
    private function module(array $installConfig): ModuleEntityContract
    {
        $module = $this->createStub(ModuleEntityContract::class);
        $module->method('getId')->willReturn('vendor.test');
        $module->method('getPath')->willReturn('/abs/module');
        $module->method('getLocalPath')->willReturn('/module');
        $module->method('getLibPath')->willReturn('/module/lib');
        $module->method('getNamespace')->willReturn('\\Vendor\\Test');
        $module->method('getInstallConfig')->willReturn($installConfig);
        $module->method('getConfig')->willReturn(null);

        return $module;
    }
}

final class TestFileMigrationEntity extends File
{
    /** @var array<string, bool> */
    public array $existingSources = [];

    /** @var array<string, bool> */
    public array $existingTargets = [];

    /** @var list<array<int, string>> */
    public array $calls = [];

    protected function sourceExists(string $fromDir): bool
    {
        return $this->existingSources[$fromDir] ?? false;
    }

    protected function targetExists(string $toDir): bool
    {
        return $this->existingTargets[$toDir] ?? false;
    }

    public function copyDir(string $fromDir, string $toDir, bool $rewrite = true, bool $recursive = true): Result
    {
        $this->calls[] = [$rewrite && $recursive ? 'copy-dir' : 'copy-dir-files', $fromDir, $toDir];

        return new Result();
    }

    public function deleteDir(string $dirName): Result
    {
        $this->calls[] = ['delete-dir', $dirName];

        return new Result();
    }

    public function deleteDirFiles(string $fromDir, string $toDir): Result
    {
        $this->calls[] = ['delete-dir-files', $fromDir, $toDir];

        return new Result();
    }

    protected function unknownActionResult(string $action): Result
    {
        return (new Result())->addError(new Error("Unknown install action `{$action}`"));
    }
}
