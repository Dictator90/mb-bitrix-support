<?php

declare(strict_types=1);

namespace MB\Bitrix\Contracts\Bitrix;

interface QuotaAdapter
{
    /**
     * @param array<string,mixed> $fileData
     */
    public function checkUpload(array $fileData): bool;

    public function notifyInsert(int $fileSize): void;

    public function notifyDelete(int $fileSize): void;
}

