<?php

namespace MB\Bitrix\UI\Base\Traits;

use Bitrix\Main\Diag\Debug;
use MB\Support\Conditionable\ConditionTree;

trait HasCondition
{
    protected $conditionTreeStack = [];

    public function addConditionAction(ConditionTree $conditionTree, callable $callableTrue = null, callable $callableFalse = null)
    {
        $this->conditionTreeStack[$this->getConditionHash($conditionTree)] = [
            'condition' => $conditionTree,
            'callable' => [$callableTrue, $callableFalse]
        ];

        return $this;
    }

    public function doConditionActions()
    {
        foreach ($this->conditionTreeStack as $data) {
            if ($data['condition']->calculate()) {
                if ($data['callable'][0]) {
                    $data['callable'][0]($this);
                }
            } else {
                if ($data['callable'][1]) {
                    $data['callable'][1]($this);
                }
            }
        }
    }

    public function hasConditionActions()
    {
        return !empty($this->conditionTreeStack);
    }

    private function getConditionHash(ConditionTree $conditionTree)
    {
        return md5(serialize($conditionTree));
    }

    protected function getCalledClass()
    {
        return new \ReflectionClass($this);
    }
}
