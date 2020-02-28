<?php

namespace Orbit\Database;

/**
 * @author Yudi Rahono
 * @author William Shallum
 * @author Rio Astamal <rio@dominopos.com>
 * @desc Extends original model to support unique ID and some other goodies.
 */
use Illuminate\Database\Eloquent\Model;
use Orbit\Database\ExtendedQueryBuilder;
use Orbit\Database\Relation\BelongToManyWithObjectID;

class ModelWithObjectID extends Model
{
    public $incrementing = false;

    public static function boot()
    {
        parent::boot();

        static::creating(function(ModelWithObjectID $model) {
            $model->assignObjectID();
        });
    }

    public function assignObjectID()
    {
        if (! $this->exists )
        {
            $key = $this->getKey();

            if (ObjectID::isValid($key)) {
                $key = new ObjectID($key);
            } else if (preg_match('/^[0-9]+ *$/', (string)$key)) {
                // XXX to handle LMP ID this has to accept raw integer IDs.
                // Handle trailing spaces too just in case MySQL decides to
                // right pad them on read.
                $key  = (string)$key;
            } else {
                $key = ObjectID::make();
            }

            $this->setAttribute($this->getKeyName(), (string) $key);
        }
    }

    /**
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = array())
    {
        $this->assignObjectID();
        return parent::save();
    }

    public function belongsToManyObjectID($related, $table = null, $foreignKey = null, $otherKey = null, $pivotKey = null, $relation = null)
    {
        if (is_null($relation))
        {
            $relation = $this->getBelongsToManyCaller();
        }

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        /** @var Model $instance */
        $instance = new $related;

        $otherKey = $otherKey ?: $instance->getForeignKey();

        if (is_null($table))
        {
            $table = $this->joiningTable($related);
        }

        $query = $instance->newQuery();

        return new BelongToManyWithObjectID($query, $this, $table, $foreignKey, $otherKey, $pivotKey, $relation);
    }

    public function belongsToMany($related, $table = null, $foreignKey = null, $otherKey = null, $relation = null, $pivotKey = null)
    {
        $pivotKey = $table . '_id';
        return $this->belongsToManyObjectID($related, $table, $foreignKey, $otherKey, $pivotKey, $relation);
    }

    /**
     * Force the model to use write connection.
     * Downside: Once you execute this method the rest of the script will be using
     * write connection. Use find below
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function onWriteConnection()
    {
        $instance = new static;
        $instance->getConnection()->setReadPdo(NULL);

        return $instance->newQuery();
    }

    /**
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Illuminate\Support\Collection|static
     */
    public static function findOnWriteConnection($id, $columns = array('*'))
    {
        $readPdo = static::resolveConnection()->getReadPdo();
        static::resolveConnection()->setReadPdo(NULL);

        $result = parent::find($id, $columns);

        // Set the read PDO back to previous object so other object that
        // uses the ConnectionResolver will not affected
        static::resolveConnection()->setReadPdo($readPdo);
        $readPdo = NULL;

        return $result;
    }

    /**
     * Override default behaviour of creating new Builder instance.
     *
     * @see Illuminate\Database\Eloquent\Model:1819
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        $grammar = $conn->getQueryGrammar();

        return new ExtendedQueryBuilder($conn, $grammar, $conn->getPostProcessor());
    }
}
