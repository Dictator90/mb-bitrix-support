<?php

declare(strict_types=1);

namespace MB\Bitrix\Contracts\Bitrix;

interface ApplicationAdapter
{
    public function getDocumentRoot(): string;

    public function clearManagedFileCache(int $fileId, string $cacheDir, int $bucketSize = 10): void;
}

