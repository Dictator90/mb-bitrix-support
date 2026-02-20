<?php

namespace MB\Bitrix\Logger;

use Bitrix\Main\Diag\Logger;
use CAdminNotify;

/**
 * Логирование в системные уведомления (админка)
 */
class NotificationLogger extends Logger
{
    public const CONTEXT_FIELD_EVENT_MODULE_ID = 'EVENT_MODULE_ID';
    public const CONTEXT_FIELD_EVENT_TYPE = 'EVENT_TYPE';
    public const DEFAULT_EVENT_TYPE = 'MB_CORE_MISC';
    protected const DEFAULT_MODULE_ID = 'main';

    protected string $eventType;
    protected string $eventModuleId;
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
        CAdminNotify::Add([
            'MESSAGE' => $message,
            'TAG' => $this->eventType,
            'MODULE_ID' => $this->eventModuleId,
            'ENABLE_CLOSE' => 'Y',
        ]);
    }

    protected function interpolate(): string
    {
        $this->eventType = $this->defaultEventType;
        $this->eventModuleId = $this->defaultEventModuleId;

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

