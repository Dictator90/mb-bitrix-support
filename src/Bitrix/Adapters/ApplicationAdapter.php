<?php

declare(strict_types=1);

namespace MB\Bitrix\Bitrix\Adapters;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MB\Bitrix\Contracts\Bitrix\ApplicationAdapter as ApplicationAdapterContract;

final class ApplicationAdapter implements ApplicationAdapterContract
{
    public function getDocumentRoot(): string
    {
        return rtrim(Loader::getDocumentRoot(), "/\\");
    }

    public function clearManagedFileCache(int $fileId, string $cacheDir, int $bucketSize = 10): void
    {
        if (!defined('CACHED_b_file') || CACHED_b_file === false) {
            return;
        }

        $cache = Application::getInstance()->getManagedCache();
        $bucket = (int) ($fileId / $bucketSize);

        $cache->clean($cacheDir . '01' . $bucket, $cacheDir);
        $cache->clean($cacheDir . '11' . $bucket, $cacheDir);
        $cache->clean($cacheDir . '00' . $bucket, $cacheDir);
        $cache->clean($cacheDir . '10' . $bucket, $cacheDir);
    }
}

