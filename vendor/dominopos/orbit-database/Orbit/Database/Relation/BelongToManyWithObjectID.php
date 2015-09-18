<?php namespace Orbit\Database\Relation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Orbit\Database\ObjectID;

class BelongToManyWithObjectID extends BelongsToMany {
    /**
     * @var string pivot primary key
     */
    protected $key;

    public function __construct(Builder $query, Model $parent, $table, $foreignKey, $otherKey, $pivotKey, $relationName = null)
    {
        parent::__construct($query, $parent, $table, $foreignKey, $otherKey, $relationName);

        $this->key = $pivotKey;
    }

    protected function createAttachRecord($id, $timed)
    {
        $record = parent::createAttachRecord($id, $timed);
        $record[$this->key] = (string) ObjectID::make();
        return $record;
    }
}
