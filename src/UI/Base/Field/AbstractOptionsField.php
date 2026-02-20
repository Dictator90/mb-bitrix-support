<?php

namespace MB\Bitrix\UI\Base\Field;

use MB\Bitrix\UI\Traits\HasName;
use MB\Bitrix\UI\Traits\HasOptions;
use MB\Bitrix\UI\Base\Field;

abstract class AbstractOptionsField extends Field\AbstractBaseField
{
    use HasName;
    use HasOptions;

    public function __construct(string $name, array $options = [])
    {
        $this->setName($name);
        $this->setOptions($options);
    }
}
