<?php

namespace MB\Bitrix\EntityView\Grid\Panel\Actions;

use Bitrix\Main\Error;
use Bitrix\Main\Filter\Filter;
use Bitrix\Main\Grid\Panel\Action\RemoveAction as RemoveActionBase;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\Result;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use MB\Bitrix\EntityView\Filter\DataProvider;
use MB\Bitrix\EntityView\Helper;

class DeleteAction extends RemoveActionBase
{
    /**
     * Fired before processing group delete. Return EventResult::ERROR to cancel.
     * Parameters: entity, rowIds (array of grid row id strings).
     */
    public const EVENT_ON_BEFORE_PANEL_DELETE = 'onEntityViewPanelBeforeDelete';

    /**
     * Grid sends selected row ids in this parameter (array of strings).
     */
    private const REQUEST_ROW_IDS_PARAM = 'id';

    public static function getId(): string
    {
        return 'delete';
    }

    public function processRequest(HttpRequest $request, bool $isSelectedAllRows, ?Filter $filter): ?Result
    {
        $result = new Result();

        /** @var DataProvider $dataProvider */
        $dataProvider = $filter->getEntityDataProvider();
        $entity = $dataProvider->getEntity();

        if ($isSelectedAllRows) {
            Loc::loadMessages(dirname(__DIR__, 5) . '/lang/' . (defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru') . '/lib/EntityView/Base.php');
            $result->addError(new Error(Loc::getMessage('MB_CORE_ENTITYVIEW_PANEL_DELETE_ALL_DISABLED') ?: 'Bulk delete of all filtered rows is disabled for safety. Please select specific rows.'));
            return $result;
        }

        $rowIds = $request->getPost(self::REQUEST_ROW_IDS_PARAM);
        if (!is_array($rowIds)) {
            $rowIds = $rowIds !== null && $rowIds !== '' ? [(string) $rowIds] : [];
        } else {
            $rowIds = array_filter(array_map('strval', $rowIds));
        }

        if (empty($rowIds)) {
            return $result;
        }

        $event = new Event('mb.core', self::EVENT_ON_BEFORE_PANEL_DELETE, [
            'entity' => $entity,
            'rowIds' => $rowIds,
        ]);
        $event->send();
        foreach ($event->getResults() as $eventResult) {
            if ($eventResult->getType() === EventResult::ERROR) {
                $result->addError(new \Bitrix\Main\Error($eventResult->getParameters()['message'] ?? 'Delete cancelled'));
                return $result;
            }
        }

        $result->addErrors(
            $this->removeItems($entity, $rowIds)->getErrors()
        );

        return $result;
    }

    /**
     * @param Entity $entity
     * @param string[] $rowIds grid row ids (single primary: ["5","7"]; composite: ["5|10","6|11"])
     * @return Result
     */
    protected function removeItems(Entity $entity, array $rowIds): Result
    {
        $result = new Result();
        $dataClass = $entity->getDataClass();

        foreach ($rowIds as $rowId) {
            $primary = Helper::parseRowIdToPrimary($entity, $rowId);
            $res = $dataClass::delete($primary);
            if (!$res->isSuccess()) {
                $result->addErrors($res->getErrors());
            }
        }

        return $result;
    }
}
