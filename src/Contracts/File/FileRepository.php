<?php

declare(strict_types=1);

namespace MB\Bitrix\Contracts\File;

use Bitrix\Main\ORM\Query\Result;

interface FileRepository
{
    /**
     * @return array<string,mixed>|null
     */
    public function byId(int $fileId): ?array;

    /**
     * @param list<int> $fileIds
     * @return array<int, array<string,mixed>>
     */
    public function byIds(array $fileIds): array;

    /**
     * @param array<string,mixed> $filter
     * @param array<string,string> $order
     */
    public function byFilter(array $filter, array $order, int $limit, int $offset): Result;

    /**
     * @param array<string,mixed> $fields
     */
    public function insert(array $fields): int;

    public function updateDescription(int $fileId, string $description): bool;

    public function delete(int $fileId): bool;

    public function addHash(int $fileId, int $fileSize, string $fileHash): void;

    public function markDeletedDuplicate(int $fileId): void;
}
