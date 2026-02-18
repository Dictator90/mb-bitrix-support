<?php

namespace MB\Bitrix\HighloadBlock\Fields\Traits;

trait HasMultiple
{
    protected bool $isMultiple = false;

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    public function configureMultiple(bool $value = true): static
    {
        $this->isMultiple = $value;
        return $this;
    }
}
