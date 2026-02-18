<?php

namespace MB\Bitrix\Contracts\Component;

interface LangProviderInterface
{
    public function getLang(string $code, ?array $replace = null, ?string $language = null): string;
}
