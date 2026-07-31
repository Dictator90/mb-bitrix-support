<?php

declare(strict_types=1);

namespace MB\Bitrix\File\Services;

use Bitrix\Main\File\Internal\FileDuplicateTable;
use Bitrix\Main\File\Internal\FileHashTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\SystemException;
use CFile;
use MB\Bitrix\Contracts\File\FileRepository as FileRepositoryContract;

final class FileRepository implements FileRepositoryContract
{
    private const INSERT_FIELDS = [
        'MODULE_ID' => '',
        'HEIGHT' => 0,
        'WIDTH' => 0,
        'FILE_SIZE' => 0,
        'CONTENT_TYPE' => '',
        'SUBDIR' => '',
        'FILE_NAME' => '',
        'ORIGINAL_NAME' => '',
        'DESCRIPTION' => '',
        'HANDLER_ID' => '',
        'EXTERNAL_ID' => '',
        'FILE_HASH' => '',
    ];

    /**
     * @phpstan-impure строка b_file меняется между вызовами (запись/удаление файла).
     */
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

    /**
     * Пишет строку b_file через CFile::DoInsert(): ORM-запись ядро запрещает
     * ({@see \Bitrix\Main\FileTable::add()} бросает NotImplementedException).
     * Недостающие ключи добиваются дефолтами — DoInsert читает их без проверок.
     * Переданный FILE_HASH ядро сохранит само — тогда {@see addHash()} не нужен.
     */
    public function insert(array $fields): int
    {
        $fields = array_intersect_key($fields, self::INSERT_FIELDS) + self::INSERT_FIELDS;

        if ((string) $fields['FILE_NAME'] === '' || (string) $fields['SUBDIR'] === '') {
            throw new SystemException('Unable to insert into b_file: FILE_NAME and SUBDIR are required');
        }

        $id = (int) CFile::DoInsert($fields);
        if ($id <= 0) {
            throw new SystemException('Unable to insert into b_file');
        }

        return $id;
    }

    public function updateDescription(int $fileId, string $description): bool
    {
        if ($this->byId($fileId) === null) {
            return false;
        }

        CFile::UpdateDesc($fileId, $description);

        return true;
    }

    /**
     * Удаляет файл целиком (физическая копия, ресайзы, хэш, дубликаты, кэш) —
     * всё это берёт на себя CFile::Delete(). Файл, на который ссылаются
     * дубликаты, ядро помечает удалённым и сносит вместе с последней ссылкой.
     * false — файла с таким ID нет.
     */
    public function delete(int $fileId): bool
    {
        if ($this->byId($fileId) === null) {
            return false;
        }

        CFile::Delete($fileId);

        return true;
    }

    /**
     * Идемпотентно: у b_file_hash первичный ключ — FILE_ID, поэтому повторный
     * вызов (в том числе после {@see insert()} с FILE_HASH) перезаписывает строку,
     * а не падает на дубликате ключа.
     */
    public function addHash(int $fileId, int $fileSize, string $fileHash): void
    {
        if ($fileHash === '') {
            return;
        }

        try {
            FileHashTable::delete($fileId);
        } catch (\Throwable) {
            // строки хэша ещё нет — добавляем ниже
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

