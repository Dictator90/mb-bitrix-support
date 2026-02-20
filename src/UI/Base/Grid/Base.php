<?php

namespace MB\Bitrix\UI\Base\Grid;

use MB\Bitrix\UI;
use MB\Bitrix\UI\Traits\HasId;

class Base extends \MB\Bitrix\UI\Base\View
{
    use HasId;

    protected ?TemplateArea $area = null;

    public function __construct(TemplateArea $area)
    {
        $this->area = $area;
        parent::__construct();
    }

    public function showCss($withTag = true): void
    {
        if ($this->area->isEmpty()) {
            return;
        }

        parent::showCss();
    }

    public function getCss(): array
    {
        $rowsString = [];
        foreach ($this->area->getRows() as $row) {
            $rowsString[] = '"' . $row->getString() . '"';
        }

        $result = [
            '.mb-core-grid' => [
                'display' => 'grid',
                'grid-template-areas' => implode("\n", $rowsString)
            ]
        ];

        foreach ($this->area->getUniqueIds() as $id) {
            $result["#" . $id]["grid-area"] = $id;
        }

        return $result;
    }

    public function getDefaultCss(): array
    {
        return [
            '.mb-core-grid__item' => ['padding' => '14px']
        ];
    }


}
