<?php

namespace MB\Bitrix\UI\Traits;

trait HasId
{
    protected ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = trim($id);
        return $this;
    }
}
