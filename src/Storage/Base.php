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
use MB\Bitrix\Migration\Facades\Storage as StorageFacade;
use MB\Bitrix\Storage\Concerns\BuildIndexes;
use MB\Bitrix\Storage\Concerns\BatchUpsertTrait;
use MB\Bitrix\Storage\Concerns\UpdateByWhereTrait;
use MB\Bitrix\Storage\Concerns\MassUpdateTrait;
use MB\Bitrix\Storage\Concerns\DeleteByQueryTrait;

/**
 * DataManager base with bulk DB operations.
 *
 * Batch upsert via {@see addBatch()} is cross-platform for MySQL and PostgreSQL.
 *
 * @method static Query query()
 * @method static \Bitrix\Main\Entity\AddResult addBatch(array $dataList, bool|array $updateOnDuplicate = false)
 * @method static \Bitrix\Main\Entity\UpdateResult updateWhere(array|Query|ConditionTree $parameters, array $data)
 * @method static \Bitrix\Main\Entity\UpdateResult updateAll(array $primaries, array $fieldsList)
 * @method static \Bitrix\Main\Entity\UpdateResult updateBatch(array $primaries, string $fieldName, mixed $value)
 * @method static \Bitrix\Main\Entity\DeleteResult deleteWhere(array|Query|ConditionTree $parameters)
 * @method static \Bitrix\Main\Entity\DeleteResult deleteWherePrimary(int|array $data)
 */
abstract class Base extends DataManager
{
    use BuildIndexes;
    use BatchUpsertTrait;
    use UpdateByWhereTrait;
    use MassUpdateTrait;
    use DeleteByQueryTrait;

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

    // DB heavy operations are implemented in traits (see Storage\\Concerns\\*).

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

    public static function getEnumTitle($value)
    {
        $titles = static::getEnumValuesTitle();

        return ($titles[$value] ?? null) ?: $value;
    }

    protected static function getEnumValuesTitle(): array
    {
        return [];
    }
}
