<?php

namespace MB\Bitrix\Contracts;

interface CachableInterface
{
    public function remember(string $key, callable $callback, ?int $ttl = null);
}