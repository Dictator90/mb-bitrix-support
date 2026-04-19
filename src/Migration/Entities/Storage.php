<?php

declare(strict_types=1);

namespace MB\Bitrix\Migration\Entities;

use MB\Bitrix\Migration\BaseEntity;
use MB\Bitrix\Migration\Result;
use MB\Bitrix\Migration\StorageEntityManager;

class Storage extends BaseEntity
{
    public function check(): bool
    {
        return true;
    }

    public function up(): Result
    {
        return StorageEntityManager::create($this->module)->update();
    }

    public function down(): Result
    {
        return StorageEntityManager::create($this->module)->deleteAll();
    }
}
