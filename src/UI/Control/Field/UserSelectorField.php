<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;
use MB\Bitrix\UI\Base\Field\AbstractEntitySelectorField;

class UserSelectorField extends AbstractEntitySelectorField
{
    public function getHtml(): string
    {
        Extension::load(['ui', 'mb.ui.dialog-selector']);

        $jsonValue = Json::encode($this->getValue());

        return <<<DOC
            <div id="tag_selector_{$this->getName()}"></div>
            <script>
                (new  MB.UI.DialogSelector.DialogSelector({
                    target: '#tag_selector_{$this->getName()}',
                    name: '{$this->getName()}',
                    dialog: {
                        context: 'MB_CORE_{$this->getName()}',
                        dropdownMode: true,
                        preload: true,
                        entities: [
                            {
                                id: 'user-list',
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
