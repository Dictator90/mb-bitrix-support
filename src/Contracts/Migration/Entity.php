<?php

namespace MB\Bitrix\Contracts\Migration;

use Bitrix\Main\Result;

interface Entity
{
    /**
     * Проверка актуализации версии модуля и дальнейшие действия
     *
     * @return bool
     */
    public function check(): bool;

    /**
     * Установка миграций
     *
     * @return Result
     */
    public function up(): Result;

    /**
     * Откат миграций
     *
     * @return Result
     */
    public function down(): Result;
}
