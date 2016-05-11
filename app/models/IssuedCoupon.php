<?php
class IssuedCoupon extends Eloquent
{
    /**
     * IssuedCoupon Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    const ISSUE_COUPON_INCREMENT = 1111110;

    protected $table = 'issued_coupons';

    protected $primaryKey = 'issued_coupon_id';

    public function coupon()
    {
        return $this->belongsTo('Coupon', 'promotion_id', 'promotion_id');
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function issuerretailer()
    {
        return $this->belongsTo('Retailer', 'issuer_retailer_id', 'merchant_id');
    }

    public function redeemretailer()
    {
        return $this->belongsTo('Tenant', 'redeem_retailer_id', 'merchant_id');
    }

    /**
     * Redeem Retailer has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function redeemRetailerMedia()
    {
        return $this->hasMany('Media', 'object_id', 'redeem_retailer_id')
                    ->where('object_name', 'retailer');
    }

    /**
     * Save issued coupon based on promotion object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Promotion $promotion
     * @param int User ID
     * @param User $admin - The one who issue the coupon
     * @return IssuedCoupon
     */
    public function issue($promotion, $userId, $admin, $retailer = NULL)
    {
        $maxIssueId = DB::table('issued_coupons')->max(DB::raw('CAST(issued_coupon_code as UNSIGNED)'));
        if (empty($maxIssueId)) {
            $maxIssueId = static::ISSUE_COUPON_INCREMENT;
        }
        $issued = new static();
        $issued->promotion_id = $promotion->promotion_id;
        $issued->issued_coupon_code = $maxIssueId + 1;
        $issued->user_id = $userId;
        $issued->expired_date = $promotion->coupon_validity_in_date;
        $issued->issued_date = date('Y-m-d H:i:s');
        $issued->status = 'active';
        if (! is_null($retailer)) {
            $issued->issuer_retailer_id = $retailer->merchant_id;
        }
        $issued->save();

        return $issued;
    }
}
