<?php

namespace MB\Core\Settings\Options;

use Bitrix\Main\Web\Json;

class Collection
    implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable
{
    /**
     * @var array
     */
    protected array $items = [];

    public function __construct(array $items = null)
    {
        if ($items !== null) {
            $this->set($items);
        }
    }


    public function add(Base $item): static
    {
        $this->items[] = $item;
        return $this;
    }

    /**
     *
     * @return Base[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param Base[] $items
     * @return static
     */
    public function set(array $items): static
    {
        foreach ($items as $item) {
            $this->add($item);
        }

        return $this;
    }

    public function current(): Base
    {
        return current($this->items);
    }

    public function next(): void
    {
        next($this->items);
    }

    public function key(): int
    {
        return key($this->items);
    }

    public function valid(): bool
    {
        return ($this->key() !== null);
    }

    public function rewind(): void
    {
        reset($this->items);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]) || \array_key_exists($offset, $this->items);
    }

    public function offsetGet($offset): ?Base
    {
        if (isset($this->items[$offset]) || \array_key_exists($offset, $this->items)) {
            return $this->items[$offset];
        }

        return null;
    }

    public function offsetSet($offset, $value): static
    {
        if($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }

        return $this;
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function jsonSerialize()
    {
        return Json::encode($this->toArray());
    }
}
