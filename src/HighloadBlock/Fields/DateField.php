<?php

namespace MB\Bitrix\HighloadBlock\Fields;

use Bitrix\Main\UserField\Types\DateType;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasMultiple;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasRequired;

class DateField extends AbstractField
{
    use HasRequired;
    use HasMultiple;

    public function getUserType(): string
    {
        return DateType::USER_TYPE_ID;
    }

    /**
     * Устанавливает строковое значение по умолчанию (формат Bitrix, например YYYY-MM-DD).
     */
    public function setDefault(string $value): static
    {
        $this->settings['DEFAULT_VALUE'] = $value;

        return $this;
    }

    /**
     * Использовать текущую дату в качестве значения по умолчанию.
     */
    public function useCurrentDate(bool $flag = true): static
    {
        $this->settings['USE_GOOGLE_CALENDAR'] = $flag ? 'Y' : 'N';

        return $this;
    }

    protected function prepareSettings(): array
    {
        $settings = [
            'SETTINGS' => $this->settings,
        ];

        return DateType::prepareSettings($settings);
    }
}

