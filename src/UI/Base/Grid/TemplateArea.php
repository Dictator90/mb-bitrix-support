<?php

namespace MB\Bitrix\UI\Base\Grid;

class TemplateArea
{
    /**
     * @var TemplateCollection[]
     */
    protected array $rows = [];

    protected array $uniqueIds = [];

    public function __construct($rows = [])
    {
        foreach ($rows as $row) {
            if (is_string($row)) {
                $this->addRowString($row);
            }
        }
    }

    public function addRowString(string $row)
    {
        $row = trim($row);
        $arRow = explode(' ', $row);
        $templateCollection = new TemplateCollection();
        foreach ($arRow as $template) {
            $template = trim($template);
            if ($template !== '.' && !in_array($template, $this->uniqueIds)) {
                $this->uniqueIds[] = $template;
            }

            $templateCollection->createItem([
                'ID' => $template
            ]);
        }

        $this->rows[] = $templateCollection;

        return $this;
    }

    /**
     * @return array
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getUniqueIds()
    {
        return $this->uniqueIds;
    }

    public function isEmpty(): bool
    {
        return empty($this->rows);
    }
}
