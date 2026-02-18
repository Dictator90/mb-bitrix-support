<?php
namespace PHPSTORM_META {

    /* string aliases */
    override(\app(0), map($containerAliases));
    override(\MB\Core\Foundation\KernelApplication::make(0), map([
        'app' => \MB\Bitrix\Foundation\Application::class,
        'asset' => \MB\Bitrix\Page\Asset::class,
        'filesystem' => \MB\Filesystem\Filesystem::class,
        'module' => \MB\Bitrix\Module\Entity::class,
        'migration.facade' => \MB\Bitrix\Migration\Facade::class,
        'bitrix.cmain' => \CMain::class,
        'bitrix.application' => \Bitrix\Main\Application::class,
        'bitrix.request' => \Bitrix\Main\HttpRequest::class,
        'bitrix.context' => \Bitrix\Main\HttpContext::class,
        'bitrix.response' => \Bitrix\Main\HttpResponse::class,
        'bitrix.cache' => \Bitrix\Main\Data\Cache::class,
    ]));
    override(\MB\Core\Foundation\KernelApplication::get(0), map($containerAliases));
    override(\MB\Core\Foundation\KernelApplication::offsetGet(0), map($containerAliases));
    override(\MB\Core\Foundation\KernelApplication::__get(0), map($containerAliases));

    /* class-string<T> fallback */
    override(\app(), \MB\Core\Foundation\KernelApplication::class);
    override(\app(0), map(['' => '@']));
    override(\MB\Core\Foundation\KernelApplication::make(0), map(['' => '@']));
    override(\MB\Core\Foundation\KernelApplication::get(0), map(['' => '@']));
    override(\MB\Core\Foundation\KernelApplication::offsetGet(0), map(['' => '@']));
    override(\MB\Core\Foundation\KernelApplication::__get(0), map(['' => '@']));
}