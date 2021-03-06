<?php
class Object extends Eloquent
{
    /**
     * Object Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'objects';

    protected $primaryKey = 'object_id';

    public function mall()
    {
        return $this->belongsTo('Mall', 'merchant_id', 'merchant_id');
    }

    public function users()
    {
        return $this->belongsToMany('User', 'object_relation', 'main_object_id', 'secondary_object_id')
                    ->withPivot('main_object_type', 'secondary_object_type')
                    ->where('object_relation.main_object_type', 'bank')
                    ->where('object_relation.secondary_object_type', 'user');
    }

}
