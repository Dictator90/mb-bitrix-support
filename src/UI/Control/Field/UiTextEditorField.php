<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\Loader;
use Bitrix\Main\UI\Extension;
use MB\Bitrix\UI\Base\Field;

/** todo: fix this */
class UiTextEditorField extends Field\AbstractBaseField
{
    public function __construct($name)
    {
        Loader::includeModule('ui');
        Extension::load('ui.text-editor');

        $this->setName($name);
    }

    public function getHtml(): string
    {
        $id = $this->getName() . '_test';
        return <<<DOC
        <div id="{$id}"></div>
        <script>
            let texteditor = new BX.UI.TextEditor.TextEditor({
                content: {$this->getValue()},
                editable: true,
                collapsingMode: true
            });
           texteditor.renderTo(document.querySelector('#{$id}'));
        </script>
DOC;

    }
}
