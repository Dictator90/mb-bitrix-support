<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Migration;

use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Migration\Facade;
use PHPUnit\Framework\TestCase;

final class FacadeLoadTest extends TestCase
{
    public function test_facade_class_loads_and_can_be_instantiated(): void
    {
        $module = $this->createStub(ModuleEntityContract::class);
        $facade = new Facade($module);

        self::assertInstanceOf(Facade::class, $facade);
    }
}
