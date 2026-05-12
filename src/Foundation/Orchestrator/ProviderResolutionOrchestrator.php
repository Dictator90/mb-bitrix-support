<?php

declare(strict_types=1);

namespace MB\Bitrix\Foundation\Orchestrator;

use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Foundation\ServiceProvider;

final class ProviderResolutionOrchestrator
{
    /**
     * @param array<string, ServiceProvider> $serviceProviders
     */
    public function getProvider(array $serviceProviders, ServiceProvider|string $provider): ?ServiceProvider
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return $serviceProviders[$name] ?? null;
    }

    /**
     * @param array<string, ServiceProvider> $serviceProviders
     * @return array<int, ServiceProvider>
     */
    public function getProviders(array $serviceProviders, ServiceProvider|string $provider): array
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return array_values(array_filter($serviceProviders, fn (ServiceProvider $value): bool => $value instanceof $name));
    }

    public function resolveProvider(Application $app, string $provider): ServiceProvider
    {
        $instance = new $provider($app);
        if (!$instance instanceof ServiceProvider) {
            throw new \InvalidArgumentException(sprintf('Provider `%s` must extend %s.', $provider, ServiceProvider::class));
        }

        return $instance;
    }

    /**
     * @param array<string, ServiceProvider> $serviceProviders
     * @param array<string, bool> $loadedProviders
     */
    public function markAsRegistered(array &$serviceProviders, array &$loadedProviders, ServiceProvider $provider): void
    {
        $class = get_class($provider);
        $serviceProviders[$class] = $provider;
        $loadedProviders[$class] = true;
    }
}
