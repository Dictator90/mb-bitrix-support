<?php

namespace MB\Core\Settings\Page;

use Bitrix\Main\EventResult;
use Exception;
use Illuminate\Support\Collection;
use MB\Core\Module\ModuleContainer;
use MB\Core\Module\ModuleEntity;
use MB\Core\Support\Finder\ClassFinder;
use MB\Core\Support\Traits\BitrixEventsObservableTrait;

class PageRegistry
{
    use BitrixEventsObservableTrait;

    const ON_GET_PAGES_ENTITY_EVENT = 'onGetPagesEntity';

    public function __construct(protected ModuleEntity $module)
    {
        $this->attach($this->module->getId(), self::ON_GET_PAGES_ENTITY_EVENT);
    }

    public static function create(ModuleEntity $module)
    {
        return new self($module);
    }

    public static function createFromModuleId(string $moduleId): static
    {
        return new self(app()->container($moduleId)->module());
    }

    /**
     * @throws Exception
     */
    public function getEntities(): Collection
    {
        $result = ClassFinder::findExtended(
            $this->module->getLibPath(),
            $this->module->getNamespace(),
            Entity\Base::getClassName()
        );

        return collect(self::onGetPagesEntityEvent($result));
    }

    protected function onGetPagesEntityEvent($entities)
    {
        $result = $entities;

        $this->notify(
            self::ON_GET_PAGES_ENTITY_EVENT,
            ['entities' => $entities],
            function ($results) use (&$result) {
                foreach ($results as $res) {
                    if ($res->getType() == EventResult::ERROR) {
                        continue;
                    }

                    $params = $result->getParameters();
                    if ($params['entities'] && is_array($params['entities'])) {
                        $result = $params['entities'];
                    }
                }
            }
        );

        return $result;
    }
}
