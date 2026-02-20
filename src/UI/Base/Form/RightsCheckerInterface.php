<?php

namespace MB\Bitrix\UI\Base\Form;

interface RightsCheckerInterface
{
    public function getGroupRight(string $moduleId): string;
}
