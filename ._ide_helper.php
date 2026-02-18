<?php

/**
 * Этот файл предназначен только для подсказок IDE (VS Code, PhpStorm и т.п.)
 * и не должен подключаться в рантайме.
 */

use MB\Bitrix\Foundation\Application as KernelApplication;
use MB\Bitrix\Page\Asset;
use MB\Filesystem\Contracts\Filesystem as FilesystemContract;
use MB\Bitrix\Module\Entity as ModuleEntity;
use MB\Bitrix\Migration\Facade as MigrationFacade;
use MB\Bitrix\Support\Facades\Filesystem as FilesystemFacade;
use MB\Bitrix\Support\Facades\Asset as AssetFacade;
use MB\Bitrix\Support\Facades\Module as ModuleFacade;
use MB\Bitrix\Support\Facades\Migration as MigrationFacadeStatic;
use MB\Bitrix\Support\Facades\Bitrix as BitrixFacade;
use Bitrix\Main\Application as BitrixApplication;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\HttpContext;
use Bitrix\Main\Data\Cache;

/**
 * Глобальный helper для доступа к контейнеру приложения.
 *
 * Примеры:
 *  $app = app();                // KernelApplication
 *  $asset = app(Asset::class);  // Asset
 *
 * @template T of object
 * @param class-string<T>|string|null $id
 * @return KernelApplication|T
 */
function app(?string $id = null)
{
    $app = KernelApplication::getInstance();

    if ($id === null) {
        return $app;
    }

    /** @var T */
    return $app->make($id);
}

/**
 * Доступ к фасаду ассетов Bitrix.
 *
 * @return Asset
 */
function asset(): Asset
{
    /** @var Asset */
    return app('asset');
}

/**
 * Доступ к файловой системе.
 *
 * @return FilesystemContract
 */
function filesystem(): FilesystemContract
{
    /** @var FilesystemContract */
    return app('filesystem');
}

/**
 * Доступ к сущности модуля по умолчанию.
 *
 * @return ModuleEntity
 */
function module(): ModuleEntity
{
    /** @var ModuleEntity */
    return app('module');
}

/**
 * Фасад миграций.
 *
 * @return MigrationFacade
 */
function migration_facade(): MigrationFacade
{
    /** @var MigrationFacade */
    return app('migration.facade');
}

/**
 * Глобальный объект Bitrix `$APPLICATION`.
 *
 * @return \CMain
 */
function bitrix_cmain(): \CMain
{
    /** @var \CMain */
    return app('bitrix.cmain');
}

/**
 * Экземпляр `Bitrix\Main\Application`.
 *
 * @return BitrixApplication
 */
function bitrix_app(): BitrixApplication
{
    /** @var BitrixApplication */
    return app('bitrix.application');
}

/**
 * HTTP‑контекст Bitrix.
 *
 * @return HttpContext
 */
function bitrix_context(): HttpContext
{
    /** @var HttpContext */
    return app('bitrix.context');
}

/**
 * HTTP‑запрос Bitrix.
 *
 * @return HttpRequest
 */
function bitrix_request(): HttpRequest
{
    /** @var HttpRequest */
    return app('bitrix.request');
}

/**
 * HTTP‑ответ Bitrix.
 *
 * @return HttpResponse
 */
function bitrix_response(): HttpResponse
{
    /** @var HttpResponse */
    return app('bitrix.response');
}

/**
 * Кеш Bitrix.
 *
 * @return Cache
 */
function bitrix_cache(): Cache
{
    /** @var Cache */
    return app('bitrix.cache');
}

/**
 * Static facade shortcuts for container-bound services.
 *
 * Эти псевдо-алиасы нужны только для IDE и позволяют писать:
 *  Fs::get(...), A::addCss(...), M::getId(), Mig::up(...), Bx::app()
 */
class_alias(FilesystemFacade::class, 'Fs');
class_alias(AssetFacade::class, 'A');
class_alias(ModuleFacade::class, 'M');
class_alias(MigrationFacadeStatic::class, 'Mig');
class_alias(BitrixFacade::class, 'Bx');

