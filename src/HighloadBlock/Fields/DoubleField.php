<?php

namespace MB\Bitrix\HighloadBlock\Fields;

use Bitrix\Main\UserField\Types\DoubleType;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasMultiple;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasRequired;

class DoubleField extends AbstractField
{
    use HasRequired;
    use HasMultiple;

    public function getUserType(): string
    {
        return DoubleType::USER_TYPE_ID;
    }

    public function setPrecision(int $scale): static
    {
        $this->settings['PRECISION'] = $scale;

        return $this;
    }

    public function setMin(float $value): static
    {
        $this->settings['MIN_VALUE'] = $value;

        return $this;
    }

    public function setMax(float $value): static
    {
        $this->settings['MAX_VALUE'] = $value;

        return $this;
    }

    public function setDefault(float $value): static
    {
        $this->settings['DEFAULT_VALUE'] = $value;

        return $this;
    }

    protected function prepareSettings(): array
    {
        $settings = [
            'SETTINGS' => $this->settings,
        ];

        return DoubleType::prepareSettings($settings);
    }
}

