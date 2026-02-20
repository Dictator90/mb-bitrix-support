<?php

namespace MB\Bitrix\UI\Base\Traits;

use MB\Bitrix\UI\Base\Row;

trait HasChildren
{
    protected $children = [];

    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setChildren(array $children): static
    {
        foreach ($children as $child) {
            $this->addChild($child);
        }
        return $this;
    }

    public function addChild($child): static
    {
        $this->children[] = $child;
        return $this;
    }
}
