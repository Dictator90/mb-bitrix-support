<?php

namespace MB\Bitrix\Migration\Entities;

use MB\Bitrix\Agent\AgentManager;
use MB\Bitrix\Migration\Result;
use MB\Bitrix\Migration\BaseEntity;

class Agent extends BaseEntity
{
    /**
     *
     * @deprecated Need refactor
     * @return Result
     * @throws \Bitrix\Main\NotImplementedException
     * @throws \Bitrix\Main\SystemException
     */
    public function up(): Result
    {
        $this->down();
        return AgentManager::create($this->module)->update();
    }

    /**
     * @deprecated Need refactor
     * @return Result
     * @throws \Bitrix\Main\SystemException
     */
    public function down(): Result
    {
        return AgentManager::create($this->module)->deleteAll();
    }

    public function check(): bool
    {
        return false;
    }
}
