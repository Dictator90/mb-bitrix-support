<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\Security\Random;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;
use MB\Bitrix\UI\Base\Field\AbstractEntitySelectorField;

class IblockSelectorField extends AbstractEntitySelectorField
{
    public function getHtml(): string
    {
        Extension::load(['ui', 'mb.ui.dialog-selector']);

        $jsonValue = Json::encode($this->getValue());
        $random = Random::getString(5);
        $name = $this->getName() . '_' . $random;

        return <<<DOC
            <div id="tag_selector_{$name}"></div>
            <script>
                (new  MB.UI.DialogSelector.DialogSelector({
                    target: '#tag_selector_{$name}',
                    name: '{$this->getName()}',
                    dialog: {
                        context: 'MB_CORE_{$this->getName()}',
                        dropdownMode: true,
                        preload: true,
                        entities: [
                            {
                                id: 'iblock-list',
                                options: {
                                    selected: {$jsonValue}
                                },
                                dynamicLoad: true,
                                dynamicSearch: true
                            },
                        ],
                    },
                    multiple: {$this->isMultipleJson()},
            
                })).render();
            </script>
DOC;
    }
}
