<?php

namespace MB\Bitrix\UI\Base\Form;

class BitrixButtonPanelRenderer implements ButtonPanelRendererInterface
{
    public function render(array $params): void
    {
        global $APPLICATION;
        $APPLICATION->IncludeComponent('bitrix:ui.button.panel', '', $params);
    }
}
