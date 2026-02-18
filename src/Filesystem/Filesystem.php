<?php

declare(strict_types=1);

namespace MB\Bitrix\Filesystem;

use Bitrix\Main\Application;
use MB\Filesystem\Filesystem as NativeFilesystem;
use MB\Filesystem\Finder\PhpClassFinder;

/**
 * Bitrix-oriented facade around mb4it/filesystem.
 *
 * Provides a lazily created singleton instance that can be reused across
 * the project and swapped in tests.
 */
final class Filesystem
{
    private static ?NativeFilesystem $instance = null;

    /**
     * Get the shared filesystem instance.
     */
    public static function instance(): NativeFilesystem
    {
        if (self::$instance === null) {
            $basePath = Application::getDocumentRoot();
            self::$instance = new NativeFilesystem($basePath);
        }

        return self::$instance;
    }

    public static function classFinder()
    {
        return new PhpClassFinder(self::instance());
    }
    /**
     * Override the shared instance (useful for testing).
     */
    public static function setInstance(NativeFilesystem $filesystem): void
    {
        self::$instance = $filesystem;
    }
}

