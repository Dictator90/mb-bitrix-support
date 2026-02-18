<?php

namespace MB\Bitrix\Event;

use Bitrix\Main;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Db\SqlQueryException;
use Bitrix\Main\NotImplementedException;
use MB\Bitrix\Finder\ClassFinder;
use MB\Bitrix\Migration\BaseEntityManager;
use MB\Bitrix\Migration\Result;
use MB\Support\Str;

class EventManager extends BaseEntityManager
{
    protected Main\EventManager $eventManager;

    public function __construct(\MB\Bitrix\Contracts\Module\Entity $module)
    {
        $this->eventManager = Main\EventManager::getInstance();
        parent::__construct($module);
    }

    public function getEntityClass(): string
    {
        return Base::class;
    }

    /**
     * Синхронизирует зарегистрированные обработчики событий
     * с декларациями {@see Base::getHandlers()} во всех классах модуля.
     *
     * Удобная обёртка над {@see EventManager::update()} для сценариев миграций.
     */
    public function syncAll(): Result
    {
        return $this->update();
    }

    /**
     * Добавляем обработчик
     *
     * @throws Main\NotImplementedException
     * @throws Main\SystemException
     */
    public function register(string $className, array $handlerParams): void
    {
        $handlerDescription = $this->getHandlerDescription($className, $handlerParams);
        $this->saveHandler($handlerDescription);
    }

    /**
     * Удаляем обработчик
     *
     * @throws ArgumentException
     * @throws SqlQueryException
     * @throws NotImplementedException
     */
    public function unregister(string $className, array $handlerParams): void
    {
        $handlerDescription = $this->getHandlerDescription($className, $handlerParams);
        $registeredList = $this->getRegisteredHandlers($className);
        $handlerKey = $this->getHandlerKey($handlerDescription, true);

        if (isset($registeredList[$handlerKey])) {
            $this->deleteHandler($registeredList[$handlerKey]);
        }
    }

    /**
     * Обновляет привязки регулярных обработчиков событий
     *
     * @throws Main\NotImplementedException
     * @throws Main\SystemException
     */
    public function update(): Result
    {
        $result = new Result();

        try {
            $classList = ClassFinder::findExtended(
                $this->module->getLibPath(),
                $this->module->getNamespace(),
                $this->getEntityClass()
            );

            $handlerList = $this->getClassHandlers($classList);
            $registeredList = $this->getRegisteredHandlers($this->getEntityClass());

            $this->saveHandlers($handlerList);
            $this->deleteHandlers($handlerList, $registeredList);
        } catch (\Exception $e) {
            $result->addThrowable($e);
        }

        return $result;
    }

    /**
     * Удаляем все события
     *
     * @throws SqlQueryException
     */
    public function deleteAll(): Result
    {
        $result = new Result();

        try {
            $registeredList = $this->getRegisteredHandlers($this->module->getNamespace(), true);
            $this->deleteHandlers([], $registeredList);
        } catch (\Exception $e) {
            $result->addThrowable($e);
        }

        return $result;
    }

    /**
     * Обходит список классов и готовит массив обработчиков для регистрации.
     *
     * @return array<string, array<string, mixed>> список обработчиков для регистрации
     * @throws Main\NotImplementedException
     * @throws Main\ArgumentException
     */
    protected function getClassHandlers(array $classList): array
    {
        $result = [];

        /** @var Base $className */
        foreach ($classList as $className) {
            $normalizedClassName = $className::getClassName();
            $handlers = $className::getHandlers();

            foreach ($handlers as $handler) {
                $handler['toModule'] = $this->module->getId();
                $handlerDescription = $this->getHandlerDescription($normalizedClassName, $handler);
                $handlerKey = $this->getHandlerKey($handlerDescription, true);

                $result[$handlerKey] = $handlerDescription;
            }
        }

        return $result;
    }

    /**
     * Возвращает описание обработчика для регистрации, проверяет существование метода.
     *
     * @return array<string, mixed>
     * @throws Main\NotImplementedException
     * @throws Main\ArgumentException
     */
    protected function getHandlerDescription(string $className, array $handlerParams): array
    {
        if (empty($handlerParams['module']) || empty($handlerParams['event'])) {
            throw new ArgumentException(
                'Require module and event param in ' . $className
            );
        }

        $method = $handlerParams['method'] ?? $handlerParams['event'];

        if (!method_exists($className, $method)) {
            throw new NotImplementedException(
                'Method ' . $method
                . ' not defined in ' . $className
                . ' and cannot be registered as event handler'
            );
        }

        return [
            'module' => $handlerParams['module'],
            'event' => $handlerParams['event'],
            'toModule' => $handlerParams['toModule'] ?? $this->module->getId(),
            'class' => $className,
            'method' => $method,
            'sort' => isset($handlerParams['sort']) ? (int)$handlerParams['sort'] : 100,
            'arguments' => $handlerParams['arguments'] ?? ''
        ];
    }

