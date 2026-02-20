<?php

namespace MB\Bitrix\UI\Traits;

trait HasClass
{
    protected ?array $class = null;
    protected string $classString = '';

    public function getClass(): string
    {
        return $this->classString;
    }

    public function getClassArray(): ?array
    {
        return $this->class;
    }

    public function setClass(array|string $class): void
    {
        if (is_string($class)) {
            $class = explode(' ', $class);
        }
        $this->class = array_values($class);
        $this->classString = implode(' ', $this->class);
    }
}
