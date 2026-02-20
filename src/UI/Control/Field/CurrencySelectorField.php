<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Currency\CurrencyManager;
use Bitrix\Main\Loader;

class CurrencySelectorField extends DialogSelectorField
{
    protected static function getItems(): array
    {
        $result = [];
        if (!Loader::includeModule('sale')) {
            return $result;
        }

        $rows = CurrencyManager::getCurrencyList();
        foreach ($rows as $id => $name) {
            $result[] = [
                'id' => $id,
                'entityId' => 'mbDialogEntity',
                'title' => $name,
                'tabs' => 'currency'
            ];
        }

        return $result;
    }

    protected static function getTabs(): array
    {
        return [
            [
                'id' => 'currency',
                'title' => 'Валюты'
            ]
        ];
    }
}
