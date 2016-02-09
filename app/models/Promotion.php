<?php
class Promotion extends Eloquent
{
    /**
     * Promotion Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Use Trait PromotionTypeTrait so we only displaying records with value
     * `is_coupon` = 'N'
     */
    use PromotionCouponTrait;

    /**
     * Column name which determine the type of Promotion or Coupon.
     */
    const OBJECT_TYPE = 'is_coupon';

    protected $table = 'promotions';

    protected $primaryKey = 'promotion_id';

    protected $hidden = array('is_coupon', 'maximum_issued_coupon', 'coupon_validity_in_days', 'coupon_notification');

    public function promotionrule()
    {
        return $this->hasOne('PromotionRule', 'promotion_id', 'promotion_id');
    }

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function retailers()
    {
        return $this->belongsToMany('Retailer', 'promotion_retailer', 'promotion_id', 'retailer_id');
    }

    public function scopeProductPromotionType($query)
    {
        return $query->where('promotions.promotion_type', '=', 'product');
    }

    public function scopeCartPromotionType($query)
    {
        return $query->where('promotions.promotion_type', '=', 'cart');
    }

    public function scopePermanent($query)
    {
        return $query->where('promotions.is_permanent', '=', 'Y');
    }

    /**
     * Promotion strings can be translated to many languages.
     */
    public function translations()
    {
        return $this->hasMany('PromotionTranslation', 'promotion_id', 'promotion_id')->excludeDeleted()->whereHas('language', function($has) {
            $has->where('merchant_languages.status', 'active');
        });
    }

    /**
     * Add Filter promotions based on user who request it.
     *
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

        // This will filter only promotions which belongs to merchant
        // The merchant owner has an ability to view all promotions
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->whereRaw("{$prefix}promotions.merchant_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.user_id=? and m2.object_type='merchant')", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Promotion has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'promotion_id')
                    ->where('object_name', 'promotion');
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
            return 'mobile-ci/images/default_promotion.png';
        }
        return ($value);
    }
}
