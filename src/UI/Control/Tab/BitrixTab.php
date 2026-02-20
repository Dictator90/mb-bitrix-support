<?php

namespace MB\Bitrix\UI\Control\Tab;

use MB\Bitrix\UI\Control\Traits\HasCounter;
use MB\Bitrix\UI\Base;

class BitrixTab extends Base\Tab\Base
{
    use HasCounter;

    public function getTabHtml(): string
    {
        $this->beforeGetTab();
        $activeClass = $this->isActive() ? 'mb-tabs-switcher-selected' : '';
        $counter = $this->hasCounter()
            ? "<span class=\"mb-tabs-switcher-text-counter\">{$this->getCounter()}</span>"
            : "";

        return <<<DOC
            <div class="mb-tabs-switcher {$activeClass}" data-tab-id="{$this->getId()}">
                <div class="mb-tabs-switcher-text">
                    <div class="mb-tabs-switcher-text-inner">
                        {$this->getLabel()}
                        {$counter}
                    </div>
                </div>
            </div>
DOC;
    }

    public function getTabContentHtml(): string
    {
        $this->beforeGetTabContent();
        $activeClass = $this->isActive() ? 'mb-tabs-switcher-block-selected' : '';
        return <<<DOC
            <div class="mb-tabs-switcher-block {$activeClass}" data-tab-content="{$this->getId()}">
                <div class="mb-tabs-switcher-block__title">
                    {$this->getDescription()}
               </div>
                <div class="ui-form ui-form-section">
                {$this->getHtml()}
                </div>
            </div>
DOC;
    }

    public function beforeGetTab(): void
    {}

    public function beforeGetTabContent(): void
    {}

    public function toArray()
    {
        $this->beforeGetTab();
        return array_merge(parent::toArray(), [
            'counter' => $this->hasCounter() ? $this->getCounter() : false
        ]);
    }
}
