<?php

namespace MB\Bitrix\Config;

use MB\Bitrix\Config\Entity as BitrixConfigEntity;
use MB\Bitrix\Filesystem\Filesystem;

/**
 * Discovers module config entity classes under module `lib/` (extends {@see BitrixConfigEntity}).
 *
 * Discovery targets classes under `MB\Bitrix\Config\Entity`.
 */
class ConfigLocator
{
    public static function getConfigByModuleId(string $moduleId)
    {
        $moduleManager = \module($moduleId);
        $libPath = $moduleManager->getLibPath();
        if ($libPath === null) {
            return null;
        }

        $result = array_column(
            Filesystem::classFinder()->extends($libPath, BitrixConfigEntity::getClassName()),
            'class'
        );

        return $result[0] ?? null;
    }

    /**
     * @param string $path Absolute path to scan (e.g. module `lib/`)
     * @param string $baseName Unused; reserved for future filter by module namespace segment
     */
    public static function getConfigByPath(string $path, string $baseName)
    {
        $result = array_column(
            app('filesystem')->classFinder()->extends($path, BitrixConfigEntity::getClassName()),
            'class'
        );

        return $result[0] ?? null;
    }
}