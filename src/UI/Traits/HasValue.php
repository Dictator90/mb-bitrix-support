<?php

namespace MB\Bitrix\UI\Traits;

trait HasValue
{
    use HasName;
    use HasDefaultValue;

    protected mixed $value = null;
    protected mixed $rawValue = null;

    public function getValue(): mixed
    {
        return $this->value ?: $this->getDefaultValue();
    }

    public function getRawValue()
    {
        return $this->rawValue;
    }

    protected function beforeSetValue(&$value)
    {
    }

    public function setValue(mixed $value): static
    {
        $this->rawValue = $value;
        $this->beforeSetValue($value);
        $this->value = $value;
        return $this;
    }
}
