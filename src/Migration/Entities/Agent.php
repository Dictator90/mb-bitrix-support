<?php

declare(strict_types=1);

namespace MB\Bitrix\Migration\Entities;

use MB\Bitrix\Agent\AgentManager;
use MB\Bitrix\Migration\BaseEntity;
use MB\Bitrix\Migration\Result;

class Agent extends BaseEntity
{
    public function up(): Result
    {
        $this->down();
        return AgentManager::create($this->module)->update();
    }

    public function down(): Result
    {
        return AgentManager::create($this->module)->deleteAll();
    }

    public function check(): bool
    {
        return false;
    }
}
