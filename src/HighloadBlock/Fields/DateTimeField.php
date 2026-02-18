<?php

namespace MB\Bitrix\HighloadBlock\Fields;

use Bitrix\Main\UserField\Types\DateTimeType;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasMultiple;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasRequired;

class DateTimeField extends AbstractField
{
    use HasRequired;
    use HasMultiple;

    public function getUserType(): string
    {
        return DateTimeType::USER_TYPE_ID;
    }

    /**
     * Устанавливает строковое значение по умолчанию (формат Bitrix, например YYYY-MM-DD HH:MI:SS).
     */
    public function setDefault(string $value): static
    {
        $this->settings['DEFAULT_VALUE'] = $value;

        return $this;
    }

    /**
     * Использовать текущее дата‑время как значение по умолчанию.
     */
    public function useCurrentDateTime(bool $flag = true): static
    {
        $this->settings['USE_GOOGLE_CALENDAR'] = $flag ? 'Y' : 'N';

        return $this;
    }

    protected function prepareSettings(): array
    {
        $settings = [
            'SETTINGS' => $this->settings,
        ];

        return DateTimeType::prepareSettings($settings);
    }
}

