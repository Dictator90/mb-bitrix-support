<?php

declare(strict_types=1);

namespace MB\Bitrix\Foundation;

use Composer\InstalledVersions;
use ReflectionClass;

/**
 * Reads installed composer packages' `extra.mb.providers` from
 * `vendor/composer/installed.json` so the kernel can auto-register the service
 * providers of satellite mb4it packages (console, migration, …).
 */
final class PackageManifest
{
    private static ?self $instance = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $providers = null;

    public static function create(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @return array<int, string>
     */
    public function providers(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $providers = [];

        foreach ($this->packages() as $package) {
            $list = $package['extra']['mb']['providers'] ?? [];

            if (is_array($list)) {
                foreach ($list as $provider) {
                    if (is_string($provider) && $provider !== '') {
                        $providers[] = $provider;
                    }
                }
            }
        }

        return $this->providers = array_values(array_unique($providers));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function packages(): array
    {
        $file = $this->installedJsonPath();

        if ($file === null || ! is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (! is_array($data)) {
            return [];
        }

        return $data['packages'] ?? $data;
    }

    private function installedJsonPath(): ?string
    {
        if (! class_exists(InstalledVersions::class)) {
            return null;
        }

        $file = (new ReflectionClass(InstalledVersions::class))->getFileName();

        return $file === false ? null : dirname($file) . '/installed.json';
    }
}
