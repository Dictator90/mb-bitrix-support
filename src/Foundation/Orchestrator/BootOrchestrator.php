<?php

declare(strict_types=1);

namespace MB\Bitrix\Foundation\Orchestrator;

use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Foundation\ServiceProvider;

final class BootOrchestrator
{
    /**
     * @param array<int, callable> $bootingCallbacks
     * @param array<string, ServiceProvider> $serviceProviders
     * @param array<int, callable> $bootedCallbacks
     */
    public function run(
        Application $app,
        array &$bootingCallbacks,
        array $serviceProviders,
        array &$bootedCallbacks
    ): void {
        $app->dispatchKernelLifecycleEvent(Application::ON_BEFORE_BOOT_KERNEL_APPLICATION_EVENT);

        $this->fireCallbacks($app, $bootingCallbacks);

        foreach ($serviceProviders as $provider) {
            $app->bootRegisteredProvider($provider);
        }

        $app->markBooted();
        $this->fireCallbacks($app, $bootedCallbacks);

        $app->dispatchKernelLifecycleEvent(Application::ON_AFTER_BOOT_KERNEL_APPLICATION_EVENT);
    }

    /**
     * @param array<int, callable> $callbacks
     */
    private function fireCallbacks(Application $app, array &$callbacks): void
    {
        $index = 0;

        while ($index < count($callbacks)) {
            $callbacks[$index]($app);
            $index++;
        }
    }
}
