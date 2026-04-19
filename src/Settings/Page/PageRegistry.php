<?php

namespace MB\Core\Settings\Page;

use Bitrix\Main\EventResult;
use Exception;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Filesystem\Filesystem;
use MB\Bitrix\Traits\BitrixEventsObservableTrait;
use MB\Support\Collection;

class PageRegistry
{
    use BitrixEventsObservableTrait;

    const ON_GET_PAGES_ENTITY_EVENT = 'onGetPagesEntity';

    public function __construct(protected ModuleEntityContract $module)
    {
        $this->attach($this->module->getId(), self::ON_GET_PAGES_ENTITY_EVENT);
    }

    public static function create(ModuleEntityContract $module)
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
        $libPath = $this->module->getLibPath();
        $result = $libPath !== null
            ? array_column(
                Filesystem::classFinder()->extends($libPath, Entity\Base::getClassName()),
                'class'
            )
            : [];

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

                    $params = $res->getParameters();
                    if (! empty($params['entities']) && is_array($params['entities'])) {
                        $result = $params['entities'];
                    }
                }
            }
        );

        return $result;
    }
}
