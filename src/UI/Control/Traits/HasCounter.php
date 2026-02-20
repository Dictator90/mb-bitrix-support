<?php

namespace MB\Bitrix\UI\Control\Traits;

trait HasCounter
{
    protected ?int $counter = null;

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function setCounter(string|int $counter): static
    {
        $this->counter = intval($counter);
        return $this;
    }

    public function hasCounter(): bool
    {
        return $this->counter !== null;
    }

}
