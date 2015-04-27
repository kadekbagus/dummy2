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
        return $this->belongsTo('Retailer', 'merchant_id', 'merchant_id')->isMall();
    }

}
