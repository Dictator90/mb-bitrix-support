<?php

namespace MB\Bitrix\Storage;

use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Objectify\Collection;
use Bitrix\Main\ORM\Objectify\IdentityMap;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;
use Bitrix\Main\ORM\Query\QueryHelper as BitrixQueryHelper;
use \Bitrix\Main\ORM\Query\Query as BitrixQuery;
use Bitrix\Main\ORM\Fields\Relations;
use Bitrix\Main\Security\Random;

class QueryHelper extends BitrixQueryHelper
{
    public static function decompose(BitrixQuery $query, $fairLimit = true, $separateRelations = true): ?Collection
    {
        $entity = $query->getEntity();
        $oldQuery = $query;
        $primaryNames = $entity->getPrimaryArray();
        $originalSelect = $query->getSelect();

        if ($fairLimit) {
            // select distinct primary
            $query->setSelect($entity->getPrimaryArray());
            $query->setDistinct();
            $rows = $query->fetchAll();

            // return empty result
            if (empty($rows)) {
                return $query->getEntity()->createCollection();
            }

            // reset query
            $query = $entity->getDataClass()::query();
            $query->setSelect($originalSelect);
            $query->where(static::getPrimaryFilter($primaryNames, $rows));

            // save sort
            self::addOrderByField($query, $primaryNames[0], array_column($rows, $primaryNames[0]));
        }


        // search runtime field in query
        // Собираем сущности зарегестрированные с помощью registerRuntimeField
        // и регистрируем заново в новом query
        $refFields = [];
        foreach ($oldQuery->getRuntimeChains() as $chain) {
            foreach ($chain->getAllElements() as $element) {
                $class = $element->getValue();
                if ($class instanceof Relations\Reference) {
                    $refFields[] = $class->getName();
                    $class->resetEntity();
                    $query->registerRuntimeField($class);
                }
            }
        }

        // more than one OneToMany or ManyToMany
        if ($separateRelations) {
            $commonSelect = [];
            $dividedSelect = [];

            foreach ($originalSelect as $selectItem) {
                // Ищем поля runtime из выборки и записываем их в $commonSelect
                // Дальнейшие манипуляции пропускаем
                $exSelectItem = explode('.', $selectItem)[0];
                if (in_array($exSelectItem, $refFields)) {
                    $commonSelect[] = $selectItem;
                    continue;
                }
                // init query with select item
                $selQuery = $entity->getDataClass()::query();
                $selQuery->addSelect($selectItem);
                $selQuery->getQuery(true);

                // check for relations
                foreach ($selQuery->getChains() as $chain) {
                    if ($chain->hasBackReference()) {
                        $dividedSelect[] = $selectItem;
                        continue 2;
                    }
                }

                $commonSelect[] = $selectItem;
            }

            if (empty($commonSelect)) {
                $commonSelect = $query->getEntity()->getPrimaryArray();
            }

            // common query
            $query->setSelect($commonSelect);
        }

        /** @var Collection $collection query data */
        $collection = $query->fetchCollection();

        if (!empty($dividedSelect) && $collection->count()) {
            // custom identity map & collect primaries
            $im = new IdentityMap;
            $primaryValues = [];

            foreach ($collection as $object) {
                $im->put($object);
                $primaryValues[] = $object->primary;
            }

            $primaryFilter = static::getPrimaryFilter($primaryNames, $primaryValues);
            // select relations
            foreach ($dividedSelect as $selectItem) {
                $result = $entity->getDataClass()::query()
                    ->addSelect($selectItem)
                    ->where($primaryFilter)
                    ->exec();

                $result->setIdentityMap($im);
                $result->fetchCollection();
            }
        }

        return $collection;
    }

    public static function addOrderByField(BitrixQuery $query, string $field, array $values)
    {
        $name = 'SORT_PRIMARY_' . Random::getString(3);
        $query->registerRuntimeField(
            new ExpressionField(
                $name,
                'FIELD(%s, ' . implode(', ', $values) . ')',
                $field
            )
        );
        $query->addOrder($name);
    }

    public static function addOrderByRandom(BitrixQuery $query)
    {
        $name = 'SORT_RAND_' . Random::getString(3);
        $query->registerRuntimeField(
            new ExpressionField($name, 'RAND()')
        );
        $query->addOrder($name);
    }

    public static function convertLegacyFilterToConditionTree(array $filter): ConditionTree
    {
        $conditionTree = Query::filter();

        if (isset($filter['LOGIC'])) {
            $logic = strtolower($filter['LOGIC']);
            unset($filter['LOGIC']);
            $conditionTree->logic($logic);
        }

        foreach ($filter as $key => $value) {
            if (is_numeric($key)) {
                $subFilter = self::convertLegacyFilterToConditionTree($value);
                $conditionTree->where($subFilter);
            } else {
                $operator = '=';
                $field = $key;

                // Определение оператора из ключа
                if (str_starts_with($key, '=')) {
                    $operator = '=';
                    $field = substr($key, 1);
                } elseif (str_starts_with($key, '!')) {
                    $operator = '!=';
                    $field = substr($key, 1);
                } elseif (str_starts_with($key, '>=')) {
                    $operator = '>=';
                    $field = substr($key, 2);
                } elseif (str_starts_with($key, '<=')) {
                    $operator = '<=';
                    $field = substr($key, 2);
                } elseif (str_starts_with($key, '>')) {
                    $operator = '>';
                    $field = substr($key, 1);
                } elseif (str_starts_with($key, '<')) {
                    $operator = '<';
                    $field = substr($key, 1);
                } elseif (str_contains($key, '%')) {
                    $operator = 'like';
                }

                if (is_array($value)) {
                    $operator = 'in';
                }

                if (preg_match('/^PROPERTY_([\w]+?)(?:_(VALUE))?(?:\.([\w.]+))?$/i', $field, $matches)) {
                    $propertyCode = $matches[1];
                    $isEnum = $matches[2] ?? false;
                    $elemLink = $matches[3] ?? false;

                    if ($isEnum) {
                        $field = $propertyCode . '.ITEM.VALUE';
                    } elseif ($elemLink) {
                        //todo: create for section
                        $field = $propertyCode . '.ELEMENT.' . $elemLink;
                    } else {
                        $field = $propertyCode . '.VALUE';
                    }
                }

                // Добавление условия в дерево
                switch ($operator) {
                    case '=':
                        $conditionTree->where($field, $value);
                        break;
                    case '!=':
                        $conditionTree->whereNot($field, $value);
                        break;
                    case 'like':
                        $conditionTree->whereLike($field, $value);
                        break;
                    case 'in':
                        $conditionTree->whereIn($field, $value);
                        break;
                    default:
                        $conditionTree->where($field, $operator, $value);
                }
            }
        }

        return $conditionTree;
    }

    public static function isLegacyFilter(array $filter): bool
    {
        foreach ($filter as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'PROPERTY_')) {
                return true;
            }

            foreach (self::getLegacyOperatorPrefixes() as $op) {
                if (is_string($key) && str_starts_with($key, $op)) {
                    return true;
                }
            }

            if ($key === 'LOGIC' && is_string($value)) {
                return true;
            }
        }

        return false;
    }

    protected static function getLegacyOperatorPrefixes()
    {
        return ['!', '>', '<', '>=', '<=', '!=', '%', '!%', '=', '@'];
    }
}
