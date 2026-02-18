<?php

namespace MB\Bitrix\Storage;

use Bitrix\Main\ORM\Query\Query as BitrixQuery;
use Bitrix\Main\SystemException;
use Bitrix\Main\ORM\Query\Filter\ConditionTree as Filter;

/**
 * Query builder for entities based on Bitrix D7 ORM.
 *
 * Основная задача — предоставить более удобный fluent‑интерфейс
 * для where*, having*, with*‑методов, делегируя вызовы либо:
 *  - в статические методы DataManager (#whereAny/#withRelation и т.п.),
 *  - либо в {@see Filter}, если метод там поддерживается.
 *
 * Virtual WHERE methods (proxy to Filter):
 *
 * @override
 *
 * @method $this where(...$filter)
 * @see Filter::where()
 *
 * @method $this whereNot(...$filter)
 * @see Filter::whereNot()
 *
 * @method $this whereColumn(...$filter)
 * @see Filter::whereColumn()
 *
 * @method $this whereNull($column)
 * @see Filter::whereNull()
 *
 * @method $this whereNotNull($column)
 * @see Filter::whereNotNull()
 *
 * @method $this whereIn($column, $values)
 * @see Filter::whereIn()
 *
 * @method $this whereNotIn($column, $values)
 * @see Filter::whereNotIn()
 *
 * @method $this whereBetween($column, $valueMin, $valueMax)
 * @see Filter::whereBetween()
 *
 * @method $this whereNotBetween($column, $valueMin, $valueMax)
 * @see Filter::whereNotBetween()
 *
 * @method $this whereLike($column, $value)
 * @see Filter::whereLike()
 *
 * @method $this whereNotLike($column, $value)
 * @see Filter::whereNotLike()
 *
 * @method $this whereExists($query)
 * @see Filter::whereExists()
 *
 * @method $this whereNotExists($query)
 * @see Filter::whereNotExists()
 *
 * @method $this whereMatch($column, $value)
 * @see Filter::whereMatch()
 *
 * @method $this whereNotMatch($column, $value)
 * @see Filter::whereNotMatch()
 *
 * @method $this whereExpr($expr, $arguments)
 * @see Filter::whereExpr()
 *
 * @method $this whereAny(array $columns, $operator, $value)
 * @see Base::whereAny()
 *
 * @method $this whereAll(array $columns, $operator, $value)
 * @see Base::whereAll()
 *
 * @method $this whereNone(array $columns, $operator, $value)
 * @see Base::whereNone()
 *
 */
class Query extends BitrixQuery
{
    public function __call($method, $arguments)
    {
        // where and having proxies
        if (str_starts_with($method, 'having')) {
            $method = str_replace('having', 'where', $method);
        }

        if (str_starts_with($method, 'where')) {
            $dataClass = $this->entity->getDataClass();
            if (method_exists($dataClass, $method)) {
                // set query as first element
                array_unshift($arguments, $this);

                call_user_func_array(
                    [$dataClass, $method],
                    $arguments
                );

                return $this;
            } elseif (method_exists($this->filterHandler, $method)) {
                call_user_func_array(
                    [$this->filterHandler, $method],
                    $arguments
                );

                return $this;
            }
        }

        if (str_starts_with($method, 'with')) {
            $dataClass = $this->entity->getDataClass();

            if (method_exists($dataClass, $method)) {
                // set query as first element
                array_unshift($arguments, $this);

                call_user_func_array(
                    [$dataClass, $method],
                    $arguments
                );

                return $this;
            }
        }

        throw new SystemException(
            sprintf(
                'Unknown query helper method `%s`. Only where*/having*/with* methods '
                . 'defined either on the DataManager (%s) or on the internal Filter (%s) are supported.',
                $method,
                $this->entity->getDataClass(),
                Filter::class
            )
        );
    }
}