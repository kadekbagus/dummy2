<?php
use Orbit\Database\ObjectID;

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

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeRedeemed($query)
    {
        return $query->where('status', 'redeemed');
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
    public function issue($promotion, $userId = NULL, $admin = NULL, $retailer = NULL)
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

    /**
     * Bulk release issued coupon
     * Make multiple issued coupon available by coupon codes
     * Available means that this issued coupon is not gotten by user
     * Proper validation is expected before accessing this method
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @param array $couponCodes
     * @param string $promotionId
     * @param string $couponValidityDate
     * @param User $admin
     * @return void
     */
    public static function bulkIssue($couponCodes, $promotionId, $couponValidityDate, $admin = NULL) {
        $issuerUserId = NULL;
        if (! is_null($admin)) {
            $issuerUserId = $admin->user_id;
        }

        // create array of data
        $data = array();
        $now = date('Y-m-d H:i:s');
        for ($i = 0; $i < count($couponCodes); $i++) {
            $data[] = array(
                    'issued_coupon_id' => ObjectID::make(),
                    'promotion_id' => $promotionId,
                    'issued_coupon_code' => $couponCodes[$i],
                    'expired_date' => $couponValidityDate,
                    'issuer_user_id' => $issuerUserId,
                    'status' => 'available',
                    'created_at' => $now,
                    'updated_at' => $now
                );
        }

        // create collection from array
        $collection = new \Illuminate\Database\Eloquent\Collection($data);
        // chunk into smaller pieces,
        // optimum 1000 items
        // 9000 array item will raise error
        $chunks = $collection->chunk(1000);
        //convert chunk to array
        $chunks->toArray();

        //loop through chunks:
        foreach($chunks as $chunk) {
            DB::table('issued_coupons')->insert($chunk->toArray());
        }
    }

    /**
     * Get available coupon code
     * If there are already 'issued' coupon with user_email return those issued coupon
     * else return 'issued' coupon with user_email
     * Proper validation is expected before accessing this method
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @param string $promotionId
     * @param string $userEmail
     * @return IssuedCoupon | null
     */
    public function issueCoupon($promotionId, $userEmail) {
        // get 'issued' coupon with the same user_email
        $issuedCoupon = static::issued()
            ->where('promotion_id', $promotionId)
            ->where('user_email', $userEmail)
            ->first();

        if (! is_object($issuedCoupon)) {
            // get available issued coupon
            $issuedCoupon = static::available()
                ->where('promotion_id', $promotionId)
                ->whereNull('user_email')
                ->first();

            if (is_object($issuedCoupon)) {
                // set user_email to it and make it issued
                $issuedCoupon->user_email = $userEmail;
                $issuedCoupon->issued_date = date('Y-m-d H:i:s');
                $issuedCoupon->status = 'issued';
                $issuedCoupon->save();
            }
        }

        return $issuedCoupon;
    }
}
