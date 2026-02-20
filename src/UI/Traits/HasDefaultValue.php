<?php

namespace MB\Bitrix\UI\Traits;

use MB\Support\Conditionable\ConditionTree;

trait HasDefaultValue
{
    protected string $defaultValue = '';

    public function getDefaultValue(): string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(string $defaultValue): static
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public function setDefaultValueByCondition(ConditionTree $conditionTree, $trueDefault = '', $falseDefault = '')
    {
        if (method_exists($this, 'addConditionAction')) {
            $this->addConditionAction(
                $conditionTree,
                fn ($target) => $target->setDefaultValue($trueDefault),
                fn ($target) => $target->setDefaultValue($falseDefault),
            );
        }
    }
}
