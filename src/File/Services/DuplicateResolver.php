<?php

declare(strict_types=1);

namespace MB\Bitrix\File\Services;

use Bitrix\Main\Config\Option;
use Bitrix\Main\File\Internal\FileHashTable;
use CFile;
use MB\Bitrix\Contracts\File\DuplicateResolver as DuplicateResolverContract;
use MB\Bitrix\Contracts\File\FileRepository as FileRepositoryContract;

final class DuplicateResolver implements DuplicateResolverContract
{
    private readonly FileRepositoryContract $fileRepository;

    public function __construct(?FileRepositoryContract $fileRepository = null)
    {
        $this->fileRepository = $fileRepository ?? new FileRepository();
    }

    public function find(int $fileSize, string $fileHash): ?array
    {
        if ($fileHash === '' || Option::get('main', 'control_file_duplicates', 'N') !== 'Y') {
            return null;
        }

        $duplicate = FileHashTable::getList([
            'filter' => [
                '=FILE_SIZE' => $fileSize,
                '=FILE_HASH' => $fileHash,
            ],
            'select' => ['FILE_ID', 'FILE.*'],
        ])->fetch();

        return is_array($duplicate) ? $duplicate : null;
    }

    /**
     * Регистрирует новый файл поверх уже лежащей на диске копии — так же, как
     * это делает ядро в CFile::SaveFile(): своя строка b_file с теми же SUBDIR
     * и FILE_NAME, без FILE_HASH, плюс запись в b_file_duplicate со счётчиком
     * ссылок. Благодаря счётчику CFile::Delete() сносит физический файл только
     * вместе с последней ссылкой на него.
     *
     * Если оригинал пропал из b_file, возвращается 0 — вызывающий код сохранит
     * файл обычным путём.
     */
    public function resolve(array $duplicate, array $preparedData): int
    {
        $originalId = (int) ($duplicate['FILE_ID'] ?? 0);
        $original = $originalId > 0 ? $this->fileRepository->byId($originalId) : null;

        if ($original === null) {
            return 0;
        }

        $duplicateId = (int) CFile::DoInsert([
            'MODULE_ID' => (string) ($preparedData['MODULE_ID'] ?? ''),
            'HEIGHT' => (int) ($preparedData['HEIGHT'] ?? 0),
            'WIDTH' => (int) ($preparedData['WIDTH'] ?? 0),
            'FILE_SIZE' => (int) round((float) ($preparedData['FILE_SIZE'] ?? 0)),
            'CONTENT_TYPE' => (string) ($preparedData['CONTENT_TYPE'] ?? ''),
            'SUBDIR' => (string) $original['SUBDIR'],
            'FILE_NAME' => (string) $original['FILE_NAME'],
            'ORIGINAL_NAME' => (string) ($preparedData['ORIGINAL_NAME'] ?? ''),
            'DESCRIPTION' => (string) ($preparedData['DESCRIPTION'] ?? ''),
            'HANDLER_ID' => (string) ($preparedData['HANDLER_ID'] ?? ''),
            'EXTERNAL_ID' => (string) ($preparedData['EXTERNAL_ID'] ?? ''),
            'FILE_HASH' => '',
        ]);

        if ($duplicateId <= 0) {
            return 0;
        }

        CFile::AddDuplicate($originalId, $duplicateId, false);
        CFile::CleanCache($duplicateId);

        return $duplicateId;
    }
}
