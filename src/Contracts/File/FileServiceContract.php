<?php

declare(strict_types=1);

namespace MB\Bitrix\Contracts\File;

use Bitrix\Main\ORM\Query\Result;

/**
 * Контракт сервиса файлов Bitrix (b_file / D7 FileTable).
 *
 * Разрешение: {@see \app('file.service')} или {@see \MB\Bitrix\File\FileService::resolve()}.
 */
interface FileServiceContract
{
    public function save(
        array $fileData,
        string $savePath,
        bool $forceRandom = false,
        bool $skipExtension = false,
        string $dirAdd = '',
    ): ?int;

    public function saveFile(
        array $fileData,
        string $savePath,
        bool $forceRandom = false,
        bool $skipExtension = false,
        string $dirAdd = '',
    ): ?int;

    /**
     * @return array<int|string, array{success: bool, fileId?: ?int, fileData?: ?array, error?: string}>
     */
    public function saveFiles(array $filesData, string $savePath, bool $forceRandom = false): array;

    public function getFileData(int $fileId): ?array;

    /**
     * @param list<int> $fileIds
     * @return array<int, ?array>
     */
    public function getFilesData(array $fileIds): array;

    /**
     * @param array<string, mixed> $filter
     * @param array<string, string> $order
     */
    public function getFilesByFilter(
        array $filter = [],
        array $order = ['ID' => 'DESC'],
        int $limit = 50,
        int $offset = 0,
    ): Result;

    public function updateDescription(int $fileId, string $description): bool;

    public function deleteFile(int $fileId): bool;

    public function getFilePath(int $fileId): ?string;

    public function getFilePathFromArray(array $file): ?string;

    public function makeFileArray(mixed $file, string $source = '', ?string $site = null): ?array;

    public function formatSize(int $size, int $precision = 2): string;

    public function isImage(string $filename, string $mimeType = ''): bool;

    public function getContentType(string $path): string;
}
