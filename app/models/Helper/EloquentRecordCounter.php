<?php namespace Helper;
/**
 * A helper class for counting records on Eqoluent based query builder. This
 * helper used to wrap the original query into a sub query so we got the right
 * result.
 *
 * @auhor Rio Astamal <me@rioastamal.net>
 * @credit http://stackoverflow.com/questions/24823915/how-to-select-from-subquery-using-laravel-query-builder/24838367#24838367
 */
use DB;

class EloquentRecordCounter
{
    /**
     * Query builder for aggregate
     *
     * @var QueryBuilder
     */
    protected $builder = NULL;

    /**
     * Class constructor
     *
     * @param Builder $builder
     */
    public function __construct($model)
    {
        // Turn the eloquent builder into SQL string
        $sql = $model->toSql();
        $query = $model;

        // Get the query instance of the builder
        if ($model instanceof \Illuminate\Database\Eloquent\Builder) {
            $query = $model->getQuery();
        }

        // Use raw SQL Count statement
        $this->builder = DB::table( DB::raw("({$sql}) as subquery") )
                   ->mergeBindings($query);
    }

    /**
     * Static method to instantiate the class
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Builder $builder
     * @return EloquentRecordsCounter
     */
    public static function create($model)
    {
        return new static($model);
    }

    /**
     * Main method to count the records.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return int
     */
    public function count()
    {
        return $this->builder->count();
    }

    /**
     * Get the query builder
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->builder;
    }
}
