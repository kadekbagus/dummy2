<?php namespace POS;

use Illuminate\Database\Eloquent\Model as Eloquent;
use ModelStatusTrait;

class Product extends Eloquent
{
    /**
    * Product Model
    *
    * @author Ahmad Anshori <ahmad@dominopos.com>
    * @author Tian <tian@dominopos.com>
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'products';
    
    protected $primaryKey = 'product_id';

    public function categories()
    {
        return $this->belongsToMany('Category', 'product_category', 'product_id', 'product_id');
    }

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
}