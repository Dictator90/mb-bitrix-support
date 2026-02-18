<?php

declare(strict_types=1);

namespace MB\Bitrix\Logger;

use MB\Bitrix\Contracts\Log\LoggerFactoryInterface;

/**
 * Default factory for Bitrix loggers.
 */
final class LoggerFactory implements LoggerFactoryInterface
{
    public function file(string $relativeFileName, bool $autoDate = true, ?string $logFolder = null, ?int $logFileMaxSize = null): FileLogger
    {
        return new FileLogger($relativeFileName, $autoDate, $logFolder, $logFileMaxSize);
    }

    public function event(string $event = EventLogger::DEFAULT_EVENT_TYPE): EventLogger
    {
        $logger = new EventLogger();
        $logger->setEventType($event);

        return $logger;
    }

    public function notification(string $event = NotificationLogger::DEFAULT_EVENT_TYPE): NotificationLogger
    {
        $logger = new NotificationLogger();
        $logger->setEventType($event);

        return $logger;
    }

    public function universal(string $relativeFileName, bool $autoDate = true, ?string $logFolder = null, ?int $logFileMaxSize = null): UniversalLogger
    {
        return new UniversalLogger($relativeFileName, 'main', $autoDate, $logFolder, $logFileMaxSize);
    }
}

