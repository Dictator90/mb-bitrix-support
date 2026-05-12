<?php

declare(strict_types=1);

namespace MB\Bitrix\File\Services;

use Bitrix\Main\Config\Option;
use Bitrix\Main\File\Internal\FileHashTable;
use MB\Bitrix\Contracts\File\DuplicateResolver as DuplicateResolverContract;

final class DuplicateResolver implements DuplicateResolverContract
{
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

    public function resolve(array $duplicate, array $preparedData): int
    {
        return (int) $duplicate['FILE_ID'];
    }
}

