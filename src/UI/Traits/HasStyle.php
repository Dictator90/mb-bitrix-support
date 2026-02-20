<?php

namespace MB\Bitrix\UI\Traits;

trait HasStyle
{
    protected array $style = [];

    public function setStyle(array $style): static
    {
        $this->style = $style;
        return $this;
    }

    public function addStyle($name, $value): static
    {
        $this->style[$name] = $value;
        return $this;
    }

    public function getStyleArray(): array
    {
        return $this->style;
    }

    public function getStyle(): string
    {
        $result = [];
        foreach ($this->getStyleArray() as $name => $value) {
            $result[] = "$name:$value;";
        }
        return "style=\"" . implode('', $result) . "\"";
    }
}
