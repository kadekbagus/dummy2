<?php
/**
 * Class to represent the product_attribute_values table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class ProductAttributeValue extends Eloquent
{
    protected $primaryKey = 'product_attribute_value_id';
    protected $table = 'product_attribute_values';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Value belong to attribute
     */
    public function attribute()
    {
        return $this->belongsTo('ProductAttribute', 'product_attribute_id', 'product_attribute_id');
    }

    /**
     * The one who create this attribute value.
     */
    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    /**
     * The one who edit this attribute value.
     */
    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    /**
     * Attribute values belongs to merchant ids.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function scopeMerchantIds($query, array $merchantIds)
    {
        return $query->select('product_attribute_values.*')
                     ->join('product_attributes',
                           'product_attributes.product_attribute_id',
                           '=',
                           'product_attribute_values.product_attribute_id'
                     )->whereIn('product_attributes.merchant_id', $merchantIds);
    }
}
