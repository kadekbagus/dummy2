<?php
class TransactionDetail extends Eloquent
{
    /**
    * Transaction model
    *
    * @author kadek <kadek@dominopos.com>
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'transaction_details';

    protected $primaryKey = 'transaction_detail_id';

    public function transaction()
    {
        return $this->belongsTo('Transaction', 'transaction_id', 'transaction_id');
    }

    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'product_id');
    }

    public function productVariant()
    {
        return $this->belongsTo('ProductVariant', 'product_variant_id', 'product_variant_id');
    }

    /**
     * Simple join with transaction table
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @param array|int $valueId - List of value ids
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTransactionJoin($builder)
    {
        return $builder->select('transaction_details.*')
                       ->join('transactions', function($join) {
                            $join->on('transactions.transaction_id', '=', 'transaction_details.transaction_id');
                            $join->where('transactions.status', '=', DB::raw('paid'));
                       });
    }

    /**
     * Simple join with transaction table
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @param array|int $valueId - List of value ids
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeDeletedTransaction($builder)
    {
        return $builder->where('transactions.status', '!=', 'deleted');
    }

    /**
     * Get transactions details which has particular product attribute value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @param array|int $valueId - List of value ids
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAttributeValueIds($builder, $ids=array())
    {
        $prefix = DB::getTablePrefix();
        return $builder->select('products.product_name',
                              DB::raw('transpose_variant.product_attribute_id,
                              transpose_variant.product_attribute_name,
                              transpose_variant.value_id product_attribute_value_id,
                              transpose_variant.attribute_value_name'),
                              'transaction_details.*')
                     ->join('products', 'products.product_id', '=', 'transaction_details.product_id')
                     ->join('product_variants', 'product_variants.product_variant_id', '=', 'transaction_details.product_variant_id')
                     ->join('transactions', 'transactions.transaction_id', '=', 'transaction_details.transaction_id')
                     ->join(DB::raw("(
                            select vr.product_variant_id, vr.product_id, vr.value_id, vr.number,
                            pav.value attribute_value_name, par.product_attribute_name, par.product_attribute_id
                            from
                            (
                                select product_id, product_variant_id, product_attribute_value_id1 as value_id, '1' as number
                                from orbit_shop.orb_product_variants
                                union all
                                select product_id, product_variant_id, product_attribute_value_id2 as value_id, '2' as number
                                from orbit_shop.orb_product_variants
                                union all
                                select product_id, product_variant_id, product_attribute_value_id3 as value_id, '3' as number
                                from orbit_shop.orb_product_variants
                                union all
                                select product_id, product_variant_id, product_attribute_value_id4 as value_id, '4' as number
                                from orbit_shop.orb_product_variants
                                union all
                                select product_id, product_variant_id, product_attribute_value_id5 as value_id, '5' as number
                                from orbit_shop.orb_product_variants
                            ) as vr
                            join orb_product_attribute_values pav on pav.product_attribute_value_id=vr.value_id
                            join orb_product_attributes par on par.product_attribute_id=pav.product_attribute_id
                            where vr.value_id is not null
                    ) transpose_variant"), DB::raw('`transpose_variant`.`product_variant_id`'), '=', 'product_variants.product_variant_id')
                    ->whereIn(DB::raw('`transpose_variant`.`value_id`'), $ids);
    }
}
