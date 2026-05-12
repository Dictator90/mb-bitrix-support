<?php

declare(strict_types=1);

/**
 * Minimal Bitrix\Main\Application stub so {@see \MB\Bitrix\Foundation\Application}
 * can resolve path.* bindings in unit tests (load before Composer autoload).
 */
namespace Bitrix\Main {
    if (! class_exists(Application::class, false)) {
        class Application
        {
            public static function getInstance(): self
            {
                return new self();
            }

            public static function getDocumentRoot(): string
            {
                return sys_get_temp_dir();
            }

            public function getManagedCache(): object
            {
                return new class {
                    public function clean(string $key, string $dir): void {}
                };
            }
        }
    }
}
