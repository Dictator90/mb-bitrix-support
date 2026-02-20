<?php

namespace MB\Bitrix\UI\Traits;

use MB\Support\Conditionable\ConditionTree;
use MB\Bitrix\Support\Data\TextString;

trait HasEnabled
{
    protected ?bool $enabled = null;

    public function configureEnabled(bool $value = true)
    {
        $this->enabled = $value;
        return $this;
    }

    public function isEnabled(): bool
    {
        if ($this->isSetEnabled()) {
            return $this->enabled;
        }
        return true;
    }

    public function isSetEnabled(): bool
    {
        return $this->enabled !== null;
    }

    public function toggleEnabledByModuleSettings(string $moduleOption, $operator, $value = null)
    {
        if (method_exists($this, 'addConditionAction')) {
            if (TextString::match('#^(.*):(.*)$#', $moduleOption, $match)) {
                $moduleId = $match[1];
                $option = $match[2];
                $condition = ConditionTree::create();
                if (!$value) {
                    $value = $operator;
                    $operator = '=';
                }
                if (config($moduleId)) {
                    $condition->where([[config($moduleId), 'get'], [$option]], $operator, $value);
                }
                $this->addConditionAction(
                    $condition,
                    fn ($target) => $target->configureEnabled(),
                    fn ($target) => $target->configureEnabled(false),
                );
            }
        }
        return $this;
    }

    public function toggleEnabledByCondition(ConditionTree $conditionTree)
    {
        if (method_exists($this, 'addConditionAction')) {
            $this->addConditionAction(
                $conditionTree,
                fn ($target) => $target->configureEnabled(),
                fn ($target) => $target->configureEnabled(false),
            );
        }
        return $this;
    }
}
