<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\Web\Json;
use MB\Bitrix\UI\Base\Field\AbstractEntitySelectorField;

class IblockPropertySelectorField extends AbstractEntitySelectorField
{
    protected ?int $iblockId = null;

    public function __construct(string $name, $iblockId = null)
    {
        $this->iblockId = intval($iblockId);
        parent::__construct($name);
    }

    public function getHtml(): string
    {
        \Bitrix\Main\UI\Extension::load(['ui', 'mb.ui.dialog-selector']);

        $options = [
            'selected' => $this->getValue(),
            'iblockId' => $this->iblockId,
            'filter' => $this->getFilter()
        ];

        $jsonOptions = Json::encode($options);

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
                                id: 'iblock-property-list',
                                options: {$jsonOptions},
                                dynamicLoad: true,
                                dynamicSearch: true
                            },
                        ],
                    },
                    multiple: {$this->isMultipleJson()},
                    readonly: {$this->isReadonlyJson()},
                    locked: true
                    //deselectable: true
            
                })).render();
            </script>
DOC;
    }
}
