<?php

declare(strict_types=1);

namespace MB\Bitrix\Tests\Support;

use MB\Bitrix\Foundation\Application;

/**
 * Application without base service providers — for testing container, config, and helpers.
 */
final class MinimalKernel extends Application
{
    protected function registerEvents(): void
    {
        // Skip BitrixEventsObservableTrait attach (no Bitrix\Main\Event in unit tests).
    }

    protected function registerBaseServiceProviders(): void
    {
        // Intentionally empty: no Bitrix-tied providers.
    }

    protected function attachEvents(): void
    {
        // Skip kernel notify during tests (no Bitrix event bus).
    }
}
