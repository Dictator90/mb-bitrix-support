<?php

declare(strict_types=1);

namespace MB\Bitrix\Logger;

final class ModuleLoggerFactory
{
    public function make(?string $moduleId = null): LoggerFactory
    {
        return new LoggerFactory($moduleId);
    }
}
