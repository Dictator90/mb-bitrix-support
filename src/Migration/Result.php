<?php

namespace MB\Bitrix\Migration;

use Bitrix\Main;
use Bitrix\Main\Error;

final class Result extends Main\Result
{
    public function addThrowable(\Throwable $throwable)
    {
        $this->addError(
            new Error(
                $throwable->getMessage(),
                $throwable->getCode(),
                [
                    'backtrace' => $throwable->getTraceAsString()
                ]
            )
        );

        return $this;
    }
}
