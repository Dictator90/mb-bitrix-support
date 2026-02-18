<?php

namespace MB\Bitrix\Agent;

use Bitrix\Main\NotImplementedException;
use Bitrix\Main\SystemException;
use CAgent;
use MB\Bitrix\Migration\BaseEntityManager;
use MB\Bitrix\Migration\Result;
use MB\Support\Arr;
use MB\Support\Str;

class AgentManager extends BaseEntityManager
{
    public const UPDATE_RULE_STRICT = 'strict';
    public const UPDATE_RULE_FUTURE = 'future';

    public const SEARCH_RULE_STRICT = 'strict';
    public const SEARCH_RULE_SOFT = 'soft';

    public function getEntityClass(): string
    {
        return Base::class;
    }

    /**
     * Синхронизирует зарегистрированные в Bitrix агенты
     * с декларациями {@see Base::getAgents()} во всех классах модуля.
     *
     * Удобная обёртка над {@see AgentManager::update()} для сценариев миграций.
     */
    public function syncAll(): Result
    {
        return $this->update();
    }

    public function isRegistered($className, $agentParams): bool
    {
        $agentDescription = $this->getAgentDescription($className, $agentParams);
        $searchRule = $agentParams['search'] ?? static::SEARCH_RULE_STRICT;
        $registeredAgent = $this->getRegisteredAgent($agentDescription, $searchRule);

        return $registeredAgent !== null;
    }

    /**
     * Обновляет привязки регулярных агентов
     */
    public function update(): Result
    {
        $result = new Result();
        try {
            //$classList = Filesystem::classFinder()->extends($this->module->getLibPath(), $this->module->getNamespace());
            $classList = ClassFinder::findExtended(
                $this->module->getLibPath(),
                $this->module->getNamespace(),
                $this->getEntityClass()
            );

            $agentList = $this->getClassAgents($classList);
            $registeredList = $this->getRegisteredAgents(true);

            $this->saveAgents($agentList, $registeredList);
            $this->deleteAgents($agentList, $registeredList);
        } catch (\Throwable $e) {
            $result->addThrowable($e);
        }

        return $result;
    }

    /**
     * Удаляем все агенты
     */
    public function deleteAll(): Result
    {
        $result = new Result();

        try {
            $registeredList = $this->getRegisteredAgents(true);

            $this->deleteAgents([], $registeredList);
        } catch (\Throwable $e) {
            $result->addThrowable($e);
        }

        return $result;
    }

    /**
     * Добавляет агент
     *
     * @throws NotImplementedException
     * @throws SystemException
     */
    public function register(string $className, ?array $agentParams): void
    {
        $agentDescription = $this->getAgentDescription($className, $agentParams);
        $searchRule = $agentParams['search'] ?? static::SEARCH_RULE_STRICT;
        $registeredAgent = $this->getRegisteredAgent($agentDescription, $searchRule);

        $this->saveAgent($agentDescription, $registeredAgent);
    }

    /**
     * Удаляем агент
     *
     * @throws NotImplementedException
     * @throws SystemException
     */
    public function unregister($className, $agentParams): void
    {
        $agentDescription = $this->getAgentDescription($className, $agentParams);
        $searchRule = $agentParams['search'] ?? static::SEARCH_RULE_STRICT;
        $previousId = null;

        do {
            $registeredAgent = $this->getRegisteredAgent($agentDescription, $searchRule);
            if ($registeredAgent === null) {
                break;
            }

            if ($previousId === $registeredAgent['ID']) {
                throw new SystemException(sprintf('cant delete agent with id %s', $previousId));
            }

            $this->deleteAgent($registeredAgent);
            $previousId = $registeredAgent['ID'];

            if ($searchRule !== static::SEARCH_RULE_SOFT) {
                break;
            }
        } while ($registeredAgent);
    }

