<?php

namespace MB\Bitrix\Iblock\UserType\DialogSelector;

use Bitrix\Main\Loader;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;
use MB\Bitrix\Iblock\UserType\Base as UserTypeBase;

/**
 * Абстрактный класс для реализации пользовательских свойств в стиле диалога выбора сущностей
 */
abstract class Base extends UserTypeBase
{
    /**
     * Массив элементов для диалога выбора сущностей (ItemOptions)
     *
     * @return array
     */
    abstract protected static function getItems(): array;

    public static function getPropertyType(): string
    {
        return 'S';
    }

    public static function getPropertyFieldHtml($arProperty, $value, $strHTMLControlName)
    {
        if (!static::checkDependence()) {
            return static::getDependenceErrorMessage();
        }

        if (($strHTMLControlName['VALUE'] ?? '') === 'PROPERTY_DEFAULT_VALUE' && ($arProperty['MULTIPLE'] ?? '') === 'Y') {
            return static::getPropertyFieldHtmlMulty($arProperty, $value, $strHTMLControlName);
        }

        $html = '';
        if (Loader::includeModule('ui')) {
            $input = !empty($value['VALUE']) ? "<input type='hidden' name='{$strHTMLControlName['VALUE']}' value='{$value['VALUE']}'>" : '';
            $jsScript = static::getTagSelectorScript($arProperty, $value, $strHTMLControlName);
            $html = "
            <div id=\"selector_{$arProperty['ID']}\"></div>
            <div id=\"inputs_{$arProperty['ID']}\">{$input}</div>
            {$jsScript}
            ";
        }

        return $html;
    }

    public static function getPropertyFieldHtmlMulty($arProperty, $values, $strHTMLControlName)
    {
        if (!static::checkDependence()) {
            return static::getDependenceErrorMessage();
        }

        $html = '';
        if (Loader::includeModule('ui')) {
            $inputs = '';
            if (($strHTMLControlName['VALUE'] ?? '') === 'PROPERTY_DEFAULT_VALUE') {
                foreach (($values['VALUE'] ?? []) as $val) {
                    if ($val) {
                        $inputs .= "<input type='hidden' name='{$strHTMLControlName['VALUE']}[]' value='{$val}'>";
                    }
                }
            } else {
                foreach ($values as $val) {
                    if (!empty($val['VALUE'])) {
                        $inputs .= "<input type='hidden' name='{$strHTMLControlName['VALUE']}[]' value='{$val['VALUE']}'>";
                    }
                }
            }

            $jsScript = static::getTagSelectorScript($arProperty, $values, $strHTMLControlName);
            $html = "
            <div id=\"selector_{$arProperty['ID']}\"></div>
            <div id=\"inputs_{$arProperty['ID']}\">{$inputs}</div>
            {$jsScript}
            ";
        }
        return $html;
    }

    public static function getPublicViewHTML($arProperty, $value, $strHTMLControlName)
    {
        return is_array($value['VALUE'] ?? null) ? implode(', ', $value['VALUE']) : (string) ($value['VALUE'] ?? '');
    }

    public static function getTagSelectorScript($arProperty, $value, $strHTMLControlName): string
    {
        Extension::load('ui.entity-selector');
        $rawItems = static::getItems();
        if ($value) {
            foreach ($rawItems as &$item) {
                if (($arProperty['MULTIPLE'] ?? '') === 'Y') {
                    if (($strHTMLControlName['VALUE'] ?? '') === 'PROPERTY_DEFAULT_VALUE') {
                        foreach (($value['VALUE'] ?? []) as $va) {
                            if ($va && ($item['id'] ?? null) == $va) {
                                $item['selected'] = true;
                                break;
                            }
                        }
                    } else {
                        foreach ($value as $va) {
                            if (!empty($va['VALUE']) && ($item['id'] ?? null) == $va['VALUE']) {
                                $item['selected'] = true;
                                break;
                            }
                        }
                    }
                } else {
                    if (($item['id'] ?? null) == ($value['VALUE'] ?? null)) {
                        $item['selected'] = true;
                    }
                }
            }
        }

        $items = Json::encode($rawItems);
        $multiple = Json::encode(($arProperty['MULTIPLE'] ?? '') === 'Y');

        return "<script>
            new BX.UI.EntitySelector.TagSelector({
                id: '{$arProperty['ID']}',
                multiple: {$multiple},
                dialogOptions: {
                    dropdownMode: true,
                    items: {$items},
                },
                events: {
                    onAfterTagAdd: (event) => {
                        let InputContainer = BX('inputs_{$arProperty['ID']}');
                        if (InputContainer) {
                            InputContainer.appendChild(BX.create('input', {
                                props: {
                                    type: 'hidden',
                                    name: event.target.multiple ? '{$strHTMLControlName['VALUE']}[]' : '{$strHTMLControlName['VALUE']}',
                                    value: event.data.tag.id
                                }
                            }))
                        }
                    },
                    onAfterTagRemove: function(event) {
                        let InputContainer = BX('inputs_{$arProperty['ID']}');
                        if (InputContainer) {
                            let needleName = event.target.multiple ? '{$strHTMLControlName['VALUE']}[]' : '{$strHTMLControlName['VALUE']}';
                            let el = InputContainer.querySelector('[name=\"'+needleName+'\"]');
                            if (el) el.remove();
                        }
                    },
                }
            }).renderTo(document.getElementById('selector_{$arProperty['ID']}'));
</script>";
    }
}
