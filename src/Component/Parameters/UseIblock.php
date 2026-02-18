<?php

namespace MB\Bitrix\Component\Parameters;

use Bitrix\Iblock\ORM\Query;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

trait UseIblock
{
    public static function getIblockTypes(string $lang = null, bool $hasEmpty = true): array
    {
        Loader::includeModule('iblock');
        $lang = $lang ?? (defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru');

        $result = [];
        if ($hasEmpty) {
            $result = ['0' => Loc::getMessage('MB_CORE_COMP_PARAM_NOTSELECTED', null, $lang) ?: Loc::getMessage('IBLOCK_FIELD_EMPTY', null, $lang) ?: 'Не выбрано'];
        }

        $rows = \Bitrix\Iblock\TypeTable::query()
            ->setSelect(['ID', 'NAME' => 'LANG_MESSAGE.NAME', 'LID' => 'LANG_MESSAGE.LANGUAGE_ID'])
            ->where('LID', $lang)
            ->addOrder('SORT', 'ASC')
            ->addOrder('ID', 'DESC')
            ->setCacheTtl(846000)
            ->cacheJoins(true)
            ->fetchAll();

        foreach ($rows as $row) {
            $result[$row['ID']] = '[' . $row['ID'] . '] ' . $row['NAME'];
        }

        return $result;
    }

    public static function getIblocks(?string $iblockType, string $lang = null, bool $hasEmpty = true): array
    {
        Loader::includeModule('iblock');
        $lang = $lang ?? (defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru');

        $result = [];
        if (!trim((string) $iblockType)) {
            return $result;
        }

        if ($hasEmpty) {
            $result = ['0' => Loc::getMessage('MB_CORE_COMP_PARAM_NOTSELECTED', null, $lang) ?: Loc::getMessage('IBLOCK_FIELD_EMPTY', null, $lang) ?: 'Не выбрано'];
        }

        $query = (new Query(\Bitrix\Iblock\IblockTable::getEntity()))
            ->setSelect(['ID', 'NAME'])
            ->where('TYPE.ID', $iblockType)
            ->where('ACTIVE', true)
            ->addOrder('SORT')
            ->addOrder('ID', 'DESC')
            ->setCacheTtl(846000);

        $rows = $query->fetchAll();
        foreach ($rows as $row) {
            $result[$row['ID']] = '[' . $row['ID'] . '] ' . $row['NAME'];
        }

        return $result;
    }

    public static function getIblockProperties($iblockId, bool $addAll = true, bool $hasEmpty = false): array
    {
        return self::getIblockPropertiesByType($iblockId, 'all', $addAll, $hasEmpty);
    }

    public static function getIblockPropertiesByType($iblockId, string $type = 'all', bool $addAll = true, bool $hasEmpty = false): array
    {
        $arProperties = [];

        if ($addAll) {
            array_unshift($arProperties, ['all' => Loc::getMessage('MB_CORE_COMP_PARAM_SELECT_ALL', null, null) ?: 'Все']);
        }

        if ($hasEmpty) {
            array_unshift($arProperties, ['0' => Loc::getMessage('MB_CORE_COMP_PARAM_NOTSELECTED', null, null) ?: 'Не выбрано']);
        }

        if ($iblockId) {
            $query = (new Query(\Bitrix\Iblock\PropertyTable::getEntity()));
            $conditionTree = new \Bitrix\Main\ORM\Query\Filter\ConditionTree();
            if ($type !== 'all' && $type !== '') {
                $conditionTree->where('PROPERTY_TYPE', $type);
            }
            $rows = $query
                ->setSelect(['CODE', 'NAME'])
                ->where('IBLOCK_ID', $iblockId)
                ->where($conditionTree)
                ->setCacheTtl(86400)
                ->fetchAll();
            foreach ($rows as $row) {
                $arProperties[$row['CODE']] = $row['NAME'] . ' [' . $row['CODE'] . ']';
            }
        }

        return $arProperties;
    }

    public static function getIblockSefModeTemplates(): array
    {
        return [
            'sections' => ['NAME' => 'Страница списка разделов', 'VARIABLES' => []],
            'section' => [
                'NAME' => 'Страница раздела',
                'VARIABLES' => ['SECTION_ID', 'SECTION_CODE', 'SECTION_CODE_PATH'],
            ],
            'detail' => [
                'NAME' => 'Детальная страница элемента',
                'VARIABLES' => ['SECTION_ID', 'SECTION_CODE', 'SECTION_CODE_PATH', 'ELEMENT_ID', 'ELEMENT_CODE'],
            ],
            'filter' => [
                'NAME' => 'Страница фильтра',
                'VARIABLES' => ['SECTION_ID', 'SECTION_CODE', 'SECTION_CODE_PATH', 'ELEMENT_ID', 'ELEMENT_CODE', 'SMART_FILTER_PATH'],
            ],
        ];
    }

    public static function getIblockSefModeVariableAliases(): array
    {
        return [
            'ELEMENT_ID' => ['NAME' => Loc::getMessage('IB_COMPLIB_POPUP_ELEMENT_ID', null, null) ?: 'ID элемента', 'TEMPLATE' => '#ELEMENT_ID#'],
            'ELEMENT_CODE' => ['NAME' => Loc::getMessage('IB_COMPLIB_POPUP_ELEMENT_CODE', null, null) ?: 'Код элемента', 'TEMPLATE' => '#ELEMENT_CODE#'],
            'SECTION_ID' => ['NAME' => Loc::getMessage('IB_COMPLIB_POPUP_SECTION_ID', null, null) ?: 'ID раздела', 'TEMPLATE' => '#SECTION_ID#'],
            'SECTION_CODE' => ['NAME' => Loc::getMessage('IB_COMPLIB_POPUP_SECTION_CODE', null, null) ?: 'Код раздела', 'TEMPLATE' => '#SECTION_CODE#'],
            'SECTION_CODE_PATH' => ['NAME' => Loc::getMessage('IB_COMPLIB_POPUP_SECTION_CODE_PATH', null, null) ?: 'Путь коду раздела', 'TEMPLATE' => '#SECTION_CODE_PATH#'],
            'SMART_FILTER_PATH' => ['NAME' => Loc::getMessage('IB_COMPLIB_POPUP_SECTION_CODE_PATH', null, null) ?: 'Путь фильтра', 'TEMPLATE' => '#SMART_FILTER_PATH#'],
        ];
    }
}
