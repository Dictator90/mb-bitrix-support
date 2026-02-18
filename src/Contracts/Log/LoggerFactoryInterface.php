<?php

namespace MB\Bitrix\Contracts\Log;

use MB\Bitrix\Logger\EventLogger;
use MB\Bitrix\Logger\FileLogger;
use MB\Bitrix\Logger\NotificationLogger;
use MB\Bitrix\Logger\UniversalLogger;

interface LoggerFactoryInterface
{
    public function file(string $relativeFileName, bool $autoDate = true, ?string $logFolder = null, ?int $logFileMaxSize = null): FileLogger;

    public function event(string $event = EventLogger::DEFAULT_EVENT_TYPE): EventLogger;

    public function notification(string $event = NotificationLogger::DEFAULT_EVENT_TYPE): NotificationLogger;

    public function universal(string $relativeFileName, bool $autoDate = true, ?string $logFolder = null, ?int $logFileMaxSize = null): UniversalLogger;
}
