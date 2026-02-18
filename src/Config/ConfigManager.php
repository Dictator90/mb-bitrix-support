<?php

namespace MB\Core\Config;

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
            self::$instances[$siteKey] = Entity::createByModuleId($moduleId, empty($siteId) ? '' : $siteId);
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
