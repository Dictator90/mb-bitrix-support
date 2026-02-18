<?php

namespace MB\Bitrix\HighloadBlock\Fields;

use Bitrix\Main\UserField\Types\StringType;

class StringField extends AbstractField
{
    public function getUserType(): string
    {
        return StringType::USER_TYPE_ID;
    }

    public function setSize(int $value): static
    {
        $this->settings['SIZE'] = $value;
        return $this;
    }

    protected function prepareSettings(): array
    {
        $settings = [];
        return StringType::prepareSettings($settings);
    }
}
