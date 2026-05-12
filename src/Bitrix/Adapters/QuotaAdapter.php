<?php

declare(strict_types=1);

namespace MB\Bitrix\Bitrix\Adapters;

use Bitrix\Main\Config\Option;
use MB\Bitrix\Contracts\Bitrix\QuotaAdapter as QuotaAdapterContract;

final class QuotaAdapter implements QuotaAdapterContract
{
    public function checkUpload(array $fileData): bool
    {
        if (Option::get('main', 'disk_space') <= 0) {
            return true;
        }

        return (new \CDiskQuota())->checkDiskQuota($fileData);
    }

    public function notifyInsert(int $fileSize): void
    {
        if (Option::get('main', 'disk_space') <= 0) {
            return;
        }

        \CDiskQuota::updateDiskQuota('file', $fileSize, 'insert');
    }

    public function notifyDelete(int $fileSize): void
    {
        if (Option::get('main', 'disk_space') <= 0) {
            return;
        }

        \CDiskQuota::updateDiskQuota('file', $fileSize, 'delete');
    }
}

