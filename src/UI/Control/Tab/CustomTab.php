<?php

namespace MB\Bitrix\UI\Control\Tab;

class CustomTab extends BitrixTab
{
    protected string $content = '';

    public function setContent(string $content)
    {
        $this->content = $content;
        return $this;
    }

    public function getTabContentHtml(): string
    {
        $activeClass = $this->isActive() ? 'mb-tabs-switcher-block-selected' : '';
        return <<<DOC
            <div class="mb-tabs-switcher-block {$activeClass}" data-tab-content="{$this->getId()}">
                <div class="mb-tabs-switcher-block__title">
                    {$this->getDescription()}
               </div>
                <div class="ui-form ui-form-section" style="padding: 20px">
                    {$this->content}
                </div>
            </div>
DOC;
    }
}
