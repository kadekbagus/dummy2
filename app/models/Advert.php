<?php
class Advert extends Eloquent
{
    /**
     * Advert Model
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'adverts';
    protected $primaryKey = 'advert_id';

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'advert_id')
                    ->where('object_name', 'advert');
    }

}