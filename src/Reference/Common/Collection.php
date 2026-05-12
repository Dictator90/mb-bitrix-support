<?php

declare(strict_types=1);

namespace MB\Bitrix\Reference\Common;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template T of Model
 * @implements ArrayAccess<int, T>
 * @implements IteratorAggregate<int, T>
 */
class Collection implements ArrayAccess, IteratorAggregate, Countable
{
    /** @var array<int, T> */
    protected array $items = [];

    /**
     * @param iterable<int, T> $items
     */
    public function __construct(iterable $items = [])
    {
        foreach ($items as $item) {
            $this->items[] = $item;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[(int) $offset]);
    }

    public function offsetGet(mixed $offset): ?Model
    {
        return $this->items[(int) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$value instanceof Model) {
            return;
        }

        if ($offset === null) {
            $this->items[] = $value;
            return;
        }

        $this->items[(int) $offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[(int) $offset]);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}

