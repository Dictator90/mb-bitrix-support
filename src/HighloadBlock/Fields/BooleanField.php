<?php

namespace MB\Bitrix\HighloadBlock\Fields;

use Bitrix\Main\UserField\Types\BooleanType;

class BooleanField extends AbstractField
{
    public function getUserType(): string
    {
        return BooleanType::USER_TYPE_ID;
    }

    public function setDisplayType(string $type): static
    {
        if (in_array($type, [BooleanType::DISPLAY_CHECKBOX, BooleanType::DISPLAY_RADIO, BooleanType::DISPLAY_DROPDOWN])) {
            $this->settings['DISPLAY'] = $type;
        }

        return $this;
    }

    public function getDisplayType(): string
    {
        return $this->settings['DISPLAY'] ?? BooleanType::DISPLAY_CHECKBOX;
    }

    public function setDefaultValue(int $value): static
    {
        $this->settings['DEFAULT_VALUE'] = $value > 0 ? 1 : 0;
        return $this;
    }

    public function getDefaultValue(): int
    {
        return $this->settings['DEFAULT_VALUE'] ?? 0;
    }

    public function setLabel(array $label): static
    {
        $this->settings['LABEL'] = array_values($label);
        return $this;
    }

    public function getLabel(): array
    {
        return $this->settings['LABEL'] ?? ['', ''];
    }

    public function setLabelCheckbox(string $value): static
    {
        $this->settings['LABEL_CHECKBOX'] = $value;
        return $this;
    }

    public function getLabelCheckbox(): string
    {
        return $this->settings['LABEL_CHECKBOX'] ?? '';
    }

    protected function prepareSettings(): array
    {
        $settings = [
            'SETTINGS' => [
                'DEFAULT_VALUE' => $this->getDefaultValue(),
                'DISPLAY' => $this->getDisplayType(),
                'LABEL' => $this->getLabel(),
                'LABEL_CHECKBOX' => $this->getLabelCheckbox()
            ]
        ];

        return BooleanType::prepareSettings($settings);
    }
}
