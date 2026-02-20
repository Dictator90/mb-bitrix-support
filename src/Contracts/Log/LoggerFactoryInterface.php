<?php

namespace MB\Bitrix\Contracts\Log;

use MB\Bitrix\Logger\EventLogger;
use MB\Bitrix\Logger\NotificationLogger;
use MB\Bitrix\Logger\UniversalLogger;
use Psr\Log\LoggerInterface;

interface LoggerFactoryInterface
{
    public function file(string $fileName, bool $daily = true): LoggerInterface;

    public function event(string $event = EventLogger::DEFAULT_EVENT_TYPE): EventLogger;

    public function notification(string $event = NotificationLogger::DEFAULT_EVENT_TYPE): NotificationLogger;

    public function universal(bool $daily = true): UniversalLogger;
}
