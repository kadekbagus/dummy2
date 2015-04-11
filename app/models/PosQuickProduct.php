<?php
/**
 * Class for represent the activities table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class PosQuickProduct extends Eloquent
{
    protected $primaryKey = 'pos_quick_product_id';
    protected $table = 'pos_quick_products';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'product_id');
    }

    public function scopeJoinRetailer()
    {
        return $this->select('pos_quick_products.*')
                    ->join('products', function($join) {
                        $join->on('products.product_id', '=', 'pos_quick_products.product_id');
                        $join->on('products.status', '=', DB::raw('"active"'));
                    })
                    ->join('product_retailer', 'product_retailer.product_id', '=', 'pos_quick_products.product_id')
                    ->groupBy('pos_quick_products.product_id');
    }

    public function scopeJoinMerchant()
    {
        return $this->select('pos_quick_products.*')
                    ->join('products', function($join) {
                        $join->on('products.product_id', '=', 'pos_quick_products.product_id');
                        $join->on('products.status', '=', DB::raw('"active"'));
                    })
                    ->leftJoin('product_retailer', 'product_retailer.product_id', '=', 'pos_quick_products.product_id')
                    ->leftJoin('merchants', function($join) {
                        $join->on('merchants.merchant_id', '=', 'product_retailer.retailer_id');
                        $join->on('merchants.status', '=', DB::raw('"active"'));
                        $join->on('merchants.object_type', '=', DB::raw('"retailer"'));
                    })
                    ->groupBy('pos_quick_products.product_id');
    }
}
