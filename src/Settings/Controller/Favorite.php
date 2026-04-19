<?php

namespace MB\Core\Settings\Controller;

use Bitrix\Main\Error;
use Bitrix\Main\Request;
use Bitrix\Main\Web\Uri;

require_once $_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/classes/general/favorites.php";

class Favorite extends \Bitrix\Main\Engine\Controller
{
    /**
     * @var \CFavorites|\CAllFavorites
     */
    protected $favoriteClass;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->favoriteClass = class_exists('\CAllFavorites') ? \CAllFavorites::class : \CFavorites::class;
    }

    public function configureActions()
    {
        return [
            'has' => [],
            'add' => [],
            'remove' => []
        ];
    }

    public function hasAction($url)
    {
        $id =  intval($this->favoriteClass::GetIDByUrl($url));
        return [
            'isset' => $id > 0,
            'id' => $id
        ];
    }

    public function addAction(array $params)
    {
        global $USER, $DB;

        $now = $DB->GetNowFunction();
        $uid = $USER->GetID();

        $params['pageLink'] = (new Uri($params['pageLink']))->deleteParams(['IFRAME', 'IFRAME_TYPE'])->getUri();

        $arFields = [
            'NAME' => $params['pageTitle'],
            'URL' => $params['pageLink'],
            'USER_ID' => $uid,
            'COMMON' => 'N',
            'MODIFIED_BY'	=>	$uid,
            'CREATED_BY'	=>	$uid,
            'LANGUAGE_ID'	=> LANGUAGE_ID,
            '~TIMESTAMP_X'	=> $now,
            '~DATE_CREATE'	=>	$now,
        ];

        $id = $this->favoriteClass::Add($arFields,true);

        if (!$id) {
            $this->addError(new Error('Ошибка добавления в избранное: '. $id));
        }

        return ['id' => $id];
    }

    public function removeAction($id)
    {
        try {
            $this->favoriteClass::Delete(intval($id));
        }
        catch (\Throwable $e) {
            $this->addError(new Error($e->getMessage(), $e->getCode()));
        }
    }

    public function isFavoriteAction($url)
    {
        return \CAllFavorites::GetIDByUrl($url);
    }
}
