<?php

declare(strict_types=1);

/**
 * Minimal symbols for PHPStan when analysing Config / Foundation / Module (no full Bitrix).
 */
namespace Bitrix\Main {
    class Error
    {
        public function __construct(
            public string $message = '',
            public string|int $code = '',
            public array $customData = []
        ) {}

        public function getMessage(): string
        {
            return $this->message;
        }

        public function getCode(): string|int
        {
            return $this->code;
        }
    }

    class Result
    {
        /** @var list<Error> */
        protected array $errors = [];

        protected mixed $data = null;

        public function addError(Error $error): static
        {
            $this->errors[] = $error;

            return $this;
        }

        /**
         * @param list<Error> $errors
         */
        public function addErrors(array $errors): static
        {
            foreach ($errors as $error) {
                $this->errors[] = $error;
            }

            return $this;
        }

        public function setData(mixed $data): static
        {
            $this->data = $data;

            return $this;
        }

        public function getData(): mixed
        {
            return $this->data;
        }

        /**
         * @return list<Error>
         */
        public function getErrors(): array
        {
            return $this->errors;
        }

        public function isSuccess(): bool
        {
            return $this->errors === [];
        }
    }

    class Context
    {
        public static function getCurrent(): self
        {
            return new self();
        }

        public function getSite(): string
        {
            return 's1';
        }
    }

    class HttpApplication
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function getContext(): Context
        {
            return new Context();
        }
    }

    class ModuleManager
    {
        public static function isModuleInstalled(string $name): bool
        {
            return false;
        }

        /**
         * @return list<string>
         */
        public static function getInstalledModules(): array
        {
            return [];
        }
    }

    class Loader
    {
        public const LOCAL_HOLDER = 'local';

        public const BITRIX_HOLDER = 'bitrix';

        public static function includeModule(string $name): bool
        {
            return false;
        }

        public static function getDocumentRoot(): string
        {
            return '';
        }
    }

    class Localization
    {
    }
}

namespace Bitrix\Main\Localization {
    class Loc
    {
        public static function loadMessages(string $path): void {}

        public static function getMessage(string $code): ?string
        {
            return null;
        }
    }
}

namespace Bitrix\Main\Config {
    class Option
    {
        public static function get(string $moduleId, string $name, mixed $default = false, mixed $siteId = false): mixed
        {
            return $default;
        }

        public static function set(string $moduleId, string $name, string $value, string $siteId = ''): void {}

        /**
         * @return array<string, string>
         */
        public static function getForModule(string $moduleId, string $siteId = ''): array
        {
            return [];
        }

        /**
         * @param array{name: string, site_id?: string} $filter
         */
        public static function delete(string $moduleId, array $filter): void {}
    }
}

namespace {
    if (! defined('LANGUAGE_ID')) {
        define('LANGUAGE_ID', 'ru');
    }

    if (! function_exists('message')) {
        function message(string $code, ?array $replace = null, ?string $lang = null): ?string
        {
            return null;
        }
    }

    if (! class_exists('CDiskQuota', false)) {
        class CDiskQuota
        {
            /**
             * @param array<string,mixed> $fileData
             */
            public function checkDiskQuota(array $fileData): bool
            {
                return true;
            }

            public static function updateDiskQuota(string $type, int $size, string $action): void
            {
            }
        }
    }

    if (! class_exists(\CBitrixComponent::class, false)) {
        class CBitrixComponent
        {
            /** @var array<string,mixed> */
            protected array $arParams = [];

            public function __construct(mixed $component = null)
            {
            }

            protected function __showError(string $message, int|string $code = 0): void
            {
            }
        }
    }
}

namespace Bitrix\UI\EntitySelector {
    if (! class_exists(BaseProvider::class, false)) {
        class BaseProvider
        {
            public function __construct(mixed ...$args)
            {
            }
        }
    }
}
