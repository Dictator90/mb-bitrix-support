<?php

namespace MB\Bitrix\UI\Control\Form;

use MB\Bitrix\UI\Base\Form;

class Bitrix extends Form\Base
{
    public function getJsExtensions(): array
    {
        return ['ui.form-elements', 'ui.layout-form', 'ui.hint'];
    }

    public function getIncludeModules()
    {
        return ['ui'];
    }

    protected function beforeButtonPanel(): string
    {
        return '<div class="mb-buttons-container">';
    }

    protected function afterButtonPanel(): string
    {
        return '</div>';
    }
}
