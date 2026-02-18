<?php

namespace MB\Bitrix\Config;

use Bitrix\Main\HttpApplication;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntityContract;
use MB\Bitrix\Contracts\Config\Entity as EntityContract;
use MB\Support\Collection;

class Entity implements \MB\Bitrix\Contracts\Config\Entity
{
    use UseOptions;

    protected ModuleEntityContract $module;
    protected string|bool $siteId;
    private Collection $options;

    public function __construct(ModuleEntityContract $module, $siteId = '')
    {
        $this->module = $module;
        $this->siteId = $siteId;

        $this->fill();
    }

    public function getModuleId(): string
    {
        return $this->module->getId();
    }

    public function get(string $name, mixed $default = null)
    {
        return $this->options->get($name, $default);
    }

    public function getAll(): array
    {
        return $this->options->toArray();
    }

    public function has(string $name): bool
    {
        return $this->options->has($name);
    }

    public function isEmpty(string $name): bool
    {
        return
            $this->options->whereNotNull($name)->isEmpty()
            || $this->options->where($name, '==', '');
    }

    public function set(string $name, $value = ""): static
    {
        $this->setToStorage($name, $value, $this->siteId ?: "");
        $this->options->offsetSet($name, $value);

        return $this;
    }

    public function remove(string $name): static
    {
        $this->removeFromStorage($name);
        $this->options->offsetUnset($name);

        return $this;
    }

    public function fill(): static
    {
        $this->options = collect($this->getAllFromStorage($this->siteId));

        return $this;
    }

    /**
     * Возвращает идентификатор сайта
     * @return string|null
     */
    public static function getSiteId()
    {
        return HttpApplication::getInstance()->getContext()->getSite();
    }

    public static function getClassName()
    {
        return '\\' . get_called_class();
    }

    public static function create(string $moduleId, $siteId = ''): static
    {
        return new static(module($moduleId), $siteId);
    }
}