    /**
     * Обходит список классов и готовит массив для записи
     *
     * @param array $classList список классов
     * @return array список агентов для регистрации
     * @throws NotImplementedException
     */
    public function getClassAgents(array $classList): array
    {
        $agentList = [];

        /** @var Base $className */
        foreach ($classList as $className) {
            $normalizedClassName = $className::getClassName();
            $agents = $className::getAgents();

            if (empty($agents)) {
                $agents = ['method' => 'run'];
            }

            if (Arr::isAssoc($agents)) {
                $agents = [$agents];
            }

            foreach ($agents as $agent) {
                $agentDescription = $this->getAgentDescription($normalizedClassName, $agent);
                $agentKey = $this->normalizeAgentKey($agentDescription['name']);
                $agentList[$agentKey] = $agentDescription;
            }
        }

        return $agentList;
    }

    /**
     * Возвращает описание агента для регистрации, проверяет существование метода
     *
     * @return array
     * @throws NotImplementedException
     */
    public function getAgentDescription(string $className, ?array $agentParams): array
    {
        $method = $agentParams['method'] ?? 'run';

        if (!method_exists($className, $method)) {
            throw new NotImplementedException(
                'Method ' . $method
                . ' not defined in ' . $className
                . ' and cannot be registered as agent'
            );
        }

        $agentFnCall = $this->getAgentCall($className, $method, $agentParams['arguments'] ?? null);

        return [
            'name' => $agentFnCall,
            'sort' => isset($agentParams['sort']) ? (int)$agentParams['sort'] : 100,
            'interval' => isset($agentParams['interval']) ? (int)$agentParams['interval'] : 86400,
            'next_exec' => $agentParams['next_exec'] ?? '',
            'update' => $agentParams['update'] ?? null,
        ];
    }

    /**
     * Получаем список ранее зарегистрированных агентов
     *
     * @param bool $isBaseNamespace первый аргумент не является классом
     * @return array список зарегистрированных агентов
     */
    public function getRegisteredAgents(bool $isBaseNamespace = false): array
    {
        $registeredList = [];
        $namespaceLower = Str::lower($this->module->getNamespace());
        $query = CAgent::GetList(
            [],
            [
                'MODULE_ID' => $this->module->getId(),
                'NAME' => $namespaceLower . '%'
            ]
        );

        while ($agentRow = $query->fetch()) {
            $agentCallParts = explode('::', $agentRow['NAME']);
            $agentClassName = trim($agentCallParts[0]);

            if (
                $isBaseNamespace
                || $agentClassName === ''
                || !class_exists($agentClassName)
                || is_subclass_of($agentClassName, $this->getEntityClass())
            ) {
                $agentKey = $this->normalizeAgentKey($agentRow['NAME']);
                $registeredList[$agentKey] = $agentRow;
            }
        }

        return $registeredList;
    }

    /**
     * Получаем зарегистрированный агент для метода класса
     *
     * @return array|null зарегистрированный агент
     */
    public function getRegisteredAgent(array $agentDescription, string $searchRule = self::SEARCH_RULE_STRICT): ?array
    {
        $result = null;
        $variants = array_unique([
            $agentDescription['name'],
            str_replace(PHP_EOL, '', $agentDescription['name'])
        ]);

        if ($searchRule === static::SEARCH_RULE_SOFT) {
            foreach ($variants as &$variant) {
                $variant = preg_replace_callback(
                    '/::callAgent\((["\']\w+?["\'])(?:(, array\s*\(.*)(\)\s*))?\)/s',
                    static function ($matches) {
                        return isset($matches[2], $matches[3])
                            ? '::callAgent(' . $matches[1] . $matches[2] . '%' . $matches[3] . ')'
                            : '::callAgent(' . $matches[1] . '%)';
                    },
                    $variant
                );
            }
            unset($variant);
        }

        foreach ($variants as $variant) {
            $query = CAgent::GetList([], [
                'MODULE_ID' => $this->module->getId(),
                'NAME' => $variant
            ]);
            if ($row = $query->Fetch()) {
                $result = $row;
                break;
            }
        }

        return $result;
    }

    /**
     * Возвращает строку для вызова метода callAgent класса через eval
     */
    public static function getAgentCall(string $className, string $method, ?array $arguments = null): string
    {
        return static::getFunctionCall(
            $className,
            'callAgent',
            isset($arguments) ? [$method, $arguments] : [$method]
        );
    }

