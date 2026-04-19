<?php

namespace MB\Bitrix\Config;

use Bitrix\Main\Context;

class ConfigManager
{
    /**
     * @var array<string, Entity> Хранилище экземпляров
     */
    private static array $instances = [];

    public static function get(string $moduleId, string|false $siteId = ''): Entity
    {
        $siteKey = self::getInstanceKey($moduleId, $siteId);

        if (!isset(self::$instances[$siteKey])) {
            $resolvedSiteId = $siteId === false
                ? Context::getCurrent()->getSite()
                : (string) $siteId;
            self::$instances[$siteKey] = Entity::create($moduleId, $resolvedSiteId);
        }

        return self::$instances[$siteKey];
    }

    private static function getInstanceKey($moduleId, $siteId): string
    {
        $siteKey =
            $siteId === false
                ? Context::getCurrent()->getSite()
                : (
                    empty($siteId)
                        ? 'default'
                        : $siteId
            );

        return "{$moduleId}_{$siteKey}";
    }
}
