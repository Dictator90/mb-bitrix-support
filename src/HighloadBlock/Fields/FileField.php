<?php

namespace MB\Bitrix\HighloadBlock\Fields;

use Bitrix\Main\UserField\Types\FileType;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasMultiple;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasRequired;

class FileField extends AbstractField
{
    use HasRequired;
    use HasMultiple;

    public function getUserType(): string
    {
        return FileType::USER_TYPE_ID;
    }

    /**
     * Устанавливает допустимые расширения файлов (например ['jpg','png']).
     */
    public function setExtensions(array $extensions): static
    {
        $this->settings['EXTENSIONS'] = $extensions;

        return $this;
    }

    /**
     * Устанавливает максимальный размер файла в байтах.
     */
    public function setMaxSize(int $bytes): static
    {
        $this->settings['MAX_SIZE'] = $bytes;

        return $this;
    }

    protected function prepareSettings(): array
    {
        $settings = [
            'SETTINGS' => $this->settings,
        ];

        return FileType::prepareSettings($settings);
    }
}

