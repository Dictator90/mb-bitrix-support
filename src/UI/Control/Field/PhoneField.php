<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\UI\Extension;

class PhoneField extends TextField
{
    public function getHtml(): string
    {
        Extension::load('mb.mask');

        $parentHtml = parent::getHtml();
        return <<<DOC
            {$parentHtml}
            <script>
                new MB.Mask.Phone(BX({$this->getId()}))
            </script>
DOC;
    }
}
