<?php

namespace MB\Bitrix\UI\Control\Row;

use MB\Bitrix\UI\Base\Row\Base as RowBase;
use MB\Bitrix\UI\Base\Traits\HasContent;

class CustomRow extends RowBase
{
    use HasContent;

    public function getHtml(): string
    {
        return <<<DOC
        <div class="ui-form-row">
            {$this->getContent()}
        </div>
DOC;
    }
}
