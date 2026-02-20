<?php

namespace MB\Bitrix\EntityView\Grid\Row\Action;

use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Grid\Row\Action;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\Result;
use Bitrix\Main\Web\Json;
use MB\Bitrix\Config;
use MB\Bitrix\EntityView\Grid\Settings;
use MB\Bitrix\EntityView\Helper;

class DeleteAction extends Action\BaseAction
{
    const EVENT_ON_PROCESS_REQUEST = 'onGridRowDeleteAction';

    public function __construct(
        protected Entity $entity,
        protected Settings $settings
    ) {}

    public static function getId(): ?string
    {
        return 'delete';
    }

    protected function getText(): string
    {
        Loc::loadMessages(dirname(__DIR__, 5) . '/lang/' . (defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru') . '/lib/EntityView/Base.php');
        return Loc::getMessage('MB_CORE_ENTITYVIEW_ROW_DELETE') ?: 'Удалить';
    }

    public function processRequest(HttpRequest $request): ?Result
    {
        $primary = Helper::getPrimaryValues($this->entity, $request->getPostList()->toArray());

        $errors = [];
        $event = new Event(config()->getModuleId(), self::EVENT_ON_PROCESS_REQUEST, [
            'entity' => $this->entity,
            'primary' => $primary,
        ]);
        $event->send();
        foreach ($event->getResults() as $result) {
            if ($result->getType() == EventResult::ERROR) {
                Loc::loadMessages(dirname(__DIR__, 5) . '/lang/' . (defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru') . '/lib/EntityView/Base.php');
                $errors[] = $result->getParameters()['error'] ?: Loc::getMessage('MB_CORE_ENTITYVIEW_DELETE_ERROR');
            } elseif ($result->getType() == EventResult::SUCCESS) {
                return new Result();
            }
        }

        if ($errors) {
            $result = new Result();
            foreach ($errors as $error) {
                $result->addError(new Error($error));
            }

            return $result;
        }


        try {
            return $this->entity->getDataClass()::delete($primary);
        } catch (\Exception | \Error $e) {
            return (new Result())->addError(new Error("Ошибка удаления: " . $e->getMessage()));
        }
    }

    public function getControl(array $rawFields): ?array
    {
        $gridId = $this->settings->getID();
        $data = Helper::getPrimaryValues($this->entity, $rawFields);
        $jsData = Json::encode($data);
        $this->onclick = <<<JS
            BX.Main.gridManager.getById('{$gridId}')?.instance?.sendRowAction('delete', {$jsData});
JS;
        return parent::getControl($rawFields);
    }
}
