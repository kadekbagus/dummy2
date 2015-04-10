<?php
/**
 * Class for representing Media table
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class Media extends Eloquent
{
    /**
     * Override default table name so it does not include 's'
     *
     * @var string
     */
    protected $table = 'media';

    /**
     * Do not show these fields when converting the model to array or JSON
     *
     * @var array
     */
    protected $hidden = array('realpath', 'metadata');

    /**
     * Table's primary key
     *
     * @var string
     */
    protected $primaryKey = 'media_id';

    /**
     * This media belongs to a Merchant
     */
    public function merchant()
    {
        return $this->belongsTo('Merchant', 'object_id', 'merchant_id');
    }

    /**
     * This media belongs to a Retailer
     */
    public function retailer()
    {
        return $this->belongsTo('Retailer', 'object_id', 'merchant_id');
    }

    /**
     * This media belongs to a User
     */
    public function user()
    {
        return $this->belongsTo('User', 'object_id', 'user_id');
    }

    /**
     * This media belongs to a Product
     */
    public function product()
    {
        return $this->belongsTo('Product', 'object_id', 'product_id');
    }

    /**
     * Scope to search media which mime type is 'image'
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function scopeTypeImage($query)
    {
        $query->where(DB::Raw("SUBSTRING('mime_type', 1, 5)"), '=', 'image');
    }

    /**
     * Accessor for default product picture
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     */
    public function getPathAttribute($value)
    {
        if(is_null($value)){
            return '/mobile-ci/images/default-product.png';
        } else {
            return $value;
        }
    }
}
