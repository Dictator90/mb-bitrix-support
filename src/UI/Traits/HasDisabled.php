<?php

namespace MB\Bitrix\UI\Traits;

use MB\Support\Conditionable\ConditionTree;

trait HasDisabled
{
    protected bool $disabled = false;

    public function configureDisabled(bool $value = true): static
    {
        $this->disabled = $value;
        return $this;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    protected function getDisabled(): string
    {
        return $this->isDisabled() ? 'disabled="disabled"' : '';
    }

    public function toggleDisabledByCondition(ConditionTree $conditionTree)
    {
        if (method_exists($this, 'addConditionAction')) {
            $this->addConditionAction(
                $conditionTree,
                fn ($target) => $target->configureDisabled(),
                fn ($target) => $target->configureDisabled(false),
            );
        }
        return $this;
    }
}
