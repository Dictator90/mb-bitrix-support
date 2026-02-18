<?php

namespace MB\Bitrix\Config;

use Bitrix\Main\Config\Option;
use MB\Support\Str;

trait UseOptions
{
    protected static string $serializedOptionPrefix = '__MB_CONFIG__:';

    protected function getFromStorage($name, $default = null, string|bool $siteId = false)
    {
        $optionValue = Option::get($this->module->getId(), $name, $default, $siteId);

        if (Str::position($optionValue, static::$serializedOptionPrefix) === 0) {
            $truncatedValue = Str::substring(
                $optionValue,
                Str::length(static::$serializedOptionPrefix)
            );
            $unserializedValue = unserialize($truncatedValue);
            $optionValue = ($unserializedValue !== false ? $unserializedValue : null);
        }

        if (!isset($optionValue)) {
            $optionValue = $default;
        }

        return $optionValue;
    }

    protected function setToStorage($name, $value = "", $siteId = "")
    {
        if (!is_scalar($value)) {
            $value = static::$serializedOptionPrefix . serialize($value);
        }

        Option::set($this->module->getId(), $name, $value, $siteId);

        return $this;
    }

    protected function getAllFromStorage($siteId = '')
    {
        $result = [];

        $optionValues = Option::getForModule($this->module->getId(), $siteId);
        foreach ($optionValues as $optionName => $optionValue) {
            if (Str::position($optionValue, static::$serializedOptionPrefix) === 0) {
                $truncatedValue = Str::substring(
                    $optionValue,
                    Str::length(static::$serializedOptionPrefix)
                );
                $unserializedValue = unserialize($truncatedValue);
                $optionValue = ($unserializedValue !== false ? $unserializedValue : null);
            }

            $result[$optionName] = $optionValue;
        }

        return $result;

    }

    public function removeFromStorage(string $name, $siteId = ""): static
    {
        Option::delete($this->module->getId(), ['name' => $name, 'site_id' => $siteId]);

        return $this;
    }
}
