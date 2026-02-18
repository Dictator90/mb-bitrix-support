<?php

namespace MB\Bitrix\Agent;

use Bitrix\Main;
use MB\Bitrix\Contracts\Module\Entity;

/**
 * Базовый класс для описания агентов модуля.
 *
 * Конкретный агент в проекте:
 * - наследует этот класс;
 * - реализует {@see Base::getAgents()} и возвращает массив описаний агентов;
 * - реализует метод(ы), указанные в описании (по умолчанию {@see Base::run()}).
 *
 * Формат одного описания агента (AgentDefinition):
 * <code>
 * [
 *   'method'    => string,        // имя вызываемого статического метода (по умолчанию 'run')
 *   'arguments' => array|null,    // позиционные аргументы вызова (опционально)
 *   'interval'  => int,           // интервал запуска в секундах (по умолчанию 86400)
 *   'sort'      => int,           // сортировка (по умолчанию 100)
 *   'next_exec' => string,        // дата следующего запуска Y-m-d H:i:s (опционально)
 *   'update'    => string,        // правило обновления NEXT_EXEC (см. AgentManager::UPDATE_RULE_*)
 *   'search'    => string,        // правило поиска существующих агентов (см. AgentManager::SEARCH_RULE_*)
 * ]
 * </code>
 *
 * Вместо одного описания можно вернуть массив таких описаний.
 */
abstract class Base
{
    /**
     * Декларация агентов для текущего класса.
     *
     * Возвращает одно или несколько описаний в формате AgentDefinition
     * (см. описание в phpdoc класса).
     *
     * @return array<int, array<string, mixed>>|array<string, mixed>
     */
    abstract public static function getAgents(): array;

    /**
     * Полное имя класса с ведущим backslash.
     */
    public static function getClassName()
    {
        return '\\' . get_called_class();
    }

    /**
     * Проверяет, зарегистрирован ли агент с заданными параметрами.
     *
     * @param Entity     $moduleEntity объект модуля
     * @param array|null $agentParams  AgentDefinition или его часть
     */
    public static function isRegistered(Entity $moduleEntity, $agentParams = null)
    {
        $className = static::getClassName();
        $agentParams =
            !isset($agentParams)
                ? static::getDefaultParams()
                : array_merge(static::getDefaultParams(), $agentParams)
        ;

        return AgentManager::create($moduleEntity)->isRegistered($className, $agentParams);
    }

    /**
     * Регистрирует агент с заданными параметрами.
     *
     * @throws Main\NotImplementedException
     * @throws Main\SystemException
     */
    public static function register(Entity $moduleEntity, $agentParams = null)
    {
        $className = static::getClassName();
        $agentParams =
            !isset($agentParams)
                ? static::getDefaultParams()
                : array_merge(static::getDefaultParams(), $agentParams)
        ;

        AgentManager::create($moduleEntity)->register($className, $agentParams);
    }

    /**
     * Удаляет агент с заданными параметрами.
     *
     * @param Entity     $moduleEntity
     * @param array|null $agentParams
     */
    public static function unregister(Entity $moduleEntity, ?array $agentParams = null): void
    {
        $className = static::getClassName();
        $agentParams =
            !isset($agentParams)
                ? static::getDefaultParams()
                : array_merge(static::getDefaultParams(), $agentParams)
        ;

        AgentManager::create($moduleEntity)->unregister($className, $agentParams);
    }

    /**
     * Обертка для вызова метода агента.
     *
     * Если метод агента возвращает:
     *  - false  — агент будет удалён (CAgent вернёт null);
     *  - array  — массив будет использован как новые аргументы вызова;
     *  - иное   — агент будет переустановлен с теми же аргументами.
     *
     * @param string     $method
     * @param array|null $arguments
     *
     * @return string|null Строка вызова для CAgent или null, если агент должен быть удалён.
     */
    public static function callAgent(string $method, array|null $arguments = null): ?string
    {
        $className = static::getClassName();
        $result = '';

        if (is_array($arguments)) {
            $callResult = call_user_func_array([$className, $method], $arguments);
        } else {
            $callResult = call_user_func([$className, $method]);
        }

        if ($callResult !== false) {
            if (is_array($callResult)) {
                $arguments = $callResult;
            }

            $result = AgentManager::getAgentCall($className, $method, $arguments);
        }

        return $result;
    }

    /**
     * Базовые параметры агента по умолчанию.
     *
     * Переопределяется в наследниках при необходимости.
     *
     * @return array<string, mixed>
     */
    public static function getDefaultParams()
    {
        return [];
    }

    /**
     * Метод агента по умолчанию.
     *
     * Должен вернуть:
     *  - false  — чтобы агент был удалён;
     *  - array  — новые аргументы вызова для следующего запуска;
     *  - любое иное значение — агент будет запланирован повторно с теми же аргументами.
     *
     * @return mixed|false
     */
    public static function run()
    {
        return false;
    }
}
