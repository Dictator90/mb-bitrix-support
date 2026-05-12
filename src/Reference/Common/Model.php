<?php

declare(strict_types=1);

namespace MB\Bitrix\Reference\Common;

/**
 * Lightweight data model used by UI template DTOs.
 */
class Model
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(protected array $attributes = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
