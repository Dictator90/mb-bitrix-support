<?php

namespace MB\Bitrix\Migration;

use Bitrix\Main;
use Bitrix\Main\Error;

final class Result extends Main\Result
{
    public function addThrowable(\Throwable $throwable): static
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

    public function merge(Main\Result $result): static
    {
        /** @var list<Error> $errors */
        $errors = $result->getErrors();
        if ($errors !== []) {
            $this->addErrors($errors);
        }

        return $this;
    }
}
