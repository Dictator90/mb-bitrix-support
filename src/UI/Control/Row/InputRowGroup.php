<?php

namespace MB\Bitrix\UI\Control\Row;

use MB\Bitrix\Contracts\UI\Renderable;

class InputRowGroup extends InputRow
{
    public function getHtml(): string
    {
        if ($this->getLabel() !== null) {
            return <<<DOC
                <div class="ui-form-row">
                    {$this->getLeftHtml()}
                    <div class="ui-form-content">
                        <div class="ui-form-row-group">
                            {$this->getRightHtml()}
                        </div>
                    </div>
                </div>
            
DOC;
        }

        return <<<DOC
            <div class="ui-form-row-group">
                {$this->getRightHtml()}
            </div>
DOC;


    }

    protected function getRightHtml()
    {
        return $this->getInputsHtml();
    }

    protected function getInputsHtml(): string
    {
        $result = null;
        if ($this->inlineMode) {
            $result .= "<div class='ui-form-row-inline ui-form-row-inline-wa'>";
        }
        foreach ($this->getChildren() as $child) {
            if ($child instanceof Renderable) {
                ob_start();
                $child->render();
                $html = ob_get_clean();

                if ($child instanceof InputRow) {
                    $result .= "{$html}";
                } else {
                    ob_start();
                    (new InputRow())
                        ->addChild($child)
                        ->setLabel($this->getLabel())
                        ->render();
                    $inputRowHtml = ob_get_clean();
                    $result .= "{$inputRowHtml}";
                }
            }
        }
        if ($this->inlineMode) {
            $result .= "</div>";
        }

        return $result;
    }
}
