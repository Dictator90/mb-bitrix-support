<?php

namespace MB\Bitrix\UI\Traits;

use MB\Support\Conditionable\ConditionTree;

trait HasReadonly
{
    protected bool $readonly = false;

    public function configureReadonly(bool $value = true): static
    {
        $this->readonly = $value;
        return $this;
    }

    public function isReadonly()
    {
        return $this->readonly;
    }

    public function isReadonlyJson()
    {
        return $this->readonly ? 'true' : 'false';
    }

    protected function getReadonly()
    {
        return $this->isReadonly() ? 'readonly="readonly"' : '';
    }

    public function toggleReadonlyByCondition(ConditionTree $conditionTree)
    {
        if (method_exists($this, 'addConditionAction')) {
            $this->addConditionAction(
                $conditionTree,
                fn ($target) => $target->configureReadonly(),
                fn ($target) => $target->configureReadonly(false),
            );
        }
    }
}
