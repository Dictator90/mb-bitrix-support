<?php

namespace MB\Bitrix\UI\Traits;

use MB\Support\Conditionable\ConditionTree;

trait HasRequired
{
    protected bool $required = false;

    public function configureRequired(bool $value = true): static
    {
        $this->required = $value;
        return $this;
    }

    public function isRequired()
    {
        return $this->required;
    }

    protected function getRequired(): string
    {
        return $this->isRequired() ? 'required="required"' : '';
    }

    public function toggleRequiredByCondition(ConditionTree $conditionTree)
    {
        if (method_exists($this, 'addConditionAction')) {
            $this->addConditionAction(
                $conditionTree,
                fn ($target) => $target->configureRequired(),
                fn ($target) => $target->configureRequired(false),
            );
        }
    }
}
