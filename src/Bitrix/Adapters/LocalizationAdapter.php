<?php

declare(strict_types=1);

namespace MB\Bitrix\Bitrix\Adapters;

use Bitrix\Main\Localization\Loc;
use MB\Bitrix\Contracts\Bitrix\LocalizationAdapter as LocalizationAdapterContract;

final class LocalizationAdapter implements LocalizationAdapterContract
{
    public function message(string $code, string $fallback = ''): string
    {
        return (string) (Loc::getMessage($code) ?: $fallback);
    }
}

