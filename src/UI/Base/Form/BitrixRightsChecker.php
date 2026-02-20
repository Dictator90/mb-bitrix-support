<?php

namespace MB\Bitrix\UI\Base\Form;

class BitrixRightsChecker implements RightsCheckerInterface
{
    public function getGroupRight(string $moduleId): string
    {
        global $APPLICATION;
        return $APPLICATION->GetGroupRight($moduleId);
    }
}
