<?php

declare(strict_types=1);

use Bitrix\Main\Localization\Loc;
use MB\Bitrix\Contracts\Config\Repository as ConfigRepositoryContract;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Module\Entity;
use MB\Bitrix\Page\Asset;
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

if (! function_exists('__loc')) {
    function __loc(string $code, ?array $replace = null, $fallback = null, ?string $lang = null): ?string
    {
        return Loc::getMessage($code, $replace, $lang) ?? $fallback;
    }
}

function asset(): Asset
{
    /** @var Asset */
    return app('asset');
}

if (! function_exists('icon')) {
    /**
     * Возвращает HTML инлайновой SVG-иконки из спрайта фронтенда.
     *
     * Иконки — стиль Lucide (stroke + currentColor): цвет задаётся CSS-свойством
     * `color`, размер — классом-модификатором `icon--{size}` (xs/sm/md/lg/xl/2xl/3xl)
     * либо utility-классами `w-/h-`. Без модификатора размер = 1em (по тексту).
     *
     * URL спрайта берётся из конфига: если `config('frontend.sprite')` абсолютный
     * (начинается с `/` или содержит `://`) — используется как есть; иначе
     * склеивается как `config('frontend.path')` + '/' + `config('frontend.sprite')`.
     *
     * @param string $name  Имя иконки без префикса (имя файла в src/icons/), напр. `arrow-right`.
     * @param string $size  Размер-модификатор: xs|sm|md|lg|xl|2xl|3xl. Пусто — 1em.
     * @param string $class Доп. CSS-классы для тега <svg>.
     * @param array<string,string> $attrs Доп. атрибуты (напр. ['title' => 'Телефон']).
     */
    function icon(string $name, string $size = '', string $class = '', array $attrs = []): string
    {
        static $spriteUrl = null;
        if ($spriteUrl === null) {
            $spriteCfg = (string) config('frontend.sprite', 'img/sprite.svg');
            if (str_starts_with($spriteCfg, '/') || str_contains($spriteCfg, '://')) {
                $spriteUrl = $spriteCfg;
            } else {
                $base = rtrim((string) config('frontend.path', '/local/templates/ee/frontend/dist'), '/');
                $spriteUrl = $base . '/' . ltrim($spriteCfg, '/');
            }
        }

        $title = $attrs['title'] ?? null;
        unset($attrs['title']);

        $classAttr = 'icon'
            . ($size !== '' ? ' icon--' . $size : '')
            . ($class !== '' ? ' ' . $class : '');

        $extra = '';
        foreach ($attrs as $key => $value) {
            $extra .= ' ' . htmlspecialcharsbx((string)$key) . '="' . htmlspecialcharsbx((string)$value) . '"';
        }

        $a11y = $title !== null
            ? ' role="img" aria-label="' . htmlspecialcharsbx($title) . '"'
            : ' aria-hidden="true" focusable="false"';

        $href = htmlspecialcharsbx($spriteUrl) . '#icon-' . htmlspecialcharsbx($name);

        return '<svg class="' . htmlspecialcharsbx($classAttr) . '"' . $a11y . $extra . '>'
            . '<use href="' . $href . '"></use>'
            . '</svg>';
    }
}
