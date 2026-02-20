<?php

namespace MB\Bitrix\EntityView;

use Bitrix\Main\Grid\Column\Type;
use Bitrix\Main\ORM\Entity as ORMEntity;
use Bitrix\Main\ORM\Fields;
use MB\Support\Str;
use MB\Bitrix\UI;

class Helper
{
    /** Row id delimiter for composite primary (must match BaseRowAssembler::PRIMARY_DELIMITER). */
    public const PRIMARY_DELIMITER = '|';

    public static function getUrlByFields(string $urlTemplate, ORMEntity $entity, array $fields)
    {
        $search = [];
        $replace = [];
        foreach ($entity->getPrimaryArray() as $primary) {
            $search[] = "#{$primary}#";
            $replace[] = $fields[$primary];
        }

        foreach ($search as $primaryCode => $template) {
            $replace[] = $fields[$primaryCode];
        }

        return str_replace(array_values($search), $replace, $urlTemplate);
    }

    public static function getPrimaryValues(ORMEntity $entity, array $fields)
    {
        $primary = array_fill_keys($entity->getPrimaryArray(), null);
        array_walk($primary, fn(&$value, $key) => $value = $fields[$key]);

        return $primary;
    }

    /**
     * Parse grid row id string back to primary key array.
     * For composite primary, row id is "val1|val2" (PRIMARY_DELIMITER).
     *
     * @param ORMEntity $entity
     * @param string $rowId
     * @return array<string, mixed> primary key name => value
     */
    public static function parseRowIdToPrimary(ORMEntity $entity, string $rowId): array
    {
        $primaryNames = $entity->getPrimaryArray();
        if (count($primaryNames) === 1) {
            return [$primaryNames[0] => $rowId];
        }
        $parts = explode(self::PRIMARY_DELIMITER, $rowId);
        $result = [];
        foreach ($primaryNames as $i => $name) {
            $result[$name] = $parts[$i] ?? null;
        }
        return $result;
    }

    public static function getGridIdByEntity(ORMEntity $entity): string
    {
        return 'GRID_' . $entity->getDBTableName();
    }

    public static function getFilterIdByEntity(ORMEntity $entity): string
    {
        return 'FILTER_' . $entity->getDBTableName();
    }

    public static function extractGridOptions(array $array)
    {
        return self::extractByPrefix('GRID_', $array);
    }

    public static function extractFilterOptions(array $array)
    {
        return self::extractByPrefix('FILTER_', $array);
    }

    public static function extractCommonOptions(array $array)
    {
        return self::extractByPrefix('COMMON_', $array);
    }

    public static function deleteByPrefix(array $prefix, array &$array)
    {
        foreach ($array as $key => $value) {
            foreach ($prefix as $p) {
                if (Str::position($key, $p) === 0) {
                    unset($array[$key]);
                    break;
                }
            }

        }
    }

    protected static function extractByPrefix(string $prefix, array $array)
    {
        $result = [];
        $options = array_filter($array, function ($key) use ($prefix) {
            return Str::position($key, $prefix) === 0;
        }, ARRAY_FILTER_USE_KEY);

        foreach ($options as $name => $value) {

            $name = $name === $prefix . 'ID' ? $name : str_replace($prefix, '', $name);
            $result[$name] = $value;
        }

        unset($options);

        return $result;
    }

    public static function getColumnTypeByField(Fields\Field $field)
    {
        if ($field instanceof Fields\StringField || !$field instanceof Fields\ScalarField) {
            return Type::TEXT;
        } elseif ($field instanceof Fields\BooleanField) {
            return Type::CHECKBOX;
        } elseif ($field instanceof Fields\DateField || $field instanceof Fields\DateTimeField) {
            return Type::DATE;
        } elseif ($field instanceof Fields\EnumField) {
            return Type::DROPDOWN;
        } elseif ($field instanceof Fields\FloatField || $field instanceof Fields\IntegerField) {
            return Type::NUMBER;
        }

        return Type::TEXT;
    }

    public static function getUiFieldByOrmField(Fields\Field $field)
    {
        $result = null;
        if (method_exists($field, 'isPrimary') && $field->isPrimary()) {
            $result = (new UI\Control\Field\NonEditableField($field->getName()))->configureRenderInput();
        }

        if (method_exists($field, 'isPrivate') && $field->isPrivate()) {
            $result = new UI\Control\Field\NonEditableField($field->getName());
        }

        if (!$result) {
            if ($field instanceof Fields\ExpressionField) {
                //$result = (new UI\Control\Field\StringField());
            } elseif ($field instanceof Fields\StringField) {
                $result = new UI\Control\Field\TextField($field->getName());
            } elseif (!$field instanceof Fields\ScalarField) {
                //$result = new UI\Control\Field\StringField();
            } elseif ($field instanceof Fields\BooleanField) {
                $result = new UI\Control\Field\SwitcherField($field->getName());
            } elseif ($field instanceof Fields\DateField || $field instanceof Fields\DateTimeField) {
                $result = new UI\Control\Field\CalendarField($field->getName());
            } elseif ($field instanceof Fields\EnumField) {
                $result = new UI\Control\Field\DialogSelectorField($field->getName());
                $values = $field->getValues();
                $fieldDataClass = $field->getEntity()->getDataClass();
                $items = [];
                foreach ($values as $v) {
                    if (method_exists($fieldDataClass, 'getEnumTitle')) {
                        $title = $fieldDataClass::getEnumTitle($v);
                    } else {
                        $title = $v;
                    }
                    $items[] = [
                        'id' => $v,
                        'title' => $title
                    ];
                }
                $result->setTabsContent([
                    'tab' => [
                        'title' => $field->getTitle() ?: $field->getName(),
                        'items' => $items
                    ],
                ]);
            } elseif ($field instanceof Fields\FloatField || $field instanceof Fields\IntegerField) {
                $result = new UI\Control\Field\NumberField($field->getName());
            }
        }

        if ($val = $field->getDefaultValue()) {
            $result->setDefaultValue($val);
        }

        return $result;
    }
}
