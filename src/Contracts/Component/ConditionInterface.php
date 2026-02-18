<?php

namespace MB\Bitrix\Contracts\Component;

interface ConditionInterface
{
    /**
     * Evaluate condition against current parameter values.
     *
     * @param array<string, mixed> $values current component parameter values (key => value)
     */
    public function evaluate(array $values): bool;
}
