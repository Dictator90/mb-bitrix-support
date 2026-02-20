<?php

namespace MB\Bitrix\UI\Traits;

trait HasOptions
{
    protected array $options = [];
    protected bool $hasNoSelectOption = true;

    public function setOptions(array $values)
    {
        $this->options = $values;
        return $this;
    }

    public function getRawOptions()
    {
        return $this->options;
    }

    public function getOptions()
    {
        return $this->hasNoSelectOption()
            ? $this->getNoSelectOption() + $this->options
            : $this->options;
    }

    public function configureNoSelectOption(bool $value = true)
    {
        $this->hasNoSelectOption = $value;
        return $this;
    }

    public function getNoSelectOption()
    {
        return [0 => 'Не выбранно'];
    }

    public function hasNoSelectOption()
    {
        return $this->hasNoSelectOption;
    }
}
