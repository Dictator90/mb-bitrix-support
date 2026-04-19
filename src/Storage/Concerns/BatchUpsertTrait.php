<?php

namespace MB\Bitrix\Storage\Concerns;

use Bitrix\Main;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Entity\AddResult;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;
use MB\Bitrix\Storage\SqlHelper;

trait BatchUpsertTrait
{
    /**
     * Пакетное добавление записей в таблицу (cross-platform UPSERT).
     *
     * Ведет себя как старая {@see \MB\Bitrix\Storage\Base::addBatch()},
     * но SQL for duplicates строится через {@see SqlHelper}.
     *
     * @param array $dataList
     * @param bool|array $updateOnDuplicate Если false - обычная вставка,
     *                                      если true - обновление при дубликате всех полей кроме первичного ключа,
     *                                      если array - список полей для обновления при дубликате.
     * @return AddResult
     * @throws ArgumentException
     * @throws SqlQueryException|Main\SystemException
     */
    public static function addBatch(array $dataList, bool|array $updateOnDuplicate = false)
    {
        $result = new AddResult();

        if ($dataList === []) {
            return $result;
        }

        try {
            /** @var Main\ORM\Entity $entity */
            $entity = static::getEntity();
            $fields = $entity->getFields();
            $connection = $entity->getConnection();
            $helper = $connection->getSqlHelper();
            $tableName = $entity->getDBTableName();

            $sqlFieldPart = '';
            $sqlValuePart = '';
            $issetFieldsPart = false;
            $usedFields = [];

            foreach ($dataList as $data) {
                foreach ($data as $fieldName => $value) {
                    if (!isset($fields[$fieldName])) {
                        throw new ArgumentException(
                            sprintf(
                                '%s Entity has no `%s` field.',
                                $entity->getName(),
                                $fieldName
                            ),
                            $fieldName
                        );
                    }

                    $field = $fields[$fieldName];
                    $data[$fieldName] = $field->modifyValueBeforeSave($value, $data);

                    if (!$issetFieldsPart) {
                        $usedFields[] = $fieldName;
                    }
                }

                $insert = $helper->prepareInsert($tableName, $data);

                if (!$issetFieldsPart) {
                    $issetFieldsPart = true;
                    $sqlFieldPart = $insert[0];
                }

                $sqlValuePart .= ($sqlValuePart !== '' ? ',' . PHP_EOL : '') . '(' . $insert[1] . ')';
            }

            if (! $issetFieldsPart) {
                return $result;
            }

            $primaryArray = $entity->getPrimaryArray();
            $duplicateFields = [];
            $doUpdate = $updateOnDuplicate !== false;

            if ($doUpdate) {
                if (is_array($updateOnDuplicate)) {
                    $duplicateFields = $updateOnDuplicate;
                } else {
                    $tableFields = $connection->getTableFields($tableName);
                    $primaryMap = array_flip($primaryArray);

                    foreach ($usedFields as $fieldName) {
                        if (!isset($primaryMap[$fieldName]) && isset($tableFields[$fieldName])) {
                            $duplicateFields[] = $fieldName;
                        }
                    }
                }
            }

            $sql = SqlHelper::buildCrossPlatformUpsertSql(
                $connection,
                $tableName,
                $sqlFieldPart,
                $sqlValuePart,
                $primaryArray,
                $duplicateFields,
                $doUpdate
            );

            $connection->queryExecute($sql);
        } catch (\Exception $e) {
            throw $e;
        }

        return $result;
    }
}

