<?php
namespace MB\Bitrix\UI\Control\Traits;

trait HasMaxCount
{
    protected ?int $maxCount = 3;

    public function setMaxCount(int $value): static
    {
        $this->maxCount = $value;
        return $this;
    }

    public function getMaxCount(): int
    {
        return $this->maxCount;
    }
}
