<?php

namespace MB\Bitrix\HighloadBlock\Fields;

use Bitrix\Main\UserField\Types\IntegerType;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasMultiple;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasRequired;

class IntegerField extends AbstractField
{
    use HasRequired;
    use HasMultiple;

    public function getUserType(): string
    {
        return IntegerType::USER_TYPE_ID;
    }

    public function setSize(int $value): static
    {
        $this->settings['SIZE'] = $value;

        return $this;
    }

    public function setMin(int $value): static
    {
        $this->settings['MIN_VALUE'] = $value;

        return $this;
    }

    public function setMax(int $value): static
    {
        $this->settings['MAX_VALUE'] = $value;

        return $this;
    }

    public function setDefault(int $value): static
    {
        $this->settings['DEFAULT_VALUE'] = $value;

        return $this;
    }

    protected function prepareSettings(): array
    {
        $settings = [
            'SETTINGS' => $this->settings,
        ];

        return IntegerType::prepareSettings($settings);
    }
}

