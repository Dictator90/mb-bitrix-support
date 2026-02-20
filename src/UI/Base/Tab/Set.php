<?php

namespace MB\Bitrix\UI\Base\Tab;

use MB\Bitrix\Contracts\UI\Renderable;

abstract class Set
    implements Renderable
{

    abstract protected function getTabsetHeaderHtml(): string;
    abstract protected function getTabsetContentHtml(): string;

    abstract protected function getTabsetStartHtml(): string;
    abstract protected function getTabsetEndHtml(): string;

    /**
     *
     * @var Base[]|null
     */
    protected ?array $tabs = null;

    public function __construct(array $tabs = null)
    {
        $this->tabs = $tabs;
    }

    /**
     * @param Base[] $tabs
     * @return void
     */
    public function setTabs(array $tabs): static
    {
        $this->tabs = $tabs;
        return $this;
    }

    public function addTab(Base $tab): static
    {
        $this->tabs[] = $tab;
        return $this;
    }

    public function getTabs()
    {
        return $this->tabs;
    }

    public function isEmpty(): bool
    {
        return empty($this->tabs);
    }

    public function render(): void
    {
        echo $this->getHtml();
    }

    public function getHtml(): string
    {
        if ($this->isEmpty()) {
            return '';
        }
        return $this->getTabsetStartHtml()
            . $this->getTabsetHeaderHtml()
            . $this->getTabsetContentHtml()
            . $this->getTabsetEndHtml();
    }

    protected function getTabsHeaderHtml(): string
    {
        $result = '';
        foreach ($this->getTabs() as $tab) {
            $result .= $tab->getTabHtml();
        }
        return $result;
    }

    protected function getTabsContentHtml(): string
    {
        $result = '';
        foreach ($this->getTabs() as $tab) {
            $result .= $tab->getTabContentHtml();
        }
        return $result;
    }

    public function toArray()
    {
        $result = [];
        foreach ($this->getTabs() as $tab) {
            $result[] = $tab->toArray();
        }

        return $result;
    }
}
