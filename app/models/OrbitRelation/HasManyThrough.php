<?php namespace OrbitRelation;
/**
 * Extends Illuminate\Database\Eloquent\Relations to prodive the third key
 * (the parent key name) for comparing on join.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Database\Eloquent\Relations\HasManyThrough as HMT;

class HasManyThrough extends HMT
{
    /**
     * The key name on the parent.
     *
     * @var string
     */
    protected $parentKeyName;

    /**
     * Create a new has many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $parentKey
     * @return void
     */
    public function __construct(Builder $query, Model $farParent, Model $parent, $firstKey, $secondKey, $parentKey)
    {
        $this->parentKeyName = $parentKey;
        parent::__construct($query, $farParent, $parent, $firstKey, $secondKey);
    }

    /**
     * Set the join clause on the query and make sure it uses the parent key name.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $query
     * @return void
     */
    protected function setJoin(Builder $query = null)
    {
        $query = $query ?: $this->query;

        $foreignKey = $this->related->getTable() . '.' . $this->secondKey;

        if (! empty($this->parentKeyName)) {
            $parentKey = $this->parent->getTable() . '.' . $this->parentKeyName;
        } else {
            $parentKey = $this->getQualifiedParentKeyName();
        }

        $query->join($this->parent->getTable(), $parentKey, '=', $foreignKey);
    }
}
