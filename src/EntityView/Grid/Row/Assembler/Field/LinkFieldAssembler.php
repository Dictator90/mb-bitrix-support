<?php

namespace MB\Bitrix\EntityView\Grid\Row\Assembler\Field;

use MB\Bitrix\EntityView\Grid\Row\Assembler\BaseFieldAssembler;

class LinkFieldAssembler extends BaseFieldAssembler
{
    protected string $type = 'text';

    protected function prepareColumn($value)
    {
        return "<a href=\"$value\" target='_blank'>{$value}</a>";
    }
}
