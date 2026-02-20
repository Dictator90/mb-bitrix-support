<?php

namespace MB\Bitrix\UI\Control\Traits;

trait HasCanEdit
{
    protected bool $canEdit = true;

    public function configureCanEdit($value = true)
    {
        $this->canEdit = $value;
        return $this;
    }

    public function isCanEdit()
    {
        return $this->canEdit;
    }
}
