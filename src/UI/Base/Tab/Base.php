<?php

namespace MB\Bitrix\UI\Base\Tab;

use Bitrix\Main\Context;
use MB\Bitrix\Contracts\UI\Renderable;
use MB\Bitrix\UI\Traits\HasActive;
use MB\Bitrix\UI\Traits\HasDescription;
use MB\Bitrix\UI\Traits\HasEnabled;
use MB\Bitrix\UI\Traits\HasId;
use MB\Bitrix\UI\Traits\HasLabel;
use MB\Bitrix\UI\Base\Form;
use MB\Bitrix\UI\Base\Row;

abstract class Base
    implements Renderable
{
    use HasId;
    use HasLabel;
    use HasDescription;
    use HasActive;
    use HasEnabled;

    abstract public function getTabHtml(): string;
    abstract public function getTabContentHtml(): string;

    protected Renderable $parent;
    protected Form\Base $form;

    /**
     * @var Row\Base[] $rows
     */
    protected array $rows = [];

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->activeProcess();
    }

    public function activeProcess()
    {
        if ($activeTabs = Context::getCurrent()->getRequest()->get('activeTab')) {
            $activeTabs = explode('|', $activeTabs);
            if (in_array($this->id, $activeTabs)) {
                $this->configureActive();
            }
        }
    }

    public function setParent(Renderable $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return \MB\Bitrix\Contracts\UI\Renderable
     */
    public function getParent(): Renderable
    {
        return $this->parent;
    }

    /**
     * @param Form\Base $form
     * @return static
     */
    public function setForm(Form\Base $form): static
    {
        $this->form = $form;
        return $this;
    }

    public function addRow(Row\Base $row)
    {
        if ($row->isEnabled()) {
            $this->rows[] = $row;
        }
        return $this;
    }

    public function setRows(array $rows)
    {
        foreach ($rows as $row) {
            if ($row instanceof Row\Base) {
                $this->addRow($row);
            }
        }

        return $this;
    }

    public function getRows()
    {
        return $this->rows;
    }

    public function render(): void
    {
        echo $this->getHtml();
    }

    public function getHtml(): string
    {
        $result = '';

        if (!$this->isEnabled()) {
            return $result;
        }

        foreach ($this->rows as $row) {
            if ($row->hasConditionActions()) {
                $row->doConditionActions();
            }

            if ($row->isEnabled()) {
                $result .= $row->getHtml();
            }
        }
        return $result;
    }

    public function toArray()
    {
        $result = [
            'id' => $this->getId(),
            'title' => $this->getLabel(),
            'description' => $this->getDescription(),
            'selected' => $this->isActive(),
            'rows' => []
        ];

        foreach ($this->rows as $row) {
            $result['rows'][] = $row->toArray();
        }

        return $result;
    }
}
