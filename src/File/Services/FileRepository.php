<?php

declare(strict_types=1);

namespace MB\Bitrix\File\Services;

use Bitrix\Main\File\Internal\FileDuplicateTable;
use Bitrix\Main\File\Internal\FileHashTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\Type\DateTime as BitrixDateTime;
use MB\Bitrix\Contracts\File\FileRepository as FileRepositoryContract;

final class FileRepository implements FileRepositoryContract
{
    public function byId(int $fileId): ?array
    {
        $file = FileTable::getById($fileId)->fetch();

        return is_array($file) ? $file : null;
    }

    public function byIds(array $fileIds): array
    {
        if ($fileIds === []) {
            return [];
        }

        return FileTable::query()
            ->setSelect(['*', 'HASH.*'])
            ->whereIn('ID', $fileIds)
            ->fetchAll();
    }

    public function byFilter(array $filter, array $order, int $limit, int $offset): Result
    {
        $query = FileTable::query()
            ->setSelect(['*', 'HASH.*'])
            ->setOrder($order)
            ->setLimit($limit)
            ->setOffset($offset);

        if ($filter !== []) {
            $query->setFilter($filter);
        }

        return $query->exec();
    }

    public function insert(array $fields): int
    {
        $add = FileTable::add($fields);
        if (!$add->isSuccess()) {
            throw new \Bitrix\Main\SystemException(implode('; ', $add->getErrorMessages()));
        }

        $id = (int) $add->getId();
        if ($id <= 0) {
            throw new \Bitrix\Main\SystemException('Unable to insert into b_file');
        }

        return $id;
    }

    public function updateDescription(int $fileId, string $description): bool
    {
        $update = FileTable::update($fileId, [
            'DESCRIPTION' => $description,
            'TIMESTAMP_X' => new BitrixDateTime(),
        ]);

        return $update->isSuccess();
    }

    public function delete(int $fileId): bool
    {
        return FileTable::delete($fileId)->isSuccess();
    }

    public function addHash(int $fileId, int $fileSize, string $fileHash): void
    {
        if ($fileHash === '') {
            return;
        }

        FileHashTable::add([
            'FILE_ID' => $fileId,
            'FILE_SIZE' => $fileSize,
            'FILE_HASH' => $fileHash,
        ]);
    }

    public function markDeletedDuplicate(int $fileId): void
    {
        FileDuplicateTable::markDeleted($fileId);
        FileHashTable::delete($fileId);
    }
}