    /**
     * Получаем список ранее зарегистрированных обработчиков.
     *
     * @param string $baseClassName название класса или неймспейс
     * @param bool   $isBaseNamespace первый аргумент является неймспейсом
     *
     * @return array<string, array<string, mixed>> список зарегистрированных обработчиков
     * @throws Main\Db\SqlQueryException
     */
    protected function getRegisteredHandlers(string $baseClassName, bool $isBaseNamespace = false): array
    {
        $registeredList = [];
        $namespaceLower = str_replace('\\', '\\\\', Str::lower($baseClassName));
        $connection = Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $query = $connection->query(
            'SELECT * FROM b_module_to_module '
            . 'WHERE TO_CLASS like "' . $sqlHelper->forSql($namespaceLower) . '%"'
        );

        while ($handlerRow = $query->fetch()) {
            $handlerClassName = $handlerRow['TO_CLASS'];

            if (
                $isBaseNamespace
                || $handlerClassName === $baseClassName
                || !class_exists($handlerClassName)
                || is_subclass_of($handlerClassName, $baseClassName)
            ) {
                $handlerKey = $this->getHandlerKey($handlerRow, false);
                $registeredList[$handlerKey] = $handlerRow;
            }
        }

        return $registeredList;
    }

    /**
     * Получаем зарегистрированный обработчик.
     *
     * @return array<string, mixed>|null обработчик
     * @throws Main\Db\SqlQueryException
     */
    public function getRegisteredHandler(array $handlerDescription): ?array
    {
        $connection = Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $query = $connection->query(
            'SELECT * FROM b_module_to_module'
            . ' WHERE '
            . ' FROM_MODULE_ID = "' . $sqlHelper->forSql($handlerDescription['module']) . '"'
            . ' AND MESSAGE_ID = "' . $sqlHelper->forSql($handlerDescription['event']) . '"'
            . ' AND TO_CLASS = "' . $sqlHelper->forSql($handlerDescription['class']) . '"'
            . ' AND TO_METHOD = "' . $sqlHelper->forSql($handlerDescription['method']) . '"'
        );

        return $query->fetch() ?: null;
    }

    /**
     * Ключ массива для обработчика события.
     *
     * Ключ формируется из пары:
     * - module / FROM_MODULE_ID
     * - event / MESSAGE_ID
     * - class / TO_CLASS
     * - method / TO_METHOD
     * - arguments / TO_METHOD_ARG
     */
    protected function getHandlerKey(array $handlerData, bool $byDescription = false): string
    {
        $signKeys = [
            'module' => 'FROM_MODULE_ID',
            'event' => 'MESSAGE_ID',
            'class' => 'TO_CLASS',
            'method' => 'TO_METHOD',
            'arguments' => 'TO_METHOD_ARG'
        ];
        $values = [];

        foreach ($signKeys as $descriptionKey => $rowKey) {
            $key = $byDescription ? $descriptionKey : $rowKey;
            $values[] =
                is_array($handlerData[$key] ?? null) && !empty($handlerData[$key])
                    ? serialize($handlerData[$key])
                    : ($handlerData[$key] ?? '');
        }

        return Str::lower(implode('|', $values));
    }

    /**
     * Регистрирует все обработчики в базе данных
     *
     * @throws Main\SystemException
     */
    protected function saveHandlers(array $handlerList): void
    {
        foreach ($handlerList as $handlerDescription) {
            $this->saveHandler($handlerDescription);
        }
    }

    /**
     * Регистрируем обработчик в базе данных
     */
    protected function saveHandler(array $handlerDescription): void
    {
        $this->eventManager->registerEventHandler(
            $handlerDescription['module'],
            $handlerDescription['event'],
            $handlerDescription['toModule'],
            $handlerDescription['class'],
            $handlerDescription['method'],
            $handlerDescription['sort'],
            '',
            $handlerDescription['arguments']
        );
    }

    /**
     * Удаляет неиспользуемые обработчики из базы данных
     */
    protected function deleteHandlers(array $handlerList, array $registeredList): void
    {
        foreach ($registeredList as $handlerKey => $handlerRow) {
            if (!isset($handlerList[$handlerKey])) {
                $this->deleteHandler($handlerRow);
            }
        }
    }

    /**
     * Удаляет обработчик из базы данных
     */
    protected function deleteHandler(array $handlerRow): void
    {
        $handlerArgs = $handlerRow['TO_METHOD_ARG'] ?? '';

        if (is_string($handlerArgs)) {
            $handlerArgsUnserialize = @unserialize($handlerArgs);

            if (is_array($handlerArgsUnserialize) && !empty($handlerArgsUnserialize)) {
                $handlerArgs = $handlerArgsUnserialize;
            }
        }

        $this->eventManager->unregisterEventHandler(
            $handlerRow['FROM_MODULE_ID'],
            $handlerRow['MESSAGE_ID'],
            $handlerRow['TO_MODULE_ID'],
            $handlerRow['TO_CLASS'],
            $handlerRow['TO_METHOD'],
            '',
            $handlerArgs
        );
    }
}
