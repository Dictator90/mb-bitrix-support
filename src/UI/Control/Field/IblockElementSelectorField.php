<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\Web\Json;
use MB\Bitrix\UI\Base\Field\AbstractEntitySelectorField;

class IblockElementSelectorField extends AbstractEntitySelectorField
{
    protected $primary = 'iblockId';
    protected int $iblockId = 0;

    public function __construct(string $name, $iblockId = 0)
    {
        $this->iblockId = intval($iblockId);

        parent::__construct($name);
    }

    public function getHtml(): string
    {
        \Bitrix\Main\UI\Extension::load(['ui', 'mb.ui.dialog-selector']);

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
                                id: 'iblock-element-list',
                                options: {
                                     selected: {$jsonValue},
                                     iblockId: {$this->iblockId}
                                },
                                dynamicLoad: true,
                                dynamicSearch: true
                            },
                        ],
                    },
                    multiple: {$this->isMultipleJson()}
            
                })).render();
            </script>
DOC;
    }
}
