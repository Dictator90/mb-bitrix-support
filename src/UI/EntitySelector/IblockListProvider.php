<?php

namespace MB\Bitrix\UI\EntitySelector;

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;
use Bitrix\UI\EntitySelector\Dialog;
use Bitrix\UI\EntitySelector\Item;
use Bitrix\UI\EntitySelector\BaseProvider;
use Bitrix\UI\EntitySelector\SearchQuery;

class IblockListProvider extends BaseProvider
{

    public const ENTITY_ID = 'iblock-list';
    protected const ELEMENTS_LIMIT = 100;

    public function __construct($options)
    {
        Loader::includeModule('iblock');
        parent::__construct();

        if (!$options['selected']) {
            $options['selected'] = [];
        } elseif (!is_array($options['selected'])) {
            $options['selected'] = [$options['selected']];
        }

        $options['moduleId'] = $options['moduleId'] ?: 'mb.core';

        $this->options = $options;
    }

    public function isAvailable(): bool
    {
        global $APPLICATION, $USER;
        $moduleGroupRights = $APPLICATION->GetGroupRight($this->options['moduleId']);

        return $USER->isAuthorized() && $moduleGroupRights >= 'R';
    }

    public function fillDialog(Dialog $dialog): void
    {
        if ($dialog->getItemCollection()->count() > 0)
        {
            foreach ($dialog->getItemCollection() as $item)
            {
                $dialog->addRecentItem($item);
            }
        }

        $recentItems = $dialog->getRecentItems()->getEntityItems(self::ENTITY_ID);
        $recentItemsCount = count($recentItems);

        if ($recentItemsCount < self::ELEMENTS_LIMIT)
        {
            $elements = $this->getElements(null, self::ELEMENTS_LIMIT);
            foreach ($elements as $element)
            {
                $dialog->addRecentItem($this->makeItem($element));
            }
        }
    }

    public function doSearch(SearchQuery $searchQuery, Dialog $dialog): void
    {
        $filter = [];

        $query = $searchQuery->getQuery();
        if ($query !== '') {
            $filter = $this->getQueryFilter($query);
        }

        $elements = $this->getElements($filter, self::ELEMENTS_LIMIT);
        if (count($elements) === self::ELEMENTS_LIMIT) {
            $searchQuery->setCacheable(false);
        }

        foreach ($elements as $element)
        {
            $dialog->addItem(
                $this->makeItem($element)
            );
        }
    }

    private function getQueryFilter(string $query): ConditionTree
    {
        return (new ConditionTree())
            ->logic('or')
            ->whereLike('NAME', '%'.$query.'%')
            ->whereLike('ID', $query.'%')
            ->whereLike('IBLOCK_TYPE_NAME', '%'.$query.'%');
    }

    protected function getElements(?ConditionTree $conditionTree = null, ?int $limit = null): array
    {
        $context = Context::getCurrent();
        $query = IblockTable::query()
            ->where('ACTIVE', true)
            ->where('TYPE_LID', $context->getLanguage())
            ->setSelect([
                'ID',
                'NAME',
                'IBLOCK_TYPE_ID',
                'IBLOCK_TYPE_NAME' => 'TYPE.LANG_MESSAGE.NAME',
                'TYPE_LID' => 'TYPE.LANG_MESSAGE.LANGUAGE_ID'
            ])
            ->addOrder('ID')
            ->setLimit($limit);

        if ($conditionTree) {
            $query->where($conditionTree);
        }

        return $query->fetchAll();
    }

    public function getItems(array $ids): array
    {
        $items = [];

        foreach ($this->getElements() as $element) {
            $items[] = $this->makeItem($element);
        }

        return $items;
    }

    protected function makeItem(array $element): Item
    {
        $itemParams = [
            'id' => $element['ID'] ?? null,
            'entityId' => self::ENTITY_ID,
            'title' => "[{$element['ID']}] " . ($element['NAME'] ?? null),
            'subtitle' => "[{$element['IBLOCK_TYPE_ID']}] " . ($element['IBLOCK_TYPE_NAME'] ?? null),
            'description' => null,
            'avatar' => null,
            'selected' => in_array($element['ID'], $this->options['selected']),
            'customData' => [
                'xmlId' => null,
            ],
        ];

        return new Item($itemParams);
    }
}
