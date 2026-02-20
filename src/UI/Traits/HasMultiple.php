<?php

namespace MB\Bitrix\UI\Traits;

trait HasMultiple
{
    protected bool $multiple = false;

    public function configureMultiple(bool $value = true): static
    {
        $this->multiple = $value;
        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function isMultipleJson()
    {
        return $this->multiple ? 'true' : 'false';
    }
}
