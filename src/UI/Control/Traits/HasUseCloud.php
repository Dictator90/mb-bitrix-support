<?php

namespace MB\Bitrix\UI\Control\Traits;

trait HasUseCloud
{
    protected bool $useCloud = true;

    public function configureUseCloud($value = true)
    {
        $this->useCloud = $value;
        return $this;
    }

    public function isUseCloud()
    {
        return $this->useCloud;
    }
}
