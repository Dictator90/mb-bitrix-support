<?php

namespace MB\Bitrix\UI\Traits;

trait HasLabel
{
    protected ?string $label = null;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = trim($label);
        return $this;
    }
}
