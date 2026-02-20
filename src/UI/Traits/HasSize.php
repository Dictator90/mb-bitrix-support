<?php

namespace MB\Bitrix\UI\Traits;

trait HasSize
{
    protected int $size = 20;

    public function setSize(int|string $size): static
    {
        $this->size = intval($size);
        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }
}
