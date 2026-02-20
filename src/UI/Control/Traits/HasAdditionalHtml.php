<?php

namespace MB\Bitrix\UI\Control\Traits;

trait HasAdditionalHtml
{
    protected string|null $aboveTarget = null;
    protected string|null $belowTarget = null;
    protected string|null $beforeTarget = null;
    protected string|null $afterTarget = null;

    public function getAboveTarget(): ?string
    {
        return $this->aboveTarget;
    }

    public function setAboveTarget(string $aboveTarget): static
    {
        $this->aboveTarget = $aboveTarget;
        return $this;
    }

    public function getBelowTarget(): ?string
    {
        return $this->belowTarget;
    }

    public function setBelowTarget(string $belowTarget): static
    {
        $this->belowTarget = $belowTarget;
        return $this;
    }

    public function getBeforeTarget(): ?string
    {
        return $this->beforeTarget;
    }

    public function setBeforeTarget(string $beforeTarget): static
    {
        $this->beforeTarget = $beforeTarget;
        return $this;
    }

    public function getAfterTarget(): ?string
    {
        return $this->afterTarget;
    }

    public function setAfterTarget(string $afterTarget): static
    {
        $this->afterTarget = $afterTarget;
        return $this;
    }
}
