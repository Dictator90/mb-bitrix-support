<?php

declare(strict_types=1);

namespace MB\Bitrix\Support\Facades;

use MB\Bitrix\Support\Facade;
use MB\Filesystem\Contracts\Filesystem as FilesystemContract;
use MB\Filesystem\Nodes\Directory;
use MB\Filesystem\Nodes\File;

/**
 * @method static bool exists(string $path)
 * @method static string get(string $path)
 * @method static mixed require(string $path)
 * @method static array glob(string $pattern, int $flags = 0)
 * @method static string extension(string $path)
 * @method static string basename(string $path)
 * @method static string dirname(string $path)
 * @method static string filename(string $path)
 * @method static bool isFile(string $path)
 * @method static bool isDirectory(string $path)
 * @method static void put(string $path, string $contents)
 * @method static void putJson(string $path, array $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
 * @method static array|object json(string $path, bool $assoc = true, array|object|null $default = null)
 * @method static string content(string $path, ?string $default = null)
 * @method static void updateContent(File|string $fileOrPath, callable $updater, bool $atomic = true)
 * @method static File file(string $path)
 * @method static Directory directory(string $path)
 * @method static void delete(string|array $paths)
 * @method static void move(string $from, string $to)
 * @method static void copy(string $from, string $to)
 * @method static void makeDirectory(string $path, int $mode = 0755, bool $recursive = true)
 * @method static void chmod(string $path, int $mode)
 * @method static void touch(string $path, ?int $mtime = null)
 * @method static void deleteDirectory(string $directory, bool $recursive = false)
 * @method static array files(string $directory, bool $recursive = false)
 * @method static array directories(string $directory, bool $recursive = false)
 *
 * Thin static facade over the container-bound filesystem service.
 *
 * This is primarily intended for simple scripts, views and glue code.
 * In domain services prefer injecting {@see FilesystemContract}.
 */
class Filesystem extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return 'filesystem';
    }
}

