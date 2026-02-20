<?php

namespace MB\Bitrix\UI\Base\Traits;

trait HasContent
{
    protected ?string $content = null;

    public function hasContent(): bool
    {
        return !$this->content;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $value): static
    {
        $this->content = $value;
        return $this;
    }
}
