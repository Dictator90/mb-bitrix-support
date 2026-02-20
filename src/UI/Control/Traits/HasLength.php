<?php
namespace MB\Bitrix\UI\Control\Traits;

trait HasLength
{
    protected int|null $minlength = null;
    protected int|null $maxlength = null;

    public function getMinlength(): ?int
    {
        return $this->minlength;
    }

    public function setMinlength(?int $minlength): static
    {
        $this->minlength = $minlength;
        return $this;
    }

    public function getMaxlength(): ?int
    {
        return $this->maxlength;
    }

    public function setMaxlength(?int $maxlength): static
    {
        $this->maxlength = $maxlength;
        return $this;
    }

    protected function getLength()
    {
        return ($this->getMinlength() ? "minlength=\"{$this->getMinlength()}\" " : '')
            . ($this->getMaxlength() ? "maxlength=\"{$this->getMaxlength()}\" " : '');
    }
}
