<?php

namespace MB\Core\Settings\Page;

use Bitrix\Main\Context;
use Bitrix\Main\HttpRequest;
use MB\Core\Log\FileLogger;
use MB\Core\Module\ModuleContainer;
use MB\Core\Module\ModuleEntity;
use MB\Core\Settings\Page\Entity\Base;
use Illuminate\Support\Collection;

class PageManager
{
    protected const PAGE_URI_PARAM = 'page';

    protected Collection $pages;
    protected HttpRequest $request;

    public function __construct(protected ModuleEntity $module)
    {
        $this->pages = PageRegistry::create($module)->getEntities();
        $this->request = Context::getCurrent()->getRequest();
    }

    /**
     * @return Page404
     */
    public function getCurrentPage(): Entity\Base
    {
        $pageUri = $this->request->getQuery(self::PAGE_URI_PARAM);
        if (!$pageUri || $this->pages->isEmpty()) {
            return new Page404($this->module);
        }
        $page = $this->pages->where(function ($class) use ($pageUri) {
            /** @var Base $class */
            return $class::getId() == $pageUri;
        });

        if ($page->isEmpty()) {
            return new Page404($this->module);
        }

        $page = $page->getIterator()->current();
        if (!$this->checkPage($page)) {
            return new Page404($this->module);
        }

        return new $page($this->module);
    }

    public function getPages(): Collection
    {
        return $this->pages;
    }

    public function getPagesArray(): array
    {
        return $this->getPages()->toArray();
    }

    public function getMenuPages($baseUrl = '/bitrix/admin/mb_core_settings.php')
    {
        $result = [];
        $parents = [];
        $pageUriParam = self::PAGE_URI_PARAM;

        foreach ($this->getPagesArray() as $class) {
            /** @var Base $class */
            if (!$this->checkPage($class)) {
                continue;
            }

            if ($parentMenuClass = $class::getParentMenuClass()) {
                if (class_exists($parentMenuClass)) {
                    $parents[] = $parentMenuClass::getId();
                    if(!$result[$parentMenuClass::getId()]) {
                        $result[$parentMenuClass::getId()] = [
                            'text' => $parentMenuClass::getText(),
                            'sort' => $parentMenuClass::getSort(),
                            'items_id' => $parentMenuClass::getId(),
                            'more_url' => [],
                            'items' => []
                        ];
                    }

                    $result[$parentMenuClass::getId()]['items'][] = [
                        'text' => $class::getTitle(),
                        'title' => $class::getTitle(),
                        'url' => static::getPageUrl($class, $baseUrl),
                        'icon' => $class::getMenuIcon(),
                        'sort' => $class::getSort(),
                    ];
                }
            } else {
                $result[] = [
                    'text' => $class::getTitle(),
                    'title' => $class::getTitle(),
                    'url' => static::getPageUrl($class, $baseUrl),
                    'icon' => $class::getMenuIcon(),
                    'sort' => $class::getSort()
                ];
            }

            if ($parents) {
                foreach ($parents as $parentId) {
                    $result[$parentId]['items'] =
                        collect($result[$parentId]['items'])
                            ->sortBy('sort')
                            ->values()
                            ->all();
                }
            }

        }

        return
            collect($result)
                ->sortBy('sort')
                ->values()
                ->all();
    }

    protected function checkPage($pageClass)
    {
        return !$pageClass::isSystem() && $pageClass::isActive();
    }

    public static function getPageUrl($pageClass, string $baseUrl): string
    {
        $pageUriParam = self::PAGE_URI_PARAM;
        $startUrl = "{$baseUrl}?{$pageUriParam}";

        if (class_exists($pageClass) && is_subclass_of($pageClass, Base::class)) {
            return "$startUrl={$pageClass::getId()}";
        }

        return $startUrl;
    }
}
