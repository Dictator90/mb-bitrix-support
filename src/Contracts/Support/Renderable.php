<?php

declare(strict_types=1);

namespace MB\Bitrix\Contracts\Support;

/**
 * Object that can render itself (e.g. Bitrix admin settings page output).
 */
interface Renderable
{
    public function render(): void;
}
