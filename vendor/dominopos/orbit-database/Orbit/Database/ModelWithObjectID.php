<?php namespace Orbit\Database;

use Illuminate\Database\Eloquent\Model;
use Orbit\Database\Relation\BelongToManyWithObjectID;

class ModelWithObjectID extends Model {
    public $incrementing = false;

    public function save(array $options = array())
    {
        if (! $this->exists )
        {
            $key = $this->getKey();

            if (ObjectID::isValid($key)) {
                $key = new ObjectID($key);
            } else {
                $key = ObjectID::make();
            }

            $this->setAttribute($this->getKeyName(), (string) $key);
        }

        parent::save();
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
