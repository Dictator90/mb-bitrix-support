<?php

declare(strict_types=1);

namespace MB\Bitrix\Logger;

use MB\Bitrix\Config\Entity;
use MB\Bitrix\Contracts\Log\LoggerFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Default factory for Bitrix loggers.
 */
final class LoggerFactory implements LoggerFactoryInterface
{
    public function __construct(protected ?string $moduleId = null)
    {}

    public function file(string $fileName = '{level}_{date}.log', bool $daily = true): LoggerInterface
    {
        $basePath = '/local/log/';
        /** @var Entity $config */
        $config = $this->moduleId ? app("$this->moduleId:config"): null;
        if ($config) {
            $basePath = $config->get('log_path') ?: $basePath;
        }

        return $daily ? \MB\Logger\LoggerFactory::daily($basePath, $fileName) : \MB\Logger\LoggerFactory::single($basePath, '__debug.log');
    }

    public function event(string $event = EventLogger::DEFAULT_EVENT_TYPE): EventLogger
    {
        $logger = new EventLogger($this->moduleId);
        $logger->setEventType($event);

        return $logger;
    }

    public function notification(string $event = NotificationLogger::DEFAULT_EVENT_TYPE): NotificationLogger
    {
        $logger = new NotificationLogger($this->moduleId);
        $logger->setEventType($event);

        return $logger;
    }

    public function universal(bool $daily = true): UniversalLogger
    {
        return new UniversalLogger($this->moduleId, $daily);
    }
}

