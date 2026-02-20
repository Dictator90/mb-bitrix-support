<?php

namespace MB\Bitrix\UI\Base\Grid;

class EmptyGrid extends Base
{
    public function __construct()
    {
        $gridArea = new TemplateArea();
        $gridArea->addRowString('.');
        parent::__construct($gridArea);
    }
}
