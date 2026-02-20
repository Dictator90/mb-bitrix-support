<?php

namespace MB\Bitrix\UI\Control\Row;

use MB\Bitrix\UI\Control;

class TextInputRow extends InputRow
{
    public function __construct(string $name, array $params = [])
    {
        $controlField = (new Control\Field\TextField($name));

        if ($params['defaultValue']) {
            $controlField->setDefaultValue($params['defaultValue']);
        }

        if ($params['minLength']) {
            $controlField->setMinlength(intval($params['minLength']));
        }

        if ($params['maxLength']) {
            $controlField->setMaxlength(intval($params['maxLength']));
        }

        if ($params['size']) {
            $controlField->setSize($params['size']);
        }

        if ($params['style']) {
            $controlField->setStyle($params['style']);
        }

        if ($params['placeholder']) {
            $controlField->setPlaceholder($params['placeholder']);
        }

        $params = [
            'field' => $controlField,
            'label' => $params['label'] ?: null,
            'hint' => $params['hint'] ?: null,
        ];

        parent::__construct($params);
    }
}
