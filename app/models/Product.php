<?php

class Product extends Eloquent
{
    /**
    * Product Model
    *
    * @author Ahmad Anshori <ahmad@dominopos.com>
    * @author Tian <tian@dominopos.com>
    * @author Rio Astamal <me@rioastamal.net>
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'products';

    protected $primaryKey = 'product_id';

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function category1()
    {
        return $this->belongsTo('Category', 'category_id1', 'category_id');
    }

    public function category2()
    {
        return $this->belongsTo('Category', 'category_id2', 'category_id');
    }

    public function category3()
    {
        return $this->belongsTo('Category', 'category_id3', 'category_id');
    }

    public function category4()
    {
        return $this->belongsTo('Category', 'category_id4', 'category_id');
    }

    public function category5()
    {
        return $this->belongsTo('Category', 'category_id5', 'category_id');
    }

    public function tax1()
    {
        return $this->belongsTo('MerchantTax', 'merchant_tax_id1', 'merchant_tax_id');
    }

    public function tax2()
    {
        return $this->belongsTo('MerchantTax', 'merchant_tax_id2', 'merchant_tax_id');
    }

    public function scopeFeatured($query)
    {
        return $query->where('products.is_featured', '=', 'Y');
    }

    public function retailers()
    {
        return $this->belongsToMany('Retailer', 'product_retailer', 'product_id', 'retailer_id');
    }

    public function suggestions()
    {
        return $this->belongsToMany('Product', 'product_suggestion', 'product_id', 'suggested_product_id');
    }

    public function cartdetail()
    {
        return $this->belongsTo('CartDetail', 'product_id', 'product_id');
    }

    public function variants()
    {
        $variants = $this->hasMany('ProductVariant', 'product_id', 'product_id')
                         ->excludeDeleted('product_variants')
                         ->orderBy('created_at', 'desc');

        if (Config::get('model:product.variant.exclude_default', NULL) === 'yes') {
            $variants->excludeDefault();
        }

        if (Config::get('model:product.variant.include_transaction_status', NULL) === 'yes') {
            $variants->includeTransactionStatus();
        }

        return $variants;
    }

    public function variantsNoDefault()
    {
        return $this->hasMany('ProductVariant', 'product_id', 'product_id')
                    ->excludeDeleted()
                    ->excludeDefault()
                    ->orderBy('created_at', 'desc');
    }

    public function attribute1()
    {
        return $this->belongsTo('ProductAttribute', 'attribute_id1', 'product_attribute_id');
    }

    public function attribute2()
    {
        return $this->belongsTo('ProductAttribute', 'attribute_id2', 'product_attribute_id');
    }

    public function attribute3()
    {
        return $this->belongsTo('ProductAttribute', 'attribute_id3', 'product_attribute_id');
    }

    public function attribute4()
    {
        return $this->belongsTo('ProductAttribute', 'attribute_id4', 'product_attribute_id');
    }
    public function attribute5()
    {
        return $this->belongsTo('ProductAttribute', 'attribute_id5', 'product_attribute_id');
    }

    /**
     * Add Filter retailers based on user who request it.
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
     */
    public function scopeAllowedForUser($builder, $user)
    {
        // Super admin allowed to see all entries
        $superAdmin = Config::get('orbit.security.superadmin');
        if (empty($superAdmin))
        {
            $superAdmin = array('super admin');
        }

        // Transform all array into lowercase
        $superAdmin = array_map('strtolower', $superAdmin);
        $userRole = trim(strtolower($user->role->role_name));
        if (in_array($userRole, $superAdmin))
        {
            // do nothing return as is
            return $builder;
        }

        // This will filter only products which belongs to merchant
        // The merchant owner has an ability to view all products
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->whereRaw("{$prefix}products.merchant_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.user_id=?)", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Product has many uploaded media.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'product_id')
                    ->where('object_name', 'product');
    }

    /**
     * Get last attribute index number of an object of Product. In example,
     * if 'attribute_id1' and 'attribute_id2' filled then the return value
     * would be '2'. If none of the attribute id are filled then the output
     * would be '1'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return int
     */
    public function getLastAttributeIndexNumber()
    {
        for ($i=5; $i>=1; $i--) {
            if (! empty($this->{'attribute_id' . $i})) {
                break;
            }
        }

        return $i;
    }

    /**
     * Determine whether particular attribute Id are already on the product.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $attributeId - The attribute id to check
     * @return boolean
     */
    public function isAttributeIdExists($id)
    {
        $ids = array();

        // Loop through all the attribute_idX field
        for ($i=1; $i<=5; $i++) {
            if (! empty($this->{'attribute_id' . $i})) {
                $ids[] = (string)$this->{'attribute_id' . $i};
            }
        }

        if (in_array((string)$id, $ids)) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Accessor for empty product image
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param string $value - image path
     * @return string $value
     */
    public function getImageAttribute($value)
    {
        if (empty($value)) {
            return 'mobile-ci/images/default_product.png';
        }
        return ($value);
    }
}
