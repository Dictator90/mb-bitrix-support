<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Integration\Migration;

use MB\Bitrix\Contracts\Config\Entity as ConfigEntityContract;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Migration\Entities\File;
use MB\Bitrix\Migration\Result;
use PHPUnit\Framework\TestCase;

final class RealBitrixFileEntitySmokeTest extends TestCase
{
    public function test_unknown_action_returns_real_bitrix_error(): void
    {
        $entity = new class($this->module()) extends File {
            public function __construct(ModuleEntityContract $module)
            {
                parent::__construct($module);
            }

            protected function sourceExists(string $fromDir): bool
            {
                return true;
            }

            public function copyDir(string $fromDir, string $toDir, bool $rewrite = true, bool $recursive = true): Result
            {
                return new Result();
            }

            public function deleteDir(string $dirName): Result
            {
                return new Result();
            }

            public function deleteDirFiles(string $fromDir, string $toDir): Result
            {
                return new Result();
            }
        };

        $result = $entity->up();

        self::assertFalse($result->isSuccess());
        self::assertCount(1, $result->getErrors());
        self::assertSame('Unknown install action `unknown-action`', $result->getErrors()[0]->getMessage());
        self::assertSame(
            ['/bitrix/admin/vendor.test' => 'error'],
            $result->getData()
        );
    }

    private function module(): ModuleEntityContract
    {
        $module = $this->createStub(ModuleEntityContract::class);
        $module->method('getId')->willReturn('vendor.test');
        $module->method('getPath')->willReturn('/abs/module');
        $module->method('getLocalPath')->willReturn('/module');
        $module->method('getLibPath')->willReturn('/module/lib');
        $module->method('getNamespace')->willReturn('\\Vendor\\Test');
        $module->method('getInstallConfig')->willReturn([
            'unknown-action' => [
                'admin' => '/bitrix/admin/{{moduleId}}',
            ],
        ]);
        $module->method('getConfig')->willReturn(null);

        return $module;
    }
}
