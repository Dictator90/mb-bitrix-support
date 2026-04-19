<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Support;

use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Support\Facades\Config;
use MB\Bitrix\Support\Facade;
use PHPUnit\Framework\TestCase;

final class HelpersAndFacadeTest extends TestCase
{
    protected function tearDown(): void
    {
        Application::setInstance(null);
        Facade::clearResolvedInstances();
    }

    public function test_config_helper_and_facade(): void
    {
        $app = new MinimalKernel();
        $app->make('config')->set('kernel.test', 42);
        $this->assertSame(42, config('kernel.test'));
        $this->assertArrayHasKey('kernel', config());
        $this->assertSame(42, Config::get('kernel.test'));

        Config::swap(new \MB\Bitrix\Config\ArrayRepository(['x' => 1]));
        $this->assertSame(1, Config::get('x'));
    }

    public function test_module_helper_resolves_binding(): void
    {
        $app = new MinimalKernel();
        $stub = $this->createStub(ModuleEntityContract::class);
        $app->instance('vendor.test:module', $stub);
        $this->assertSame($stub, module('vendor.test'));
    }

    public function test_set_base_path_loads_config_files(): void
    {
        $root = sys_get_temp_dir() . '/mb-bitrix-support-kernel-' . uniqid();
        mkdir($root, 0777, true);
        mkdir($root . '/config', 0777, true);
        file_put_contents($root . '/config/app.php', "<?php\nreturn ['env' => 'testing'];\n");

        try {
            $app = new MinimalKernel();
            $app->setBasePath($root);
            $this->assertSame('testing', config('app.env'));
        } finally {
            @unlink($root . '/config/app.php');
            @rmdir($root . '/config');
            @rmdir($root);
        }
    }
}
