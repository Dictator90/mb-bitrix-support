<?php

namespace MB\Core\Config;

use MB\Bitrix\Filesystem\Filesystem;

class ConfigLocator
{
	public static function getConfigByModuleId(string $moduleId)
	{
        $moduleManager = module($moduleId);

        $result = array_column(
            Filesystem::classFinder()->extends($moduleManager->getLibPath(), Entity::getClassName()),
            'class'
        );

        return $result[0];
	}

    public static function getConfigByPath(string $path, string $baseName)
    {
        $result = array_column(
            Filesystem::classFinder()->extends($path, Entity::getClassName()),
            'class'
        );

        return $result[0];
    }
}
