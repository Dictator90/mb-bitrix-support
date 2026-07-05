<?php

namespace MB\Bitrix\Storage\Concerns;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity\UpdateResult;
use MB\Bitrix\Storage\SqlHelper;

trait MassUpdateTrait
{
    /**
     * Массовое обновление через VALUES + JOIN.
     * Поддерживает составные первичные ключи и разные СУБД.
     *
     * @param array $primaries
     * @param array $fieldsList
     * @return UpdateResult
     *
     * @throws ArgumentException
     */
    public static function updateAll(array $primaries, array $fieldsList): UpdateResult
    {
        $result = new UpdateResult();

        if (empty($primaries) || empty($fieldsList)) {
            return $result;
        }

        if (count($primaries) !== count($fieldsList)) {
            throw new ArgumentException(
                'Number of primaries must match number of fields sets'
            );
        }

        $entity = static::getEntity();
        $connection = $entity->getConnection();
        $tableName = $entity->getDBTableName();
        $entityFields = $entity->getFields();
        $helper = $connection->getSqlHelper();

        $primaryFields = $entity->getPrimaryArray();
        $isCompositePrimary = count($primaryFields) > 1;

        try {
            $connection->startTransaction();

            self::validatePrimaries($primaries, $primaryFields, $isCompositePrimary);

            $updateFields = [];
            foreach ($fieldsList as $fields) {
                foreach (array_keys($fields) as $field) {
                    if (!in_array($field, $primaryFields) && !in_array($field, $updateFields)) {
                        $updateFields[] = $field;
                    }
                }
            }

            // Валидация полей
            foreach ($updateFields as $field) {
                if (!isset($entityFields[$field])) {
                    throw new ArgumentException(
                        sprintf(
                            '%s Entity has no `%s` field.',
                            $entity->getName(),
                            $field
                        ),
                        $field
                    );
                }
            }

            // Строим VALUES часть
            $allFields = array_merge($primaryFields, $updateFields);
            $values = [];

            foreach ($primaries as $index => $primary) {
                $fields = $fieldsList[$index];
                $rowData = [];

                // Добавляем значения первичных ключей
                if ($isCompositePrimary) {
                    foreach ($primaryFields as $primaryField) {
                        $value = $primary[$primaryField];
                        $fieldObject = $entityFields[$primaryField];
                        $rowData[] = $helper->convertToDb($value, $fieldObject);
                    }
                } else {
                    $fieldObject = $entityFields[$primaryFields[0]];
                    $rowData[] = $helper->convertToDb($primary, $fieldObject);
                }

                // Добавляем значения обновляемых полей
                foreach ($updateFields as $field) {
                    $fieldObject = $entityFields[$field];

                    if (array_key_exists($field, $fields)) {
                        $value = $fields[$field];
                        $preparedValue = $fieldObject->modifyValueBeforeSave($value, $fields);
                        $rowData[] = $helper->convertToDb($preparedValue, $fieldObject);
                    } else {
                        $rowData[] = $helper->convertToDb(null, $fieldObject);
                    }
                }

                $values[] = '(' . implode(', ', $rowData) . ')';
            }

            $sql = SqlHelper::buildCrossPlatformUpdateSql($connection, $tableName, $primaryFields, $allFields, $values);

            $connection->queryExecute($sql);
            $connection->commitTransaction();

            $result->setAffectedRowsCount($connection);
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            throw $e;
        }

        return $result;
    }

    /**
     * Упрощенный метод массового обновления для простых случаев.
     * Обновляет одно поле для множества записей.
     */
    public static function updateBatch(array $primaries, string $fieldName, mixed $value): UpdateResult
    {
        $fieldsList = [];
        foreach ($primaries as $primary) {
            $fieldsList[] = [$fieldName => $value];
        }

        return static::updateAll($primaries, $fieldsList);
    }

    /**
     * @param array $primaries
     * @param array $primaryFields
     * @param bool $isComposite
     */
    protected static function validatePrimaries(array $primaries, array $primaryFields, bool $isComposite): void
    {
        foreach ($primaries as $index => $primary) {
            if ($isComposite) {
                if (!is_array($primary)) {
                    throw new ArgumentException(
                        sprintf('Primary key at index %d must be array for composite key', $index)
                    );
                }

                foreach ($primaryFields as $field) {
                    if (!isset($primary[$field])) {
                        throw new ArgumentException(
                            sprintf('Missing primary key field "%s" at index %d', $field, $index)
                        );
                    }
                }
            } else {
                if (is_array($primary)) {
                    throw new ArgumentException(
                        sprintf('Primary key at index %d must be scalar for simple key', $index)
                    );
                }
            }
        }
    }
}

