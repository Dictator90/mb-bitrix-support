<?php

namespace MB\Bitrix\UI\Control\Row;

use Bitrix\Main\Security\Random;
use MB\Bitrix\UI\Control\TabSet\BitrixTabSet;
use MB\Bitrix\UI\Base\Row\ChildrenBase as RowBase;
use MB\Bitrix\UI\Base\Tab;

class SubTabsRow extends RowBase
{

    public function __construct(array $tabs = null)
    {
        $this->setChildren($tabs);
    }

    public function getHtml(): string
    {
        $tabs = [];
        $children = $this->getChildren();
        foreach ($children as $child) {
            if ($child instanceof Tab\Base) {
                $tabs[] = $child;
            }
        }
        $tabSet = new BitrixTabSet($tabs);
        $tabSet->setId('mb-tabs-' . Random::getString(8));

        ob_start();
        $tabSet->render();
        $tabsHtml = ob_get_clean();

        unset($tabs, $tabSet);

        return <<<DOC
        <div class="ui-form-row">
            <div class="ui-form-content">
            {$tabsHtml}
            </div>
        </div>
DOC;
    }
}
