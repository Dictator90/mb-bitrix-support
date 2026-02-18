<?php

namespace MB\Bitrix\Storage;

use Bitrix\Main;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Entity\{ AddResult, UpdateResult, DeleteResult };
use Bitrix\Main\DB\Connection;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;
use MB\Support\Arr;
use MB\Support\Str;
use MB\Bitrix\Storage\Concerns\DeleteByFilter;
use MB\Bitrix\Storage\Concerns\BuildIndexes;

/**
 * @method static Query query()
 */
abstract class Base extends DataManager
{
    use DeleteByFilter;
    use BuildIndexes;

    public static function getClassName()
    {
        return '\\' . get_called_class();
    }

    public static function getObjectParentClass()
    {
        return EntityObject::class;
    }

    public static function getCollectionParentClass()
    {
        return Collection::class;
    }

    /**
     * @return class-string<Query>
     */
    public static function getQueryClass()
    {
        return Query::class;
    }


    /**
     * @return array
     */
    public static function getRequiredFields(): array
    {
        $result = [];
        /** @var Fields\Field $field */
        foreach (static::getMap() as $field) {
            if ($field instanceof Fields\ScalarField && $field->isRequired()) {
                $result[$field->getName()] = $field;
            }
        }

        return $result;
    }

    /**
     * Пакетное добавление записей в таблицу
     *
     * @param array $dataList Массив данных для добавления. Каждый элемент - ассоциативный массив поле=>значение
     * @param bool|array $updateOnDuplicate Если false - обычная вставка,
     *                                      если true - обновление при дубликате ключа всех полей кроме первичного ключа,
     *                                      если array - список полей для обновления при дубликате ключа
     * @return AddResult
     * @throws Main\ArgumentException При передаче несуществующих полей
     * @throws SqlQueryException|Main\SystemException При ошибках выполнения SQL
     *
     * <code>
     * // Простая пакетная вставка
     * $data = [
     *     ['NAME' => 'Запись 1', 'ACTIVE' => 'Y', 'SORT' => 100],
     *     ['NAME' => 'Запись 2', 'ACTIVE' => 'Y', 'SORT' => 200],
     *     ['NAME' => 'Запись 3', 'ACTIVE' => 'N', 'SORT' => 300],
     * ];
     *
     * $result = MyTable::addBatch($data);
     *
     * // Вставка с обновлением при дубликате ключа
     * $data = [
     *     ['ID' => 1, 'NAME' => 'Обновленная запись 1', 'COUNT' => 5],
     *     ['ID' => 2, 'NAME' => 'Обновленная запись 2', 'COUNT' => 10],
     * ];
     *
     * // Обновить все поля кроме ID при дубликате
     * $result = MyTable::addBatch($data, true);
     *
     * // Обновить только указанные поля при дубликате
     * $result = MyTable::addBatch($data, ['NAME', 'COUNT']);
     * </code>
     */
    public static function addBatch(array $dataList, bool|array $updateOnDuplicate = false)
    {
        $result = new AddResult();

        try {
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
                        throw new Main\ArgumentException(
                            sprintf(
                                '%s Entity has no `%s` field.',
                                $entity->getName(),
                                $fieldName
                            )
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

            if ($issetFieldsPart) // has data to insert
            {
                $insertRule = 'INSERT INTO';
                $duplicateSql = '';

                if ($updateOnDuplicate !== false) {
                    if (is_array($updateOnDuplicate)) {
                        $duplicateFields = $updateOnDuplicate;
                    } else {
                        $tableFields = $connection->getTableFields($tableName);
                        $primaryArray = $entity->getPrimaryArray();
                        $primaryMap = array_flip($primaryArray);
                        $duplicateFields = [];

                        foreach ($usedFields as $fieldName) {
                            if (!isset($primaryMap[$fieldName]) && isset($tableFields[$fieldName])) {
                                $duplicateFields[] = $fieldName;
                            }
                        }
                    }

                    foreach ($duplicateFields as $fieldName) {
                        $fieldNameQuoted = $helper->quote($fieldName);

                        if ($duplicateSql !== '') {
                            $duplicateSql .= ', ';
                        }

                        $duplicateSql .= $fieldNameQuoted . ' = VALUES(' . $fieldNameQuoted . ')';
                    }

                    if ($duplicateSql === '') {
                        $insertRule = 'INSERT IGNORE INTO';
                    } else {
                        $duplicateSql =
                            PHP_EOL . 'ON DUPLICATE KEY UPDATE'
                            . PHP_EOL . $duplicateSql;
                    }
                }

                $sql =
                    $insertRule . ' ' . $tableName . '(' . $sqlFieldPart . ') ' .
                    'VALUES ' . $sqlValuePart
                    . $duplicateSql;

                $connection->queryExecute($sql);
            }
        } catch (\Exception $e) {
            // check result to avoid warning
            $result->isSuccess();

            throw $e;
        }

        return $result;
    }

    /**
     * Пакетное обновление записей по условию
     *
     * @param array|Query|ConditionTree $parameters
     * Массив параметров или объект Query для фильтрации записей. Поддерживаемые параметры:
     * - 'filter' - условия фильтрации
     * - 'runtime' - runtime поля
     *
     * @param array $data Ассоциативный массив поле => значение для обновления
     * @return UpdateResult
     * @throws Main\SystemException При невалидном запросе
     *
     * <code>
     * // Обновление по фильтру
     * $parameters = [
     *     'filter' => ['ACTIVE' => 'Y', 'CATEGORY_ID' => 5],
     *     'runtime' => [
     *         new \Bitrix\Main\Entity\ExpressionField('USER_COUNT', 'COUNT(%s)', 'ID')
     *     ]
     * ];
     *
     * $data = ['STATUS' => 'APPROVED', 'MODIFIED_BY' => 1];
     *
     * $result = MyTable::updateWhere($parameters, $data);
     *
     * // Обновление с использованием Query
     * $query = MyTable::query()
     *     ->setFilter(['>=DATE_CREATE' => '2024-01-01'])
     *     ->registerRuntimeField('USER_COUNT', new \Bitrix\Main\Entity\ExpressionField('USER_COUNT', 'COUNT(%s)', 'ID'));
     *
     * $result = MyTable::updateWhere($query, ['PROCESSED' => 'Y']);
     *
     * // Обновление с использованием ConditionTree
     * $filter = Query::filter()->where('ACTIVE', true);
     * MyTable::updateWhere($filter, ['PROCESSED' => 'Y']);
     * </code>
     */
    public static function updateWhere(array|Query|ConditionTree $parameters, array $data): UpdateResult
    {
        $result = new UpdateResult();

        if ($parameters instanceof ConditionTree) {
            $parameters = static::query()->where($parameters);
        }

        $query = $parameters instanceof Query
            ? $parameters
            : static::createBatchQuery($parameters);

        if (!Arr::isAssoc($data)) {
            throw new \InvalidArgumentException('data must be an associative array');
        }

        try {
            $selectSql = $query->getQuery();
            if (preg_match(
                '/^SELECT\s.*?\sFROM(\s.*?)(\s(?:LEFT |RIGHT |INNER )?JOIN\s.*?)?(\sWHERE\s.*?)?$/si',
                $selectSql,
                $match
            )) {
                $entity = static::getEntity();
                $connection = $entity->getConnection();
                $helper = $connection->getSqlHelper();

                $tableName = $entity->getDBTableName();
                $tableAlias = $helper->quote($query->getInitAlias());
                $dataReplacedColumn = static::replaceFieldName($data);
                $update = $helper->prepareUpdate($tableName, $dataReplacedColumn);
                $update[0] = $tableAlias . '.' . str_replace(', ', ', ' . $tableAlias . '.', $update[0]);

                $sql = 'UPDATE ' . $match[1] . $match[2] . ' SET ' . $update[0] . $match[3];

                dump($sql);
                $connection->queryExecute($sql, $update[1]);
                $result->setAffectedRowsCount($connection);
            } else {
                throw new Main\SystemException('invalid updateBatch query');
            }
        } catch (\Exception $e) {
            $result->isSuccess();

            throw $e;
        }

        return $result;
    }

    /**
     * Массовое обновление через VALUES + JOIN.
     * Поддерживает составные первичные ключи и разные СУБД.
     *
     * @param array $primaries Массив первичных ключей
     *        Для простого ключа: [1, 2, 3]
     *        Для составного ключа: [['CATEGORY_ID' => 1, 'PRODUCT_ID' => 10], ...]
     * @param array $fieldsList Массив данных для обновления, соответствующий по порядку $primaries
     * @return Main\Entity\UpdateResult
     *
     * @throws ArgumentException
     *
     * <code>
     * // Простой ключ
     * $primaries = [1, 2, 3];
     * $primaries = [['ID' => 1], ['ID' => 2], ['ID' => 3]]
     * $fieldsList = [
     *     ['STATUS' => 'active', 'PRICE' => 100],
     *     ['STATUS' => 'inactive', 'PRICE' => 200],
     *     ['PRICE' => 300]
     * ];
     * MyTable::updateAll($primaries, $fieldsList);
     *
     * // Составной ключ
     * $primaries = [
     *     ['CATEGORY_ID' => 1, 'PRODUCT_ID' => 10],
     *     ['CATEGORY_ID' => 1, 'PRODUCT_ID' => 11],
     *     ['CATEGORY_ID' => 2, 'PRODUCT_ID' => 10]
     * ];
     * $fieldsList = [
     *     ['QUANTITY' => 5, 'POSITION' => 1],
     *     ['QUANTITY' => 8, 'POSITION' => 2],
     *     ['QUANTITY' => 3]
     * ];
     * MyTable::updateAll($primaries, $fieldsList);
     * </code>
     */
    public static function updateAll(array $primaries, array $fieldsList): UpdateResult
    {
        $result = new UpdateResult();

        if (empty($primaries) || empty($fieldsList)) {
            return $result;
        }

        if (count($primaries) !== count($fieldsList)) {
            throw new Main\ArgumentException(
                'Number of primaries must match number of fields sets'
            );
        }

        $connection = Application::getConnection();
        $tableName = static::getTableName();
        $entity = static::getEntity();
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
                    throw new Main\ArgumentException(
                        sprintf(
                            '%s Entity has no `%s` field.',
                            $entity->getName(),
                            $field
                        )
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
                        $rowData[] = $helper->convertToDb(null, $fieldObject);;
                    }
                }

                $values[] = '(' . implode(', ', $rowData) . ')';
            }

