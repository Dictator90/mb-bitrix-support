<?php

namespace MB\Core\Settings\Admin;

use Bitrix\Main\Engine;

class Controller extends Engine\Controller
{
    public function configureActions()
    {
        return [
            'isFavorite' => []
        ];
    }
    
    public function isFavoriteAction($url)
    {
        return \CAllFavorites::GetIDByUrl($url);
    }
}
