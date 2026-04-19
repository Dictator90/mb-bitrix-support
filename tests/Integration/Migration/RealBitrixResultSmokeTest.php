<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Integration\Migration;

use Bitrix\Main\Error;
use MB\Bitrix\Migration\Result;
use PHPUnit\Framework\TestCase;

final class RealBitrixResultSmokeTest extends TestCase
{
    public function test_result_extends_real_bitrix_result_and_merges_errors(): void
    {
        $partial = new \Bitrix\Main\Result();
        $partial->addError(new Error('bitrix-error'));

        $result = new Result();
        $result->merge($partial);

        self::assertFalse($result->isSuccess());
        self::assertCount(1, $result->getErrors());
        self::assertSame('bitrix-error', $result->getErrors()[0]->getMessage());
    }

    public function test_add_throwable_creates_real_bitrix_error(): void
    {
        $result = new Result();
        $result->addThrowable(new \RuntimeException('boom', 17));

        self::assertCount(1, $result->getErrors());
        self::assertSame('boom', $result->getErrors()[0]->getMessage());
        self::assertSame(17, $result->getErrors()[0]->getCode());
    }
}
