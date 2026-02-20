<?php

namespace MB\Bitrix\Logger;

use Bitrix\Main\Diag\Logger;
use CEventLog;
use Psr\Log\LogLevel;

/**
 * Логирование в журнал событий (/bitrix/admin/event_log.php)
 */
class EventLogger extends Logger
{
    public const CONTEXT_FIELD_EVENT_MODULE_ID = 'EVENT_MODULE_ID';
    public const CONTEXT_FIELD_EVENT_TYPE = 'EVENT_TYPE';
    public const CONTEXT_FIELD_EVENT_ITEM_ID = 'EVENT_ITEM_ID';

    protected const LEVELS_MAP = [
        LogLevel::EMERGENCY => CEventLog::SEVERITY_EMERGENCY,
        LogLevel::ALERT => CEventLog::SEVERITY_ALERT,
        LogLevel::CRITICAL => CEventLog::SEVERITY_CRITICAL,
        LogLevel::ERROR => CEventLog::SEVERITY_ERROR,
        LogLevel::WARNING => CEventLog::SEVERITY_WARNING,
        LogLevel::NOTICE => CEventLog::SEVERITY_NOTICE,
        LogLevel::INFO => CEventLog::SEVERITY_INFO,
        LogLevel::DEBUG => CEventLog::SEVERITY_DEBUG,
    ];
    protected const DEFAULT_LEVEL = 'UNKNOWN';
    public const DEFAULT_EVENT_TYPE = 'MAIN_MISC';
    public const DEFAULT_MODULE_ID = 'main';

    protected string $eventType;
    protected string $eventModuleId;
    protected string $eventItemId;
    protected string $defaultEventType = self::DEFAULT_EVENT_TYPE;
    protected string $defaultEventModuleId = self::DEFAULT_MODULE_ID;

    public function __construct(?string $moduleId, string $eventType = self::DEFAULT_EVENT_TYPE)
    {
        $this->setModuleId($moduleId ?? self::DEFAULT_MODULE_ID);
        $this->setEventType($eventType);
    }

    public function setEventType(string $eventType): self
    {
        $this->defaultEventType = $eventType;
        return $this;
    }

    public function setModuleId(string $moduleId): self
    {
        $this->defaultEventModuleId = $moduleId;
        return $this;
    }

    protected function logMessage(string $level, string $message): void
    {
        $eventLevel = self::LogLevelToSeverity($level);
        CEventLog::Add([
            'SEVERITY' => $eventLevel,
            'AUDIT_TYPE_ID' => $this->eventType,
            'MODULE_ID' => $this->eventModuleId,
            'ITEM_ID' => $this->eventItemId,
            'DESCRIPTION' => $message,
        ]);
    }

    public static function LogLevelToSeverity(string $level): string
    {
        return self::LEVELS_MAP[$level] ?? self::DEFAULT_LEVEL;
    }

    protected function interpolate(): string
    {
        $this->eventItemId = '';
        $this->eventType = $this->defaultEventType;
        $this->eventModuleId = $this->defaultEventModuleId;

        if (isset($this->context['ITEM_ID'])) {
            $this->eventItemId = (string) $this->context['ITEM_ID'];
        }
        if (isset($this->context['ID'])) {
            $this->eventItemId = (string) $this->context['ID'];
        }
        if (isset($this->context[self::CONTEXT_FIELD_EVENT_ITEM_ID])) {
            $this->eventItemId = (string) $this->context[self::CONTEXT_FIELD_EVENT_ITEM_ID];
        } else {
            $this->context[self::CONTEXT_FIELD_EVENT_ITEM_ID] = $this->eventItemId;
        }

        if (isset($this->context[self::CONTEXT_FIELD_EVENT_MODULE_ID])) {
            $this->eventModuleId = (string) $this->context[self::CONTEXT_FIELD_EVENT_MODULE_ID];
        } else {
            $this->context[self::CONTEXT_FIELD_EVENT_MODULE_ID] = $this->eventModuleId;
        }

        if (isset($this->context[self::CONTEXT_FIELD_EVENT_TYPE])) {
            $this->eventType = (string) $this->context[self::CONTEXT_FIELD_EVENT_TYPE];
        } else {
            $this->context[self::CONTEXT_FIELD_EVENT_TYPE] = $this->eventType;
        }

        return parent::interpolate();
    }
}

