<?php

namespace MB\Bitrix\UI\Traits;

use MB\Support\Conditionable\ConditionTree;

trait HasActive
{
    protected bool $active = false;

    public function configureActive(bool $value = true): static
    {
        $this->active = $value;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function toggleActiveByCondition(ConditionTree $conditionTree)
    {
        if (method_exists($this, 'addConditionAction')) {
            $this->addConditionAction(
                $conditionTree,
                fn ($target) => $target->configureActive(),
                fn ($target) => $target->configureActive(false),
            );
        }
    }
}
