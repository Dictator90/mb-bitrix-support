<?php

namespace MB\Bitrix\HighloadBlock\Fields;

use Bitrix\Main\UserField\Types\EnumType;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasMultiple;
use MB\Bitrix\HighloadBlock\Fields\Traits\HasRequired;

class EnumField extends AbstractField
{
    use HasRequired;
    use HasMultiple;

    /**
     * Хранит значения перечисления в виде:
     * [
     *   ['VALUE' => '...', 'XML_ID' => '...', 'SORT' => 100, 'DEF' => 'N'],
     *   ...
     * ]
     */
    protected array $values = [];

    public function getUserType(): string
    {
        return EnumType::USER_TYPE_ID;
    }

    public function addOption(string $value, ?string $xmlId = null, int $sort = 100, bool $isDefault = false): static
    {
        $this->values[] = [
            'VALUE' => $value,
            'XML_ID' => $xmlId ?: $value,
            'SORT' => $sort,
            'DEF' => $isDefault ? 'Y' : 'N',
        ];

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $options
     */
    public function setOptions(array $options): static
    {
        $this->values = $options;

        return $this;
    }

    protected function prepareSettings(): array
    {
        $settings = [
            'VALUES' => $this->values,
            'MULTIPLE' => $this->isMultiple ? 'Y' : 'N',
        ];

        return EnumType::prepareSettings($settings);
    }
}

