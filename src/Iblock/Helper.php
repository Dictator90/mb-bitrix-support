<?php

namespace MB\Bitrix\Iblock;

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Error;
use Bitrix\Main\ORM\Objectify\EntityObject;
use Bitrix\Main\Result;
use Exception;

class Helper
{
    public static function hasApiCode($iblockId, $cacheTtl = 86400): bool
    {
        $iblock = IblockTable::query()
            ->setSelect(['ID', 'CODE', 'API_CODE'])
            ->where('ID', $iblockId)
            ->setCacheTtl($cacheTtl)
            ->fetchObject();

        return (bool) $iblock->get('API_CODE');
    }

    public static function issetOrSetApiCode($iblockId, $cacheTtl = 86400): Result
    {
        $result = new Result();
        try {
            /** @var EntityObject $iblock */
            $iblock = IblockTable::query()
                ->setSelect(['ID', 'CODE', 'API_CODE'])
                ->where('ID', $iblockId)
                ->setCacheTtl($cacheTtl)
                ->fetchObject();

            if (!$iblock->get('API_CODE')) {
                $code = $iblock->get('CODE');
                if (!$code || !preg_match('/^[a-z][a-z0-9]{0,49}$/i', (string) $code)) {
                    $code = 'iblock' . $iblockId;
                }

                $iblock->set('API_CODE', $code);
                $res = $iblock->save();
                if (!$res) {
                    $result->addErrors($res->getErrors());
                }
            }
        } catch (Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }

        return $result;
    }
}
