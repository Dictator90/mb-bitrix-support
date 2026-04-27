<?php
namespace PHPSTORM_META {

    /* string aliases */
    override(\app(0), map($containerAliases));
    override(\MB\Bitrix\Foundation\Application::make(0), map([
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
    override(\MB\Bitrix\Foundation\Application::get(0), map($containerAliases));
    override(\MB\Bitrix\Foundation\Application::offsetGet(0), map($containerAliases));
    override(\MB\Bitrix\Foundation\Application::__get(0), map($containerAliases));

    /* class-string<T> fallback */
    override(\app(), \MB\Bitrix\Foundation\Application::class);
    override(\app(0), map(['' => '@']));
    override(\MB\Bitrix\Foundation\Application::make(0), map(['' => '@']));
    override(\MB\Bitrix\Foundation\Application::get(0), map(['' => '@']));
    override(\MB\Bitrix\Foundation\Application::offsetGet(0), map(['' => '@']));
    override(\MB\Bitrix\Foundation\Application::__get(0), map(['' => '@']));
}