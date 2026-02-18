<?php

namespace MB\Bitrix\HighloadBlock\Fields\Traits;

trait HasRequired
{
    protected bool $isRequired = false;

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function configureRequired(bool $value = true): static
    {
        $this->isRequired = $value;
        return $this;
    }
}
