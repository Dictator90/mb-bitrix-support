<?php

namespace MB\Bitrix\Component\Parameters;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserConsent\Internals\AgreementTable;

trait UseAgreement
{
    public static function getAgreementList(?string $lang = null, bool $hasEmpty = true): array
    {
        $result = [];
        if ($hasEmpty) {
            $lang = $lang ?? (defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru');
            $result = ['0' => Loc::getMessage('MB_CORE_COMP_PARAM_NOTSELECTED', null, $lang) ?: 'Не выбрано'];
        }

        if (!\Bitrix\Main\Loader::includeModule('main')) {
            return $result;
        }

        $rows = AgreementTable::getList([
            'select' => ['ID', 'NAME'],
            'filter' => ['=ACTIVE' => 'Y'],
            'order' => ['ID' => 'DESC'],
        ]);
        foreach ($rows as $row) {
            $result[$row['ID']] = $row['NAME'] . ' [' . $row['ID'] . ']';
        }

        return $result;
    }
}
