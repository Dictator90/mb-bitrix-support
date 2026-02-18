<?php

namespace MB\Bitrix\Component\Parameters;

use MB\Bitrix\Contracts\Component\ComponentParametersInterface;
use MB\Support\Collection;

abstract class Base implements ComponentParametersInterface
{
    protected Collection $values;

    public function __construct()
    {
        $this->values = new Collection();
    }

    public function getParam(string $key, $default = null): mixed
    {
        return $this->values->get($key, $default);
    }

    public function addParam(string $key, string|int|array|null $value = null): static
    {
        $this->values->offsetSet($key, $value);
        return $this;
    }

    public function setParams(array $values): static
    {
        $this->values = $this->values->merge($values);
        return $this;
    }

    public function toArray(): array
    {
        $this->modifyParametersBeforeReturn();
        return $this->values->all();
    }

    protected function modifyParametersBeforeReturn(): void
    {
    }
}
