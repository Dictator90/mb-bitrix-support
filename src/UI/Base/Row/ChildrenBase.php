<?php

namespace MB\Bitrix\UI\Base\Row;

use MB\Bitrix\Contracts\UI\Renderable;
use MB\Bitrix\UI\Base\Field;
use MB\Bitrix\UI\Base\Traits\HasChildren;

abstract class ChildrenBase extends Base
{
    use HasChildren;

    public function toArray()
    {
        $result = ['fields' => []];

        foreach ($this->children as $child) {
            $result['fields'][] = $child->toArray();
        }

        return $result;

    }

    public function addChild(Renderable $child): static
    {
        if ($child instanceof Field\AbstractBaseField) {
            $child->setRow($this);
        }
        $this->children[] = $child;
        return $this;
    }

}
