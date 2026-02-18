<?php

namespace MB\Bitrix\Iblock;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\SiteTable;

class DetailUrl
{
    public static function getByElement(string|int $id, ?string $template = null, ?string $siteId = null): ?string
    {
        if (!self::checkDependency()) {
            return null;
        }

        $query = new Query(ElementTable::getEntity());

        self::addElementUrlDataToQuery($query);

        $query
            ->where('ID', $id)
            ->setCacheTtl(86400)
            ->cacheJoins(true);

        $data = $query->fetch();

        $replace = self::getElementReplace($data, $siteId);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $template ?: $data['DETAIL_PAGE_URL_SCHEMA']
        );
    }

    public static function getBySection(string|int $id, $template = null, ?string $siteId = null): ?string
    {
        if (!self::checkDependency()) {
            return null;
        }

        $query = new Query(SectionTable::getEntity());

        self::addSectionUrlDataToQuery($query);

        $query
            ->where('ID', $id)
            ->setCacheTtl(86400)
            ->cacheJoins(true);

        $data = $query->fetch();

        $replace = self::getSectionReplace($data, $siteId);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $template ?: $data['DETAIL_PAGE_URL_SCHEMA']
        );
    }

    public static function addElementUrlDataToQuery(Query $query): void
    {
        $query->setSelect([
            'ID',
            'CODE',
            'XML_ID',
            'IBLOCK_ID',
            'IBLOCK_TYPE_ID' => 'IBLOCK.IBLOCK_TYPE_ID',
            'IBLOCK_SECTION_ID',
            'SECTION_CODE_PATH',
            'DETAIL_PAGE_URL_SCHEMA' => 'IBLOCK.DETAIL_PAGE_URL',
            'SECTION_ID' => 'IBLOCK_SECTION.ID',
            'SECTION_CODE' => 'IBLOCK_SECTION.CODE'
        ]);
        $query->registerRuntimeField(
            new ExpressionField(
                'SECTION_CODE_PATH',
                '(
                    SELECT GROUP_CONCAT(s.CODE ORDER BY s.DEPTH_LEVEL SEPARATOR "/")
                      FROM b_iblock_section s
                      WHERE s.IBLOCK_ID = %s
                        AND s.LEFT_MARGIN <= %s
                        AND s.RIGHT_MARGIN >= %s
                )',
                ['IBLOCK_ID', 'IBLOCK_SECTION.LEFT_MARGIN', 'IBLOCK_SECTION.RIGHT_MARGIN']
            )
        );
    }

    public static function addSectionUrlDataToQuery(Query $query): void
    {
        $query->setSelect([
            'ID',
            'CODE',
            'XML_ID',
            'IBLOCK_ID',
            'IBLOCK_TYPE_ID' => 'IBLOCK.IBLOCK_TYPE_ID',
            'IBLOCK_SECTION_ID',
            'SECTION_CODE_PATH',
            'DETAIL_PAGE_URL_SCHEMA' => 'IBLOCK.SECTION_PAGE_URL',
            'SECTION_ID' => 'PARENT_SECTION.ID',
            'SECTION_CODE' => 'PARENT_SECTION.CODE'
        ]);

        $query->registerRuntimeField(
            new ExpressionField(
                'SECTION_CODE_PATH',
                '(
                    SELECT GROUP_CONCAT(s.CODE ORDER BY s.DEPTH_LEVEL SEPARATOR "/")
                      FROM b_iblock_section s
                      WHERE s.IBLOCK_ID = %s
                        AND s.LEFT_MARGIN <= %s
                        AND s.RIGHT_MARGIN >= %s
                )',
                ['IBLOCK_ID', 'PARENT_SECTION.LEFT_MARGIN', 'PARENT_SECTION.RIGHT_MARGIN']
            )
        );
    }

    public static function buildByElement(array $row, ?string $template = null, ?string $siteId = null): string
    {
        $replace = self::getElementReplace($row, $siteId);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $template ?: $row['DETAIL_PAGE_URL_SCHEMA']
        );
    }

    public static function buildBySection(array $row, ?string $template = null, ?string $siteId = null): string
    {
        $replace = self::getSectionReplace($row, $siteId);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $template ?: $row['DETAIL_PAGE_URL_SCHEMA']
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param string|null $siteId
     * @return array<string, string>
     */
    protected static function getElementReplace(array $data, $siteId = null): array
    {
        $result = self::getBaseReplace($data, $siteId);
        return $result + ['#ELEMENT_CODE#' => $data['CODE'] ?? ''];
    }

    /**
     * @param array<string, mixed> $data
     * @param string|null $siteId
     * @return array<string, string>
     */
    protected static function getSectionReplace(array $data, $siteId = null): array
    {
        return self::getBaseReplace($data, $siteId);
    }

    /**
     * @param array<string, mixed> $data
     * @param string|null $siteId
     * @return array<string, string>
     */
    protected static function getBaseReplace(array $data, $siteId = null): array
    {
        $result = [
            '#CODE#' => $data['CODE'] ?? '',
            '#ID#' => $data['ID'] ?? '',
            '#IBLOCK_ID#' => $data['IBLOCK_ID'] ?? '',
            '#IBLOCK_TYPE_ID#' => $data['IBLOCK_TYPE_ID'] ?? '',
            '#ELEMENT_ID#' => $data['ID'] ?? '',
            '#EXTERNAL_ID#' => $data['XML_ID'] ?? '',
            '#SECTION_ID#' => $data['SECTION_ID'] ?? '',
            '#SECTION_CODE#' => $data['SECTION_ID'] ?? '',
            '#SECTION_CODE_PATH#' => $data['SECTION_CODE_PATH'] ?? ''
        ];

        if (!$siteId) {
            $site = Application::getInstance()->getContext()->getSiteObject();
        } else {
            $site = SiteTable::wakeUpObject($siteId);
        }
        if ($site) {
            $result['#SERVER_NAME#'] = $site->getServerName();
            $result['#SITE_DIR#'] = $site->getDocRoot();
        }

        return $result;
    }

    protected static function checkDependency(): bool
    {
        return (bool) Loader::includeModule('iblock');
    }
}
