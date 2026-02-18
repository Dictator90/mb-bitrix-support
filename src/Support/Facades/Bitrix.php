<?php

declare(strict_types=1);

namespace MB\Bitrix\Support\Facades;

use Bitrix\Main\Application as BitrixApplication;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\HttpContext;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use MB\Bitrix\Support\Facade;

/**
 * Static facade for core Bitrix services exposed via the Application container.
 *
 * This facade centralises access to:
 * - global CMain instance
 * - Bitrix\Main\Application
 * - HTTP context, request, response and cache
 *
 * Under the hood it uses the following container bindings:
 * - bitrix.cmain
 * - bitrix.application
 * - bitrix.context
 * - bitrix.request
 * - bitrix.response
 * - bitrix.cache
 */
final class Bitrix extends Facade
{
    /**
     * {@inheritDoc}
     *
     * Facade root is Bitrix\Main\Application. Extra helpers are provided as
     * explicit static methods below and do not rely on the container alias.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bitrix.application';
    }

    /**
     * Get the global CMain instance.
     */
    public static function cmain(): \CMain
    {
        /** @var \CMain */
        return app('bitrix.cmain');
    }

    /**
     * Get Bitrix application instance.
     */
    public static function app(): BitrixApplication
    {
        /** @var BitrixApplication */
        return app('bitrix.application');
    }

    /**
     * Get current HTTP context.
     */
    public static function context(): HttpContext
    {
        /** @var HttpContext */
        return app('bitrix.context');
    }

    /**
     * Get current HTTP request.
     */
    public static function request(): HttpRequest
    {
        /** @var HttpRequest */
        return app('bitrix.request');
    }

    /**
     * Get current HTTP response.
     */
    public static function response(): HttpResponse
    {
        /** @var HttpResponse */
        return app('bitrix.response');
    }

    /**
     * Get Bitrix cache service.
     */
    public static function cache(): Cache
    {
        /** @var Cache */
        return app('bitrix.cache');
    }
}

