<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Config;

use MB\Bitrix\Config\ArrayRepository;
use PHPUnit\Framework\TestCase;

final class ArrayRepositoryTest extends TestCase
{
    public function test_dot_get_set_has(): void
    {
        $r = new ArrayRepository(['app' => ['name' => 'demo']]);
        $this->assertTrue($r->has('app.name'));
        $this->assertSame('demo', $r->get('app.name'));
        $this->assertSame('fallback', $r->get('missing', 'fallback'));
        $r->set('app.debug', true);
        $this->assertTrue($r->get('app.debug'));
    }

    public function test_load_from_directory(): void
    {
        $dir = dirname(__DIR__) . '/fixtures/config';
        $r = new ArrayRepository();
        $r->loadFromDirectory($dir);
        $this->assertSame('fixture-app', $r->get('sample.key'));
    }
}
