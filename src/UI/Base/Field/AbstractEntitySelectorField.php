<?php

namespace MB\Bitrix\UI\Base\Field;

use MB\Bitrix\UI\Traits\HasFilter;
use MB\Bitrix\UI\Traits\HasMultiple;
use MB\Bitrix\UI\Traits\HasName;
use MB\Bitrix\UI\Traits\HasReadonly;

abstract class AbstractEntitySelectorField extends AbstractBaseField
{
    use HasName;
    use HasMultiple;
    use HasReadonly;
    use HasFilter;

    protected $primary = null;

    protected $lazyCallback = null;

    public function __construct(string $name)
    {
        $this->setName($name);
    }

    public function setLazyCallback($callback)
    {
        $this->lazyCallback = $callback;
        return $this;
    }

    public function beforeRender()
    {
        if ($this->primary) {
            if (is_callable($this->lazyCallback)) {
                $this->{$this->primary} = call_user_func($this->lazyCallback);
            } elseif (is_array($this->lazyCallback) && count($this->lazyCallback) > 1 && is_callable($this->lazyCallback[0])) {
                $this->{$this->primary} = call_user_func_array(...$this->lazyCallback);
            }
        }
    }

    public function toArray()
    {
        return array_merge(parent::toArray(), [
            'entityClass' => get_called_class(),
            'name' => $this->getName(),
            'multiple' => $this->isMultiple()
        ]);
    }
}
