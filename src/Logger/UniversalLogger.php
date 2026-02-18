<?php

namespace MB\Bitrix\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Универсальное логирование в журнал событий, системные уведомления и файл.
 */
class UniversalLogger extends AbstractLogger
{
    public const CONTEXT_FIELD_EVENT_MODULE_ID = EventLogger::CONTEXT_FIELD_EVENT_MODULE_ID;
    public const CONTEXT_FIELD_EVENT_TYPE = EventLogger::CONTEXT_FIELD_EVENT_TYPE;
    public const CONTEXT_FIELD_EVENT_ITEM_ID = EventLogger::CONTEXT_FIELD_EVENT_ITEM_ID;
    public const CONTEXT_FIELD_NOTIFICATION_MESSAGE = 'NOTIFICATION_MESSAGE';

    public const PANEL_NOTIFICATION_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
    ];

    public const EVENT_NOTIFICATION_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
    ];

    private FileLogger $fileLogger;
    private EventLogger $eventLogger;
    private NotificationLogger $notificationLogger;

    /**
     * @param string $relativeFileName относительный путь к файлу лога
     * @param string $moduleId идентификатор модуля
     * @param bool $autoDate добавлять дату в путь файла
     * @param string|null $logFolder каталог логов (null = FileLogger::LOG_FOLDER)
     * @param int|null $logFileMaxSize макс. размер файла (null = FileLogger::LOG_FILE_MAX_SIZE)
     */
    public function __construct(
        string $relativeFileName,
        string $moduleId = 'main',
        bool $autoDate = true,
        ?string $logFolder = null,
        ?int $logFileMaxSize = null
    ) {
        $this->fileLogger = new FileLogger($relativeFileName, $autoDate, $logFolder, $logFileMaxSize);
        $this->eventLogger = new EventLogger($moduleId);
        $this->notificationLogger = new NotificationLogger($moduleId);
    }

    public function setEventType(string $eventType): self
    {
        $this->eventLogger->setEventType($eventType);
        $this->notificationLogger->setEventType($eventType);
        return $this;
    }

    public function setModuleId(string $moduleId): self
    {
        $this->eventLogger->setModuleId($moduleId);
        $this->notificationLogger->setModuleId($moduleId);
        return $this;
    }

    /**
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array $context
     */
    public function log($level, $message, array $context = []): void
    {
        $sentEvent = false;
        if (in_array($level, self::EVENT_NOTIFICATION_LEVELS, true)) {
            $eventMessage = $message . "<br><br>";
            $eventMessage .= "Файловый лог по данному событию: {$this->fileLogger->getSiteLogFileName()}";
            $this->eventLogger->log($level, $eventMessage, $context);
            $sentEvent = true;
        }
        if (in_array($level, self::PANEL_NOTIFICATION_LEVELS, true)) {
            $notificationMessage = $context[self::CONTEXT_FIELD_NOTIFICATION_MESSAGE] ?? (string) $message;
            $notificationMessage = trim($notificationMessage);
            if ($sentEvent) {
                if (!in_array(substr($notificationMessage, -1), ['.', '!', '?'], true)) {
                    $notificationMessage .= '.';
                }
                $eventTypeKey = self::CONTEXT_FIELD_EVENT_TYPE;
                $notificationMessage .= " Подробнее в <a href=\"/bitrix/admin/event_log.php?set_filter=Y&find_audit_type[0]={{$eventTypeKey}}\">Журнале</a>";
            }
            $this->notificationLogger->log($level, $notificationMessage, $context);
        }

        $this->fileLogger->log($level, (string) $message, $context);
    }

    public function logToEvent($level, $message, array $context = []): void
    {
        $this->eventLogger->log($level, $message, $context);
    }

    public function logToNotification($level, $message, array $context = []): void
    {
        $this->notificationLogger->log($level, $message, $context);
    }

    public function logToFile($level, $message, array $context = []): void
    {
        $this->fileLogger->log($level, $message, $context);
    }

    public function setLevel(string $level): self
    {
        $this->fileLogger->setLevel($level);
        $this->eventLogger->setLevel($level);
        $this->notificationLogger->setLevel($level);
        return $this;
    }
}

