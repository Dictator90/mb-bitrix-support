<?php

namespace MB\Bitrix\UI\Base\Row;

use MB\Bitrix\Contracts\UI\Renderable;
use MB\Bitrix\UI\Traits\RendersWithConditions;
use MB\Bitrix\UI\Traits\HasEnabled;
use MB\Bitrix\UI\Base\Traits\HasCondition;

abstract class Base
    implements Renderable
{
    use HasEnabled;
    use HasCondition;
    use RendersWithConditions;

    abstract public function getHtml(): string;

    protected function beforeRender(): void
    {
    }

    protected function afterRender(): void
    {
    }
}
