<?php

namespace MB\Bitrix\UI\Control\Traits;

trait HasMaxSize
{
    protected int $maxSize = 0;

    public function setMaxSize(int $maxSize): static
    {
        $this->maxSize = $maxSize;
        return $this;
    }

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }
}
