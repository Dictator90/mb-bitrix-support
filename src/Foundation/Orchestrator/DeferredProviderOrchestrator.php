<?php

declare(strict_types=1);

namespace MB\Bitrix\Foundation\Orchestrator;

use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Foundation\ServiceProvider;

final class DeferredProviderOrchestrator
{
    /**
     * @param array<string, string> $deferredServices
     */
    public function registerDeferred(
        Application $app,
        array &$deferredServices,
        ServiceProvider|string $provider
    ): ServiceProvider {
        if (is_string($provider)) {
            $providerInstance = $app->resolveProviderClass($provider);
            $providerClass = $provider;
        } else {
            $providerInstance = $provider;
            $providerClass = get_class($provider);
        }

        foreach ($providerInstance->provides() as $service) {
            $deferredServices[$service] = $providerClass;
        }

        return $providerInstance;
    }

    /**
     * @param array<string, string> $deferredServices
     * @param array<string, bool> $loadedProviders
     * @param array<string, true> $resolved
     */
    public function loadIfNeeded(
        Application $app,
        array &$deferredServices,
        array &$loadedProviders,
        array $resolved,
        string $abstract
    ): void {
        if (!$this->isDeferredService($deferredServices, $abstract)) {
            return;
        }

        if (isset($resolved[$abstract])) {
            return;
        }

        $this->loadService($app, $deferredServices, $loadedProviders, $abstract);
    }

    /**
     * @param array<string, string> $deferredServices
     * @param array<string, bool> $loadedProviders
     */
    public function loadAll(
        Application $app,
        array &$deferredServices,
        array &$loadedProviders
    ): void {
        foreach (array_keys($deferredServices) as $service) {
            $this->loadService($app, $deferredServices, $loadedProviders, $service);
        }
    }

    /**
     * @param array<string, string> $deferredServices
     * @param array<string, bool> $loadedProviders
     */
    public function loadService(
        Application $app,
        array &$deferredServices,
        array &$loadedProviders,
        string $service
    ): void {
        if (!$this->isDeferredService($deferredServices, $service)) {
            return;
        }

        $provider = $deferredServices[$service];
        if (isset($loadedProviders[$provider])) {
            return;
        }

        unset($deferredServices[$service]);
        $app->registerResolvedProvider($provider);
        $loadedProviders[$provider] = true;
    }

    /**
     * @param array<string, string> $deferredServices
     */
    public function isDeferredService(array $deferredServices, string $service): bool
    {
        return isset($deferredServices[$service]);
    }
}
