<?php

namespace MB\Bitrix\UI\Control\Field;

class ImageInputField extends FileInputField
{
    public function getHtml(): string
    {
        // todo: возможно чтото другое?
        global $APPLICATION;

        ob_start();
        $APPLICATION->IncludeComponent(
            'bitrix:ui.image.input',
            '',
            [
                'FILE_SETTINGS' => [
                    'id' => $this->getName() . '_#IND#',
                    'name' => $this->getName() . '[#IND#]',
                    'description' => true,
                    'delete' => true,
                    'edit' => true,
                    'upload' => true,
                    'maxCount' => 3,
                    'medialib' => true,
                    'fileDialog' => true,
                ],
                'FILE_VALUES' => !is_array($this->getValue()) ? [$this->getValue()] : $this->getValue()
            ],
        );

        return ob_get_clean();
    }
}
