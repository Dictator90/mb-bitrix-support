<?php

namespace MB\Bitrix\UI\Control\Field;

use MB\Bitrix\UI\Control\Traits\HasLength;
use MB\Bitrix\UI\Control\Traits\HasSize;

class TextField extends InputField
{
    use HasLength;
    use HasSize;

    public static function getType(): string
    {
        return 'text';
    }

    protected function exAttributes(): array
    {
        $result = [
            'size' => $this->getSize(),
        ];

        if ($this->getLength()) {
            $result['minlength'] = $this->getMinlength();
            $result['maxlength'] = $this->getMaxlength();
        }

        return $result;
    }
}
