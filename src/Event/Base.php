<?php

namespace MB\Bitrix\Event;

use Bitrix\Main\SystemException;
use MB\Bitrix\Contracts\Module\Entity;
use MB\Bitrix\Migration\Result;

/**
 * Базовый класс для описания обработчиков событий Bitrix.
 *
 * Конкретный обработчик:
 * - наследует этот класс;
 * - реализует {@see Base::getHandlers()} и возвращает массив описаний обработчиков;
 * - реализует методы, указанные в этих описаниях.
 *
 * Формат одного описания обработчика (EventHandlerDefinition):
 * <code>
 * [
 *   'module'    => string,        // код модуля-источника события (FROM_MODULE_ID)
 *   'event'     => string,        // код события (MESSAGE_ID)
 *   'method'    => string|null,   // метод класса-обработчика, по умолчанию равно event
 *   'sort'      => int,           // сортировка (по умолчанию 100)
 *   'arguments' => array|string,  // дополнительные аргументы обработчика (опционально)
 * ]
 * </code>
 *
 * Вместо одного описания можно вернуть массив таких описаний.
 */
abstract class Base
{
    /**
     * Декларация обработчиков событий для текущего класса.
     *
     * Возвращает одно или несколько описаний в формате EventHandlerDefinition
     * (см. описание в phpdoc класса).
     *
     * @return array<int, array<string, mixed>>|array<string, mixed>
     */
    abstract public static function getHandlers(): array;
    
    /**
     * Полное имя класса с ведущим backslash.
     */
    public static function getClassName()
    {
        return '\\' . get_called_class();
    }

    /**
     * Регистрирует обработчик события.
     *
     * @param array|null $handlerParams параметры обработчика (module, event, method, sort, arguments)
     */
    public static function register(Entity $moduleEntity, ?array $handlerParams = null): Result
    {
        $result = new Result();

        try {
            $className = static::getClassName();
            $handlerParams =
                !isset($handlerParams)
                    ? static::getDefaultParams()
                    : array_merge(static::getDefaultParams(), $handlerParams);

            EventManager::create($moduleEntity)->register($className, $handlerParams);
        } catch (\Throwable $e) {
            $result->addThrowable($e);
        }

        return $result;
    }

    /**
     * Удаляем событие
     *
     * @throws SystemException
     */
    public static function unregister(Entity $moduleEntity, $handlerParams = null): void
    {
        $className = static::getClassName();

        $handlerParams =
            !isset($handlerParams)
                ? static::getDefaultParams()
                : array_merge(static::getDefaultParams(), $handlerParams);

        EventManager::create($moduleEntity)->unregister($className, $handlerParams);
    }

    /**
     * @return array описания обработчика для выполнения по умолчанию (module, event, method, sort, arguments)
     */
    public static function getDefaultParams(): array
    {
        return [];
    }
}
