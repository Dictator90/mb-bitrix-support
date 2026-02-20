<?php

namespace MB\Bitrix\UI\Control\TabSet;

use Bitrix\Main\Security\Random;
use MB\Bitrix\UI\Control\Traits\HasId;
use MB\Bitrix\UI\Base\Tab\Set;

class BitrixTabSet extends Set
{
    use HasId;

    public function __construct(array $tabs = null)
    {
        parent::__construct($tabs);
        $this->checkActiveTab();
    }

    public function checkActiveTab()
    {
        if ($this->getTabs()) {
            $hasActive = false;
            foreach ($this->getTabs() as $tab) {
                if ($tab->isActive() && !$hasActive) {
                    $hasActive = true;
                    continue;
                }

                if ($hasActive && $tab->isActive()) {
                    $tab->configureActive(false);
                }
            }

            if (!$hasActive) {
                $this->getTabs()[0]->configureActive();
            }
        }
    }

    protected function getTabsetHeaderHtml(): string
    {
        return <<<DOC
            <div class="mb-tabs-header">
                <div class="mb-tabs-header-switcher" data-parent-tab="{$this->getId()}">
                    {$this->getTabsHeaderHtml()}
                </div>
            </div>
DOC;
    }

    protected function getTabsetContentHtml(): string
    {
        return <<<DOC
            <div class="mb-tabs-content" data-parent-tab="{$this->getId()}">
                {$this->getTabsContentHtml()}
            </div>
DOC;
    }

    protected function getTabsetStartHtml(): string
    {
        if (!$this->id) {
            $this->setId('mb-tab-' . Random::getString(8));
        }

        return <<<DOC
            <div class="mb-tabs-container" id="{$this->getId()}">
DOC;
    }

    protected function getTabsetEndHtml(): string
    {

        return <<<DOC
            </div>
            <script>
            new MBAdminTabs("{$this->getId()}", {});
            </script>
DOC;
    }
}
