<?php

namespace MB\Bitrix\UI\Traits;

trait HasFilter
{
    protected array $filter = [];

    public function getFilter(): array
    {
        return $this->filter;
    }

    public function setFilter(array $filter): static
    {
        $this->filter = $filter;
        return $this;
    }
}
