<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Module;

use MB\Bitrix\Module\Entity;
use PHPUnit\Framework\TestCase;

final class EntityConstructionGuardTest extends TestCase
{
    public function test_peek_during_construction_returns_null_outside_ctor(): void
    {
        self::assertNull(Entity::peekDuringConstruction('any.module'));
    }
}
