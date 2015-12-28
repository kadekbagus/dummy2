<?php namespace Orbit\Database;

use Illuminate\Database\Eloquent\Model;
use Orbit\Database\Relation\BelongToManyWithObjectID;

class ModelWithObjectID extends Model {
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

}
