<?php

namespace MB\Bitrix\HighloadBlock;

use Bitrix\Highloadblock\HighloadBlockLangTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\ORM\Objectify;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserFieldTable;
use MB\Bitrix\HighloadBlock\Fields\AbstractField;

abstract class Base
{
    protected static $_instance;

    protected ErrorCollection $errorCollection;

    protected $hl;

    /**
     * @var Entity|null
     */
    protected $_entity;

    /**
     * @var DataManager|null
     */
    protected $_entityDataClass;

    protected ?Query $query = null;

    abstract public static function getTableName(): string;

    abstract public static function getName(): string;

    /**
     * Возвращает массив полей (AbstractField или массив конфигурации)
     *
     * @return array<AbstractField|array>
     */
    abstract public static function getMap(): array;

    /**
     * @return array<string, string> lid => name
     */
    abstract public static function getLang(): array;

    protected function __clone() {}
    protected function __wakeup() {}

    public static function getInstance()
    {
        $name = static::getName();
        if (!isset(self::$_instance[$name]) || self::$_instance[$name] === null) {
            self::$_instance[$name] = new static();
        }

        return self::$_instance[$name];
    }

    /**
     * @throws LoaderException
     * @throws SystemException
     */
    protected function __construct()
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new SystemException('Module "highloadblock" not included');
        }

        $this->errorCollection = new ErrorCollection();
        $this->fillEntity();
    }

    public static function getClassName()
    {
        return '\\' . get_called_class();
    }

    protected function fillEntity(): void
    {
        if ($this->hl = $this->getHlblock()) {
            if ($this->_entity = HighloadBlockTable::compileEntity($this->hl)) {
                $this->_entityDataClass = $this->_entity->getDataClass();
            }
        }
    }

    public function isExist(): bool
    {
        return (bool) $this->_entity;
    }

    public function createTable(): bool
    {
        if ($this->isExist()) {
            return false;
        }

        $this->createTableInternal();
        $this->createFields();

        return true;
    }

    /**
     * @return \Bitrix\Main\DB\Result|\Bitrix\Main\Entity\DeleteResult|false
     */
    public function dropTable()
    {
        if (!isset($this->hl['ID']) || $this->hl['ID'] <= 0) {
            return false;
        }

        return HighloadBlockTable::delete($this->hl['ID']);
    }

    public function refresh(): bool
    {
        if (!$this->isExist()) {
            return false;
        }

        $this->refreshFields();

        return true;
    }

    protected function createTableInternal(): void
    {
        $object = HighloadBlockTable::createObject();
        $object
            ->set('NAME', static::getName())
            ->set('TABLE_NAME', static::getTableName());
        $res = $object->save();
        if ($res->isSuccess()) {
            $this->hl = $this->getHlblock();
            foreach (static::getLang() as $lid => $name) {
                $langObj = HighloadBlockLangTable::createObject();
                $langObj->set('ID', $res->getId())->set('LID', $lid)->set('NAME', $name)->save();
            }
        } else {
            foreach ($res->getErrors() as $error) {
                $this->errorCollection[] = $error;
            }
        }
    }

    protected function createFields(): void
    {
        global $APPLICATION;
        $obUserField = new \CUserTypeEntity();
        foreach (static::getMap() as $field) {
            if ($field instanceof AbstractField) {
                $res = $obUserField->Add($this->buildUserField($field));
                if (!$res) {
                    $this->errorCollection[] = new Error($APPLICATION->LAST_ERROR ?? 'Unknown error');
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildUserField(AbstractField $field): array
    {
        return [
            'ENTITY_ID' => $this->getEntityId(),
            'FIELD_NAME' => $field->getName(),
            'USER_TYPE_ID' => $field->getUserType(),
            'MANDATORY' => method_exists($field, 'isRequired') && $field->isRequired() ? 'Y' : 'N',
            'MULTIPLE' => method_exists($field, 'isMultiple') && $field->isMultiple() ? 'Y' : 'N',
            'EDIT_FORM_LABEL' => $field->getEditFormLabel(),
            'LIST_COLUMN_LABEL' => $field->getListColumnLabel(),
            'LIST_FILTER_LABEL' => $field->getListFilterLabel(),
            'ERROR_MESSAGE' => ['ru' => '', 'en' => ''],
            'HELP_MESSAGE' => ['ru' => '', 'en' => ''],
            'SHOW_IN_LIST' => $field->isShowInList() ? 'Y' : 'N',
            'SHOW_FILTER' => $field->isShowInFilter() ? 'Y' : 'N',
            'EDIT_IN_LIST' => $field->isEditInList() ? 'Y' : 'N',
            'IS_SEARCHABLE' => $field->isSearchable() ? 'Y' : 'N',
            'SETTINGS' => $field->getSettings(),
        ];
    }

    protected function refreshFields(): void
    {
        $obUserField = new \CUserTypeEntity();
        $currentFields = $this->getUserFields(['ID', 'FIELD_NAME']);

        foreach (static::getMap() as $code => $data) {
            if ($data instanceof AbstractField) {
                $code = $data->getName();
            }
            if (isset($currentFields[$code])) {
                if ($data instanceof AbstractField) {
                    $obUserField->Update($currentFields[$code]['ID'], $this->buildUserField($data));
                }
            } else {
                if ($data instanceof AbstractField) {
                    $obUserField->Add($this->buildUserField($data));
                }
            }
        }
    }

    public function getEntityId(): string
    {
        return 'HLBLOCK_' . $this->hl['ID'];
    }

    public function getId(): ?int
    {
        if ($this->isExist()) {
            return (int) $this->hl['ID'];
        }

        return null;
    }

    /**
     * @param array $select
     * @return array<string, array>
     */
    protected function getUserFields(array $select = ['*']): array
    {
        $result = [];

        $query = UserFieldTable::query();
        $rows = $query
            ->setSelect($select)
            ->where('ENTITY_ID', $this->getEntityId())
            ->fetchAll();
        foreach ($rows as $row) {
            $result[$row['FIELD_NAME']] = $row;
        }

        return $result;
    }

    public function truncateTable(): bool
    {
        if (isset($this->hl['ID']) && $this->hl['ID'] > 0) {
            return Application::getConnection()->truncateTable(static::getTableName());
        }

        return false;
    }

    /**
     * @throws \Bitrix\Main\ArgumentException
     * @throws SystemException
     */
    public function query(): Query
    {
        if (!$this->query) {
            $this->query = new Query($this->_entityDataClass::getEntity());
            $this->query->addSelect('ID');
        }

        return $this->query;
    }

    public function getList(array $params = [])
    {
        return $this->_entityDataClass::getList($params);
    }

    public function getEntity(): ?Entity
    {
        return $this->_entity;
    }

    /**
     * @return DataManager|null
     */
    public function getEntityDataClass()
    {
        return $this->_entityDataClass;
    }

    /**
     * @return array|null
     */
    protected function getHlblock()
    {
        $query = new Query(HighloadBlockTable::class);
        $query
            ->setSelect(['*'])
            ->where('NAME', static::getName())
            ->where('TABLE_NAME', static::getTableName());

        return $query->fetch() ?: null;
    }

    public function getErrorCollection(): ErrorCollection
    {
        return $this->errorCollection;
    }

    public function hasErrors(): bool
    {
        return !$this->errorCollection->isEmpty();
    }
}
