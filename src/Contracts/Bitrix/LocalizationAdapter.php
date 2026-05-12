<?php

declare(strict_types=1);

namespace MB\Bitrix\Contracts\Bitrix;

interface LocalizationAdapter
{
    public function message(string $code, string $fallback = ''): string;
}

