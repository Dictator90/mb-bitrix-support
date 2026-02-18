<?php

namespace MB\Core\Migration;

use Bitrix\Tasks\Access\Install\Migration;
use MB\Core;
use MB\Core\Migration\Entity\Storage\RegularStorageManager;

class Storage extends Reference\AbstractMigration
{
	public function check(): bool
    {
        return true;
	}

    public function up(): Result
    {
        return RegularStorageManager::create($this->module)->update();
    }

    public function down(): Result
    {
        return RegularStorageManager::create($this->module)->deleteAll();
    }
}
