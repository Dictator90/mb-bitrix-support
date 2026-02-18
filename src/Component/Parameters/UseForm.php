<?php

namespace MB\Bitrix\Component\Parameters;

use Bitrix\Main\Context;
use Bitrix\Main\Localization\Loc;

trait UseForm
{
    public static function getFormList(?string $lang = null, bool $hasEmpty = true): array
    {
        $result = [];
        if ($hasEmpty) {
            $lang = $lang ?? (defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru');
            $result = ['0' => Loc::getMessage('MB_CORE_COMP_PARAM_NOTSELECTED', null, $lang) ?: 'Не выбрано'];
        }

        if (!\Bitrix\Main\Loader::IncludeModule('form')) {
            return $result;
        }

        $query = \CForm::GetList(arFilter: ['ACTIVE' => 'Y', 'SITE' => Context::getCurrent()->getSite()]);
        while ($row = $query->Fetch()) {
            $result[$row['ID']] = $row['NAME'] . ' [' . $row['ID'] . ']';
        }

        return $result;
    }

    /** @return array<string, string> */
    public static function getFormFields(int|string $formId, bool $hasEmpty = true): array
    {
        $result = [];
        if ($hasEmpty) {
            $result = ['0' => Loc::getMessage('MB_CORE_COMP_PARAM_NOTSELECTED', null, defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru') ?: 'Не выбрано'];
        }

        if (!\Bitrix\Main\Loader::IncludeModule('form')) {
            return $result;
        }

        $formId = (int) $formId;
        $query = \CFormField::GetList($formId, '');
        while ($row = $query->Fetch()) {
            $result[$row['ID']] = $row['TITLE'] . ' [' . $row['ID'] . ']';
        }

        return $result;
    }
}
