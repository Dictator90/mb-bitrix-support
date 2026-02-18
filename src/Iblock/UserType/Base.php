<?php

namespace MB\Bitrix\Iblock\UserType;

use Bitrix\Main\ModuleManager;
use MB\Bitrix\Contracts\Iblock\UserTypeInterface;

abstract class Base implements UserTypeInterface
{
    /**
     * Строковый идентификатор пользовательского свойства
     */
    abstract public static function getUserType(): string;

    /**
     * Тип пользовательского свойства
     * @return string 'S', 'E', 'N', 'F', 'G', 'L'
     * @see \Bitrix\Iblock\PropertyTable
     */
    abstract public static function getPropertyType(): string;

    /**
     * Название пользовательского свойства
     */
    abstract public static function getDescription(): string;

    public static function getUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE' => static::getPropertyType(),
            'USER_TYPE' => static::getUserType(),
            'DESCRIPTION' => static::getDescription(),
            'GetPropertyFieldHtml' => [static::class, 'getPropertyFieldHtml'],
            'GetPropertyFieldHtmlMulty' => [static::class, 'getPropertyFieldHtmlMulty'],
            'GetPublicViewHTML' => [static::class, 'getPublicViewHTML']
        ];
    }

    protected static function getClass(): string
    {
        return static::class;
    }

    protected static function checkDependence(): bool
    {
        foreach (static::getModuleDependence() as $moduleId) {
            if (!ModuleManager::isModuleInstalled($moduleId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Массив идентификаторов модулей от которых зависит пользовательское свойство
     * @return array<string>
     */
    protected static function getModuleDependence(): array
    {
        return [];
    }

    protected static function getDependenceErrorMessage(): string
    {
        return 'Один из обязательных модулей отсутствует: ' . implode(',', static::getModuleDependence());
    }
}
