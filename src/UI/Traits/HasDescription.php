<?php

namespace MB\Bitrix\UI\Traits;

trait HasDescription
{
    protected ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function hasDescription()
    {
        return !empty($this->description);
    }
}
