<?php

namespace MB\Core\Settings\Options;

use Bitrix\Main\ArgumentException;

class EmptyOptions extends Base
{

    static array $map = [];

    public function __construct(string $id, $params = [])
    {
        $this->setMap($params['map'] ?: []);
        parent::__construct($id);
    }

    public function setMap(array $map)
    {
        static::$map = $map;
        return $this;
    }


    public static function getMap(): array
    {
        return static::$map;
    }
}
