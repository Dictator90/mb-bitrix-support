<?php

namespace MB\Core\Config;

use MB\Core\Support\Finder\ClassFinder;

class ConfigLocator
{
	public static function getConfigByModuleId(string $moduleId)
	{
        $moduleManager = module($moduleId);

        $result = ClassFinder::findExtended(
            $moduleManager->getLibPath(),
            $moduleManager->getNamespace(),
            Entity::getClassName()
        );

        return $result[0];
	}

    public static function getConfigByPath(string $path, string $baseName)
    {
        $result = ClassFinder::findExtended(
            $path,
            $baseName,
            Entity::getClassName()
        );

        return $result[0];
    }
}
