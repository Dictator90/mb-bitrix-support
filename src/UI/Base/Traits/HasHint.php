<?php

namespace MB\Bitrix\UI\Base\Traits;

trait HasHint
{
    protected ?string $hint = null;
    protected bool $hintHtml = false;

    public function getHint(): string
    {
        return $this->hintHtml ? $this->hint : htmlspecialcharsbx($this->hint);
    }

    public function setHint(string $hint): static
    {
        $this->hint = $hint;
        return $this;
    }

    public function configureHintHtml($value = true): static
    {
        $this->hintHtml = $value;
        return $this;
    }


}
