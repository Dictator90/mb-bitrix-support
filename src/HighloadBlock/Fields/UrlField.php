<?php

namespace MB\Bitrix\HighloadBlock\Fields;

use Bitrix\Main\UserField\Types\UrlType;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasMultiple;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasRequired;

class UrlField extends AbstractField
{
    use HasRequired;
    use HasMultiple;

    public function getUserType(): string
    {
        return UrlType::USER_TYPE_ID;
    }

    public function setSize(int $value): static
    {
        $this->settings['SIZE'] = $value;

        return $this;
    }

    /**
     * Открывать ссылку в новом окне (если поддерживается конкретной реализацией UrlType).
     */
    public function openInNewWindow(bool $flag = true): static
    {
        $this->settings['TARGET_BLANK'] = $flag ? 'Y' : 'N';

        return $this;
    }

    protected function prepareSettings(): array
    {
        $settings = [
            'SETTINGS' => $this->settings,
        ];

        return UrlType::prepareSettings($settings);
    }
}

