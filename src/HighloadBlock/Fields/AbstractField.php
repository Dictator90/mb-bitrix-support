<?php

namespace MB\Bitrix\HighloadBlock\Fields;

use Bitrix\Main\SystemException;

abstract class AbstractField
{
    public const EDIT_FORM_LABEL = 'edit_form_label';
    public const LIST_COLUMN_LABEL = 'list_column_label';
    public const LIST_FILTER_LABEL = 'list_filter_label';

    protected array $labels = [
        self::EDIT_FORM_LABEL => ['ru' => '', 'en' => ''],
        self::LIST_COLUMN_LABEL => ['ru' => '', 'en' => ''],
        self::LIST_FILTER_LABEL => ['ru' => '', 'en' => ''],
    ];

    protected array $settings = [];

    protected bool $isSearchable = false;

    protected bool $showInList = true;

    protected bool $editInList = true;

    protected bool $showInFilter = true;

    abstract public function getUserType(): string;

    /**
     * @throws SystemException
     */
    public function __construct(protected string $name)
    {
        if ($name === '') {
            throw new SystemException('Field name required');
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName($name): static
    {
        $this->name = $name;
        return $this;
    }

    public function setLabelMessages(array $labels): static
    {
        $this->labels[self::EDIT_FORM_LABEL] = $labels;
        $this->labels[self::LIST_COLUMN_LABEL] = $labels;
        $this->labels[self::LIST_FILTER_LABEL] = $labels;

        return $this;
    }

    public function configureEditFormLabel(array $value): static
    {
        $this->labels[self::EDIT_FORM_LABEL] = $value;
        return $this;
    }

    public function getEditFormLabel()
    {
        return $this->labels[self::EDIT_FORM_LABEL];
    }

    public function configureListColumnLabel(array $value): static
    {
        $this->labels[self::LIST_COLUMN_LABEL] = $value;
        return $this;
    }

    public function getListColumnLabel()
    {
        return $this->labels[self::LIST_COLUMN_LABEL];
    }

    public function configureListFilterLabel(array $value): static
    {
        $this->labels[self::LIST_FILTER_LABEL] = $value;
        return $this;
    }

    public function getListFilterLabel()
    {
        return $this->labels[self::LIST_FILTER_LABEL];
    }

    public function isSearchable(): bool
    {
        return $this->isSearchable;
    }

    public function configureSearchable(bool $value = true): static
    {
        $this->isSearchable = $value;
        return $this;
    }

    public function isShowInList(): bool
    {
        return $this->showInList;
    }

    public function configureShowInList(bool $value = true): static
    {
        $this->showInList = $value;
        return $this;
    }

    public function isEditInList(): bool
    {
        return $this->editInList;
    }

    public function configureEditInList(bool $value = true): static
    {
        $this->editInList = $value;
        return $this;
    }

    public function isShowInFilter(): bool
    {
        return $this->showInFilter;
    }

    public function configureShowInFilter(bool $value = true): static
    {
        $this->showInFilter = $value;
        return $this;
    }

    public function getSettings(): array
    {
        return $this->prepareSettings();
    }

    public function setSettings(array $settings): static
    {
        $this->settings = $settings;
        return $this;
    }

    protected function prepareSettings(): array
    {
        return $this->settings;
    }
}
