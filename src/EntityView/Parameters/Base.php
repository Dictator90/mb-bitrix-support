<?php
namespace MB\Bitrix\EntityView\Parameters;

use Bitrix\Main\Type\Dictionary;

class Base extends Dictionary
{
    public function __construct()
    {
        parent::__construct($this->getDefault());
    }

    public function set($name, $value = null)
    {
        parent::set($name, $value);
        return $this;
    }

    public function get($name, $default = null)
    {
        return parent::get($name) ?: $default;
    }

    public function addTo($name, $value)
    {
        $curValue = $this->values[$name];
        if (is_array($curValue) || is_null($curValue)) {
            $this->values[$name][] = $value;
        } else {
            throw new \Exception("Can't addTo, key {$name} must be Array or Null");
        }

        return $this;
    }

    protected function getDefault(): array
    {
        return [];
    }
}
