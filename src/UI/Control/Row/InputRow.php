<?php

namespace MB\Bitrix\UI\Control\Row;

use MB\Bitrix\Contracts\UI\Renderable;
use MB\Bitrix\UI\Base\Field;
use MB\Bitrix\UI\Base\Row;
use MB\Bitrix\UI\Base\Traits\HasHint;
use MB\Bitrix\UI\Control\Traits\HasLabel;

class InputRow extends Row\ChildrenBase
{
    use HasLabel;
    use HasHint;

    protected bool $inlineMode = false;

    /**
     * <code>
     *     array(
     *         'label' => 'Test label',
     *         'field' => new TextField('test'),
     *         'hint' => 'Hint text',
     *         'inlineMode' => true
     *     )
     * </code>
     * @param array $params
     */
    public function __construct(array $params = [])
    {
        if ($params) {
            if ($params['label'] && is_string($params['label'])) {
                $this->setLabel($params['label']);
            }

            if ($params['hint'] && is_string($params['hint'])) {
                $this->setHint($params['hint']);
            }

            if ($params['inlineMode'] && is_bool($params['inlineMode'])) {
                $this->configureInlineMode($params['inlineMode']);
            }

            if ($params['field'] instanceof Field\AbstractBaseField) {
                $this->addChild($params['field']);
            } elseif (is_array($params['field'])) {
                $this->setChildren($params['field']);
            }
        }

        $this->enabled = null;
    }

    public function addChild(Renderable $child): static
    {
        if (!$this->isSetEnabled()) {
            $this->configureEnabled(false);
        }

        if (
            $child instanceof InputRow && $this->hasChildren()
            || ($child instanceof Field\AbstractBaseField && $child->isEnabled())
            || (method_exists($child, 'isEnabled') && $child->isEnabled())
        ) {
            $this->configureEnabled();
        }

        $this->children[] = $child;
        return $this;
    }

    public function configureInlineMode($value = true)
    {
        $this->inlineMode = $value;
        return $this;
    }

    public function getHtml(): string
    {
        return <<<DOC
        <div class="ui-form-row">
            {$this->getLeftHtml()}
            {$this->getRightHtml()}
        </div>
DOC;
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
                    $result .= "<div class=\"ui-form-row\">{$html}</div>";
                }
            }
        }
        if ($this->inlineMode) {
            $result .= "</div>";
        }

        return $result;
    }

    protected function getLeftHtml()
    {
        return <<<DOC
        <div class="ui-form-label">
            <div class="ui-ctl-label-text">{$this->getLabel()}{$this->getHintHtml()}</div>
        </div>
DOC;
    }

    protected function getRightHtml()
    {
        return <<<DOC
        <div class="ui-form-content">{$this->getInputsHtml()}</div>
DOC;
    }

    protected function getHintHtml()
    {
        $result = null;
        if ($hint = $this->getHint()) {
            $result = <<<DOC
            <span data-hint="{$hint}" class="ui-hint">
                <span class="ui-hint-icon"></span>
            </span>
DOC;
        }

        return $result;
    }

    public function getFields()
    {
        $result = [];
        foreach ($this->getChildren() as $child) {
            if ($child instanceof Field\AbstractBaseField) {
                $result[] = $child;
            } elseif (method_exists($child, 'hasChildren') && $child->hasChildren()) {
                $result = [...$result, ...$child->getFields()];
            }
        }

        return $result;
    }

    public function toArray()
    {
        $result = [
            'label' => $this->getLabel(),
            'hint' => $this->getHint()
        ];

        return array_merge(parent::toArray(), $result);
    }
}
