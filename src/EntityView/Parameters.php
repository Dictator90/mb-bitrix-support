<?php
namespace MB\Bitrix\EntityView;

use Bitrix\Main\Type\Dictionary;

class Parameters extends Dictionary
{
    public function set($name, $value = null)
    {
        parent::set($name, $value);
        return $this;
    }

    public function get($name, $default = null)
    {
        return parent::get($name) ?: $default;
    }
}