    /**
     * Возвращает строку для вызова метод класс через eval
     */
    public static function getFunctionCall(string $className, string $method, ?array $arguments = null): string
    {
        $argumentsString = '';

        if (is_array($arguments)) {
            $isFirstArgument = true;

            foreach ($arguments as $argument) {
                if (!$isFirstArgument) {
                    $argumentsString .= ', ';
                }

                $argumentsString .= var_export($argument, true);
                $isFirstArgument = false;
            }
        }

        return $className . '::' . $method . '(' . $argumentsString . ');';
    }

    /**
     * Приводит строку вызова агента к нормализованному ключу.
     *
     * Ключ используется только для внутренних сопоставлений и сравнений,
     * поэтому регистр и перевод строки не имеют значения.
     */
    private function normalizeAgentKey(string $name): string
    {
        $normalized = str_replace(PHP_EOL, '', $name);

        return Str::lower($normalized);
    }

    /**
     * Регистрирует все агенты в базе данных
     *
     * @throws SystemException
     */
    protected function saveAgents(array $agentList, array $registeredList): void
    {
        foreach ($agentList as $agentKey => $agent) {
            static::saveAgent($agent, $registeredList[$agentKey] ?? null);
        }
    }

    /**
     * Регистрируем агент в базе данных
     *
     * @throws SystemException
     */
    protected function saveAgent(array $agent, ?array $registeredAgent): void
    {
        global $APPLICATION;

        $agentData = [
            'NAME' => $agent['name'],
            'MODULE_ID' => $this->module->getId(),
            'SORT' => $agent['sort'],
            'ACTIVE' => 'Y',
            'AGENT_INTERVAL' => $agent['interval'],
            'IS_PERIOD' => 'N',
            'USER_ID' => 0
        ];

        if (!empty($agent['next_exec'])) {
            $agentData['NEXT_EXEC'] = $agent['next_exec'];
        }

        if (!$registeredAgent) {
            $saveResult = CAgent::Add($agentData);
        } else {
            if (!static::isNeedUpdateAgent($agentData, $registeredAgent, $agent['update'])) {
                $saveResult = true;
            } else {
                $updateData = array_diff_key($agentData, ['ACTIVE' => true]);
                $saveResult = CAgent::Update($registeredAgent['ID'], $updateData);
            }
        }

        if (!$saveResult) {
            $exception = $APPLICATION->GetException();

            throw new SystemException(
                'agent '
                . $agent['name']
                . ' register error'
                . ($exception ? ': ' . $exception->GetString() : '')
            );
        }
    }

    protected function isNeedUpdateAgent($agentRow, $registeredRow, $rule = self::UPDATE_RULE_FUTURE): bool
    {
        $result = false;

        if (isset($agentRow['NEXT_EXEC'])) {
            $nextExec = MakeTimeStamp($agentRow['NEXT_EXEC']);
            $scheduledExec = MakeTimeStamp($registeredRow['NEXT_EXEC']);

            switch ($rule) {
                case self::UPDATE_RULE_STRICT:
                    $result = ($nextExec !== $scheduledExec);
                    break;

                case self::UPDATE_RULE_FUTURE:
                default:
                    $result = ($nextExec + $agentRow['AGENT_INTERVAL'] < $scheduledExec);
                    break;
            }
        }

        return $result;
    }

    /**
     * Удаляет неиспользуемые агенты из базы данных
     *
     * @throws SystemException
     */
    protected function deleteAgents(array $agentList, array $registeredList): void
    {
        foreach ($registeredList as $agentKey => $agentRow) {
            if (!isset($agentList[$agentKey])) {
                static::deleteAgent($agentRow);
            }
        }
    }

    /**
     * Удаляет агент из базы данных
     *
     * @throws SystemException
     */
    protected function deleteAgent(array $registeredRow): void
    {
        $deleteResult = CAgent::Delete($registeredRow['ID']);
        if (!$deleteResult) {
            throw new SystemException('agent ' . $registeredRow['NAME'] . ' not deleted');
        }
    }
}
