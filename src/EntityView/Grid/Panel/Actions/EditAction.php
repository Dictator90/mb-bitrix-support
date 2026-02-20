<?php

namespace MB\Bitrix\EntityView\Grid\Panel\Actions;

use Bitrix\Main\Filter\Filter;
use Bitrix\Main\Grid\Panel\Action\EditAction as EditActionBase;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Result;
use MB\Bitrix\EntityView\Filter\DataProvider;

class EditAction extends EditActionBase
{
    public static function getId(): string
    {
        return 'edit';
    }

    public function processRequest(HttpRequest $request, bool $isSelectedAllRows, ?Filter $filter): ?Result
    {
        $result = new Result();

        /** @var DataProvider $dataProvider */
        $dataProvider = $filter->getEntityDataProvider();
        $entity = $dataProvider->getEntity();
        $fields = $request->getPost('FIELDS');

        if ($fields) {
            foreach ($fields as $id => $data) {
                $res = $entity->getDataClass()::update($id, $data);
                if (!$res->isSuccess()) {
                    $result->addError($res->getErrors()[0]);
                }
            }
        }

        return $result;
    }
}
