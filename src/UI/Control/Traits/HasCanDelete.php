<?php

namespace MB\Bitrix\UI\Control\Traits;

trait HasCanDelete
{
    protected bool $canDelete = true;

    public function configureCanDelete($value = true)
    {
        $this->canDelete = $value;
        return $this;
    }

    public function isCanDelete()
    {
        return $this->canDelete;
    }
}
