<?php

namespace MB\Bitrix\Contracts\Config;

interface Entity
{
    public function get(string $name, mixed $default = null);
    public function getAll(): array;
    public function set(string $name, $value = ""): static;
    public function remove(string $name): static;
    public function has(string $name): bool;
    public function isEmpty(string $name): bool;
}