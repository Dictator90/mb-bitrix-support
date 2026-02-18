<?php

namespace MB\Bitrix\Migration\Entities;

use MB\Bitrix\Event\EventManager;
use MB\Bitrix\Migration\Result;
use MB\Bitrix\Migration\BaseEntity;

class Event extends BaseEntity
{
    /**
     * Метод проверки актуальной версии модуля и версии указанной в таблице b_option
     *
     * @return bool
     */
    public function check(): bool
    {
        return true;
    }

    public function up(): Result
    {
        $result = $this->down();
        if ($result->isSuccess()) {
            return EventManager::create($this->module)->update();
        }

        return $result;
    }

    public function down(): Result
    {
        return EventManager::create($this->module)->deleteAll();
    }
}
