<?php

namespace MB\Bitrix\Contracts\Migration;

use Bitrix\Main\Result;

interface Validator
{
    public function validate(mixed $entity): Result;
}