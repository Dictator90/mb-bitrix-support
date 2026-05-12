<?php

declare(strict_types=1);

namespace MB\Bitrix\Contracts\File;

interface DuplicateResolver
{
    /**
     * @return array<string,mixed>|null
     */
    public function find(int $fileSize, string $fileHash): ?array;

    /**
     * @param array<string,mixed> $duplicate
     * @param array<string,mixed> $preparedData
     */
    public function resolve(array $duplicate, array $preparedData): int;
}

