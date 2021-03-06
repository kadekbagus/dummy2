<?php

namespace Orbit\Database;

use Illuminate\Database\Query\Builder;
use Request;

/**
 * Extended laravel query builder with small additions.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ExtendedQueryBuilder extends Builder
{
    /**
     * Add support for conditional query.
     *
     * So far, we would create an instance of our model.
     * $collection = Model::select(*);
     *
     * then, if we want to do a conditional filtering/query, we would do
     * if ($condition) {
     *     $query->where('field', 'keyword');
     * }
     *
     * $query = $query->get();
     *
     * -------------------------------
     *
     * Now with when(), we could achieve the same thing without
     * creating an instance or making ten of conditional check/IFs.
     *
     * Model::when($condition, function($query) {
     *     return $query->where('field', 'keyword');
     * })
     * ->when($condition2, function($query) {
     *     return $query->where('field2', 'keyword2');
     * })->get();
     *
     * @todo create an extended Illuminate DB builder
     *       which add this functionality.
     *
     * @param  mixed $value the conditional value check.
     * @return  Closure $callback the callback that will be run
     *                            if condition is met.
     *
     * @return Illuminate\Database\Query\Builder
     */
    public function when($value, $callback)
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Shortcut for when() with request input as conditional check param.
     *
     * @param  [type] $inputName [description]
     * @param  [type] $callback  [description]
     * @return [type]            [description]
     */
    public function whenHas($inputName, $callback)
    {
        return $this->when(Request::input($inputName), $callback);
    }
}