            $sql = SqlHelper::buildCrossPlatformUpdateSql($tableName, $primaryFields, $allFields, $values);

            $connection->queryExecute($sql);

            $connection->commitTransaction();

            $result->setAffectedRowsCount($connection);

        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            $result->isSuccess();
            throw $e;
        }

        return $result;
    }

    /**
     * Упрощенный метод массового обновления для простых случаев.
     * Обновляет одно поле для множества записей
     *
     * @param array $primaries Массив первичных ключей
     * @param string $fieldName Имя поля для обновления
     * @param mixed $value Значение для установки
     * @return UpdateResult
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
     * Пакетное удаление записей по условию
     *
     * @param array|Query $parameters Массив параметров или объект Query для фильтрации записей
     *                               Поддерживаемые параметры:
     *                               - 'filter' - условия фильтрации
     *                               - 'runtime' - runtime поля
     * @return DeleteResult
     * @throws Main\SystemException При невалидном запросе
     *
     * <code>
     * // Удаление по фильтру
     * $parameters = [
     *     'filter' => [
     *         'ACTIVE' => 'N',
     *         '<=DATE_CREATE' => '2023-01-01'
     *     ]
     * ];
     *
     * $result = MyTable::deleteWhere($parameters);
     *
     * // Удаление с runtime полями
     * $parameters = [
     *     'filter' => ['CATEGORY_ID' => 10],
     *     'runtime' => [
     *         'ITEM_COUNT' => new \Bitrix\Main\Entity\ExpressionField('ITEM_COUNT', 'COUNT(%s)', 'ID')
     *     ]
     * ];
     *
     * $result = MyTable::deleteBatchWhere($parameters);
     * </code>
     */
    public static function deleteWhere(array|Query|ConditionTree $parameters)
    {
        $result = new Main\Entity\DeleteResult();

        if ($parameters instanceof ConditionTree) {
            $parameters = static::query()->where($parameters);
        }

        $query = $parameters instanceof Query
            ? $parameters
            : static::createBatchQuery($parameters);

        try {
            $selectSql = $query->getQuery();

            if (preg_match('/^SELECT\s.*?\s(FROM\s.*)$/si', $selectSql, $match)) {
                $entity = static::getEntity();
                $connection = $entity->getConnection();
                $helper = $connection->getSqlHelper();
                $sql = 'DELETE ' . $helper->quote($query->getInitAlias()) . ' ' . $match[1];
                dump($sql);
                $connection->queryExecute($sql);
            } else {
                throw new Main\SystemException('invalid deleteBatch query');
            }
        } catch (\Exception $exception) {
            // check result to avoid warning
            $result->isSuccess();

            throw $exception;
        }

        return $result;
    }

    public static function deleteWherePrimary(int|array $data)
    {
        if (is_int($data) || Arr::isAssoc($data)) {
            return static::delete($data);
        }

        $condition = Query::filter();
        foreach ($data as $value) {
            if (Arr::isAssoc($value)) {

            }
        }
    }

    /**
     * Обновляет таблицу (полями)
     * Пока только создает новые
     *
     * @param Connection $connection
     * @return Main\Result
     * @throws Main\ArgumentException
     * @throws Main\SystemException
     * @throws SqlQueryException
     */
    public static function migrate(Connection $connection): Main\Result
    {
        $entity = static::getEntity();
        return StorageFacade::addNewFields($connection, $entity);
    }

    protected static function fillWhereFilter(ConditionTree $filter, array $params)
    {
        $exParams = $params;
        unset($exParams[0]);

        foreach ($params[0] as $field) {
            $filter->where($field, ...array_values($exParams));
        }
    }

    public static function getScalarMap()
    {
        $result = [];
        $map = static::getMap();

        foreach ($map as $field) {
            if ($field instanceof Fields\ScalarField || $field instanceof Fields\ExpressionField) {
                $result[] = $field->getName();
            }
        }

        return $result;
    }

    public static function getName()
    {
        return message(static::getLangKey());
    }

    public static function getLangKey()
    {
        return 'UNKNOWN';
    }

    public static function getFieldEnumTitle($fieldName, $optionValue, Main\Entity\Field $field = null)
    {
        $result = null;

        if ($field === null) {
            $entity = static::getEntity();
            $field = $entity->getField($fieldName);
        }

        if ($field) {
            $fieldEnumLangKey = $field->getLangCode() . '_ENUM_';
            $optionValueLangKey = str_replace(['.', ' ', '-'], '_', $optionValue);
            $optionValueLangKey = Str::toUpper($optionValueLangKey);

            $result = message($fieldEnumLangKey . $optionValueLangKey);
        }

        if ($result === null) {
            $result = $optionValue;
        }

        return $result;
    }

    protected static function parseWhereArgs($args)
    {
        unset($args[0]);
        return [
            ...array_values($args)
        ];
    }

    protected static function createBatchQuery($parameters)
    {
        $query = static::query();

        foreach ($parameters as $param => $value) {
            switch ($param) {
                case 'filter':
                    $query->setFilter($value);
                    break;

                case 'runtime':
                    foreach ($value as $name => $fieldInfo) {
                        $query->registerRuntimeField($name, $fieldInfo);
                    }
                    break;

                default:
                    throw new Main\ArgumentException("Unknown parameter: " . $param, $param);
            }
        }

        return $query;
    }

    protected static function validatePrimaries(array $primaries, array $primaryFields, bool $isComposite): void
    {
        foreach ($primaries as $index => $primary) {
            if ($isComposite) {
                if (!is_array($primary)) {
                    throw new Main\ArgumentException(
                        sprintf('Primary key at index %d must be array for composite key', $index)
                    );
                }

                foreach ($primaryFields as $field) {
                    if (!isset($primary[$field])) {
                        throw new Main\ArgumentException(
                            sprintf('Missing primary key field "%s" at index %d', $field, $index)
                        );
                    }
                }
            } else {
                if (is_array($primary)) {
                    throw new Main\ArgumentException(
                        sprintf('Primary key at index %d must be scalar for simple key', $index)
                    );
                }
            }
        }
    }

    protected static function getPrimaryId($primary)
    {
        if (is_array($primary) && count($primary) === 1) {
            return end($primary);
        }

        return $primary;
    }

    public static function getEnumTitle($value)
    {
        return static::getEnumValuesTitle()[$value] ?: $value;
    }

    protected static function getEnumValuesTitle(): array
    {
        return [];
    }
}
