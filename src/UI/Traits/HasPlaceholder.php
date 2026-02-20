<?php

namespace MB\Bitrix\UI\Traits;

trait HasPlaceholder
{
    protected ?string $placeholder = null;

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }
}
