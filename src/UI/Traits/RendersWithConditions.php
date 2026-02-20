<?php

namespace MB\Bitrix\UI\Traits;

/**
 * Shared render implementation: condition actions, enabled check, beforeRender/getHtml/afterRender.
 * Requires: getHtml(): string, hasConditionActions(), doConditionActions(), isEnabled(), beforeRender(), afterRender().
 */
trait RendersWithConditions
{
    public function render(): void
    {
        if ($this->hasConditionActions()) {
            $this->doConditionActions();
        }

        if (!$this->isEnabled()) {
            return;
        }

        $this->beforeRender();
        echo $this->getHtml();
        $this->afterRender();
    }
}
