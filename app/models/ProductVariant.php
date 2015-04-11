<?php
/**
 * Class to represent product_variants table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class ProductVariant extends Eloquent
{
    protected $primaryKey = 'product_variant_id';
    protected $table = 'product_variants';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Product variant belongs to a merchant.
     */
    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    /**
     * Product variant belongs to a retailer (shop).
     */
    public function retailer()
    {
        return $this->belongsTo('Retailer', 'retailer_id', 'merchant_id');
    }

    /**
     * Product variant belongs to a product.
     */
    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'product_id');
    }

    /**
     * Product variant belongs to a transaction detail
     */
    public function transactionDetail()
    {
        return $this->belongsTo('TransactionDetail', 'product_variant_id', 'product_variant_id');
    }

    /**
     * Product variant atribute belongs to a product attribute value.
     */
    public function attributeValue1()
    {
        return $this->belongsTo('ProductAttributeValue', 'product_attribute_value_id1', 'product_attribute_value_id');
    }
    public function attributeValue2()
    {
        return $this->belongsTo('ProductAttributeValue', 'product_attribute_value_id2', 'product_attribute_value_id');
    }
    public function attributeValue3()
    {
        return $this->belongsTo('ProductAttributeValue', 'product_attribute_value_id3', 'product_attribute_value_id');
    }
    public function attributeValue4()
    {
        return $this->belongsTo('ProductAttributeValue', 'product_attribute_value_id4', 'product_attribute_value_id');
    }
    public function attributeValue5()
    {
        return $this->belongsTo('ProductAttributeValue', 'product_attribute_value_id5', 'product_attribute_value_id');
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
     * Scope to get the most complete record which the product attribute values
     * has been filled.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMostCompleteValue($builder)
    {
        $field = 'product_variants.product_attribute_value_id';
        for ($i=5; $i>=1; $i--) {
            $builder->orderBy($field . $i, 'desc');
        }

        return $builder;
    }

    /**
     * Scope to get the newest records of product variant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNewest($builder)
    {
        return $builder->orderBy('product_variants.created_at', 'desc');
    }

    /**
     * Scope to exclude the default variant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeDefault($builder)
    {
        return $builder->where('default_variant', 'no');
    }

    /**
     * Scope to determine product variant with transaction details and add custom
     * attribute named 'has_transaction' which hold value 'yes' or 'no'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @todo This a slow query - rewrite it later.
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIncludeTransactionStatus($builder)
    {
        $prefix = DB::getTablePrefix();
        return $builder->select('product_variants.*', DB::Raw("IF(IFNULL({$prefix}transactions.transaction_id, 'yes'), 'yes', 'no') AS has_transaction"))
                       ->leftJoin('transaction_details', 'transaction_details.product_variant_id', '=', 'product_variants.product_variant_id')
                       ->leftJoin('transactions', function($join) {
                            $join->on('transactions.status', '!=', DB::Raw("'deleted'"));
                            $join->on('transactions.transaction_id', '=', 'transaction_details.transaction_id');
                       })
                       ->groupBy('product_variants.product_variant_id');
    }

    /**
     * Static method to create default variant
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Product $product Instance of Product Object
     * @param int $creator User ID who create the product
     * @param
     * @return ProductVariant
     */
    public static function createDefaultVariant($product)
    {
        // Check for existing record first
        $variant = Static::where('product_id', $product->product_id)
                         ->where('default_variant', 'yes')
                         ->excludeDeleted()
                         ->first();

        if (empty($variant)) {
            // No need to create
            $variant = new static();
        }

        $variant->product_id = $product->product_id;
        $variant->price = $product->price;
        $variant->upc = $product->upc_code;
        $variant->sku = $product->product_code;
        $variant->merchant_id = $product->merchant_id;
        $variant->default_variant = 'yes';
        $variant->created_by = $product->created_by;
        $variant->modified_by = $product->modified_by;
        $variant->status = 'active';
        $variant->save();

        return $variant;
    }
}
