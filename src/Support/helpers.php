<?php

declare(strict_types=1);

use MB\Bitrix\Contracts\Config\Repository as ConfigRepositoryContract;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Module\Entity;
use MB\Support\Str;

if (! function_exists('app')) {
    /**
     * Returns the kernel application container (requires {@see Application::setInstance()}).
     */
    function app(?string $abstract = null): mixed
    {
        $instance = Application::getInstance();

        return $abstract === null ? $instance : $instance->make($abstract);
    }
}

if (! function_exists('config')) {
    /**
     * Get / set config values via the {@see ConfigRepositoryContract} bound as `config`.
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        /** @var ConfigRepositoryContract $repository */
        $repository = app('config');

        if ($key === null) {
            return $repository->all();
        }

        return $repository->get($key, $default);
    }
}

if (! function_exists('module')) {
    /**
     * Resolve a registered module entity ({@see Application::registerModule()}).
     */
    function module(string $id): ModuleEntityContract
    {
        $normalized = Str::lower(Str::trim($id));
        $partial = Entity::peekDuringConstruction($normalized);
        if ($partial instanceof ModuleEntityContract) {
            return $partial;
        }

        return app("{$normalized}:module");
    }
}
