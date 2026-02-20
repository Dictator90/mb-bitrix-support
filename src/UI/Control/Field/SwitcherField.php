<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\Security\Random;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;
use MB\Bitrix\UI\Control\Traits\HasAdditionalHtml;
use MB\Bitrix\UI\Control\Traits\HasDisabled;
use MB\Bitrix\UI\Base\Field\AbstractBaseField;

class SwitcherField extends AbstractBaseField
{
    use HasDisabled;
    use HasAdditionalHtml;

    public const SWITCH_ON = 'Y';
    public const SWITCH_OFF = 'N';

    public const COLOR_PRIMARY = 'primary';
    public const COLOR_GREEN = 'green';

    public const SIZE_XS = 'extra-small';
    public const SIZE_S = 'small';
    public const SIZE_M = 'medium';

    protected string $color = self::COLOR_PRIMARY;
    protected string $size = self::SIZE_M;

    public function __construct($name)
    {
        $this->setName($name);
        $this->setId("{$name}_" . Random::getString(10));
    }

    public function configureColor(string $value)
    {
        $this->color = $value;
        return $this;
    }

    public function getColor()
    {
        return $this->color;
    }

    public function configureSize(string $value)
    {
        $this->size = $value;
        return $this;
    }

    public function getSize()
    {
        return $this->size;
    }

    protected function beforeRender()
    {
        echo <<<DOC
DOC;
    }

    public function getHtml(): string
    {
        Extension::load('ui.switcher');

        $id = $this->getId();
        $checked = $this->getValue() === self::SWITCH_ON;
        $switcherParams = Json::encode([
            'checked' => $checked,
            'inputName' => $this->getName(),
            'color' => $this->getColor(),
            'size' => $this->getSize(),
            'disabled' => $this->isDisabled()
        ]);
        return <<<DOC
            <div id="{$id}" {$this->getStyle()}></div>
            <script>
                (new window.BX.UI.Switcher({$switcherParams})).renderTo(BX('{$id}'));
            </script>
DOC;
    }
}
