<?php

namespace MB\Bitrix\UI\Control\Form;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\Result;
use Bitrix\Main\Web\Uri;
use MB\Bitrix\EntityView\Helper;
use MB\Support\Str;
use MB\Bitrix\UI\Base\Field;
use MB\Bitrix\UI\Base\Row;
use MB\Bitrix\UI\Base\Tab;

class EntityBitrix extends Bitrix
{
    /** Before save (create/update). Handlers can modify fields or return ERROR to cancel. */
    public const EVENT_ON_BEFORE_FORM_SAVE = 'onEntityViewFormBeforeSave';
    /** After successful save. */
    public const EVENT_ON_AFTER_FORM_SAVE = 'onEntityViewFormAfterSave';

    protected $entity = null;
    protected $editUrlTemplate = null;
    protected $listUrlTemplate = null;

    //todo ErrorCollection

    public function __construct(Entity $entity, $editUrlTemplate = null)
    {
        $this->entity = $entity;
        $this->editUrlTemplate = $editUrlTemplate;
        parent::__construct($this->entity->getDataClass()::getTableName());
    }

    public function checkRequest()
    {
        $result = new Result();
        if ($this->isCurrentActionRequest()) {
            if ($this->isSaveActionRequest()) {
                $result = $this->saveSettingsAction();
            } elseif ($this->isDeleteActionRequest()) {
                $result = $this->deleteSettingsAction();
            }
        }

        return $result;
    }

    public function saveSettingsAction(): Result
    {
        $result = new Result();

        $fieldsValue = [];
        $values = $this->request->getPostList();
        $primaryValues = Helper::getPrimaryValues($this->entity, $values->toArray());
        $allFields = [];
        $this->extractFields($allFields, $this->tabset);

        foreach ($values->toArray() as $name => $value) {
            if (!$primaryValues[$name]) {
                $this->modifyBeforeSave($name, $value, $allFields);
                $fieldsValue[$name] = $value;
            }
        }

        $isEdit = $this->isEditAction($primaryValues);
        $event = new Event('mb.core', self::EVENT_ON_BEFORE_FORM_SAVE, [
            'entity' => $this->entity,
            'primaryValues' => $primaryValues,
            'fields' => &$fieldsValue,
            'isEdit' => $isEdit,
        ]);
        $event->send();
        foreach ($event->getResults() as $eventResult) {
            if ($eventResult->getType() === EventResult::ERROR) {
                $result->addErrors($eventResult->getParameters()['errors'] ?? [new \Bitrix\Main\Error($eventResult->getParameters()['message'] ?? 'Save cancelled')]);
                return $result;
            }
            if ($eventResult->getParameters()['fields'] !== null) {
                $fieldsValue = $eventResult->getParameters()['fields'];
            }
        }

        if ($isEdit) {
            $res = $this->editRow($primaryValues, $fieldsValue);
        } else {
            $res = $this->createRow($fieldsValue);
        }
        if ($res->isSuccess()) {
            $savedPrimary = $isEdit ? $primaryValues : $res->getPrimary();
            $event = new Event('mb.core', self::EVENT_ON_AFTER_FORM_SAVE, [
                'entity' => $this->entity,
                'primary' => $savedPrimary,
                'fields' => $fieldsValue,
                'isEdit' => $isEdit,
            ]);
            $event->send();
            $uri = new Uri(Helper::getUrlByFields($this->editUrlTemplate, $this->entity, $savedPrimary));
            $uri->addParams([
                'IFRAME' => 'Y',
                'IFRAME_TYPE' => 'SIDE_SLIDER'
            ]);
            $result->setData(['path' => $uri->getUri()]);
        } else {
            $result->addErrors($res->getErrors());
        }

        return $result;


        /*
        if ($this->request->isAjaxRequest()) {
            //todo for ajax;
        } else {
            $uri = new Uri($this->request->getRequestUri());
            $uri->addParams(['saved' => 1]);
            LocalRedirect($uri->getUri());
        }
        */

    }

    public function deleteSettingsAction(): Result
    {
        $result = new Result();

        $values = $this->request->getPostList();
        $primaryValues = Helper::getPrimaryValues($this->entity, $values->toArray());

        $res = $this->deleteRow($primaryValues);
        if ($res->isSuccess()) {
            $uri = new Uri($this->request->getRequestUri());
            $uri->deleteParams([
                'action','IFRAME', 'IFRAME_TYPE', 'sessid', 'grid_id', 'internal', 'grid_action', 'bxajaxid'
            ]);
            $result->setData(['path' => $uri->getUri()]);
        } else {
            $result->addErrors($res->getErrors());
        }

        return $result;
    }

    protected function extractFields(&$result, $row)
    {
        if ($row instanceof Tab\Set) {
            foreach ($row->getTabs() as $tab) {
                $this->extractFields($result, $tab);
            }
        } elseif ($row instanceof Tab\Base) {
            foreach ($row->getRows() as $row) {
                $this->extractFields($result, $row);
            }
        } elseif ($row instanceof Row\ChildrenBase) {
            foreach ($row->getChildren() as $fiels) {
                $this->extractFields($result, $fiels);
            }
        } elseif ($row instanceof Field\AbstractBaseField) {
            $result[] = $row;
        }
    }

    protected function modifyBeforeSave($name, &$value, $allFields)
    {
        foreach ($allFields as $field) {
            if (
                $field instanceof Field\AbstractBaseField
                && Str::lower($field->getName()) == Str::lower($name)
            ) {
                $field->beforeSave($value);
            }
        }
    }

    protected function editRow($primary, $fields)
    {
        return $this->entity->getDataClass()::update($primary, $fields);
    }

    protected function createRow($fields)
    {
        return $this->entity->getDataClass()::add($fields);
    }

    protected function deleteRow($primary)
    {
        return $this->entity->getDataClass()::delete($primary);
    }

    protected function isEditAction(array $primaryValues)
    {
        if ($primaryValues) {
            foreach ($primaryValues as $value) {
                if (!$value || intval($value) <= 0) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    public function getEntity()
    {
        return $this->entity;
    }
}
