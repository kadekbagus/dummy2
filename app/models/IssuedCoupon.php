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

    const STATUS_AVAILABLE      = 'available';
    const STATUS_RESERVED       = 'reserved';
    const STATUS_ISSUED         = 'issued';
    const STATUS_REDEEMED       = 'redeemed';

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

    /**
     * IssuedCoupon - PaymentTransaction relation.
     *
     * @return [type] [description]
     */
    public function payment()
    {
        return $this->belongsTo('PaymentTransaction', 'transaction_id', 'payment_transaction_id');
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
        return $query->where('issued_coupons.status', self::STATUS_AVAILABLE);
    }

    public function scopeIssued($query)
    {
        return $query->where('issued_coupons.status', self::STATUS_ISSUED);
    }

    public function scopeRedeemed($query)
    {
        return $query->where('issued_coupons.status', self::STATUS_REDEEMED);
    }

    public static function totalAvailable($promotionId) {
        $available = static::where('status', self::STATUS_AVAILABLE)->where('promotion_id', $promotionId)->count();
        return $available;
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
                    'status' => self::STATUS_AVAILABLE,
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

    public static function bulkIssueGiftN($couponCodes, $promotionId, $couponValidityDate, $admin = NULL) {
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
                    'issued_coupon_code' => 'gift_n_coupon',
                    'url' => $couponCodes[$i],
                    'expired_date' => $couponValidityDate,
                    'issuer_user_id' => $issuerUserId,
                    'status' => self::STATUS_AVAILABLE,
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
     * @param string $userId (from wallet)
     * @param string $issuedCouponCode (from SMS)
     * @return IssuedCoupon | null
     */
    public function issueCouponViaWallet($promotionId, $userEmail, $userId = NULL, $issuedCouponCode = NULL)
    {
        // get 'issued' coupon with the same user_email
        $issuedCoupon = static::issued()
            ->where('user_id', $userId)
            ->where('promotion_id', $promotionId)
            ->first();

        // if issuedCouponCode is supplied then use it instead
        // From SMS
        if (! is_null($issuedCouponCode)) {
            $issuedCoupon = static::issued()
                ->where('promotion_id', $promotionId)
                ->where('user_id', $userId)
                ->where('issued_coupon_code', $issuedCouponCode)
                ->first();
        }
        if (! is_object($issuedCoupon)) {
            // get available issued coupon
            $issuedCoupon = static::available()
                ->where('promotion_id', $promotionId)
                ->whereNull('user_id')
                ->first();

            // if issuedCouponCode is supplied then use it instead
            // From SMS
            if (! is_null($issuedCouponCode)) {
                $issuedCoupon = static::available()
                    ->where('promotion_id', $promotionId)
                    ->where('issued_coupon_code', $issuedCouponCode)
                    ->whereNull('user_id')
                    ->first();
            }
            if (is_object($issuedCoupon)) {
                // set user_email to it and make it issued
                $issuedCoupon->user_id = $userId;
                $issuedCoupon->user_email = $userEmail;
                $issuedCoupon->issued_date = date('Y-m-d H:i:s');
                $issuedCoupon->status = self::STATUS_ISSUED;
                $issuedCoupon->save();
            }
        }
        return $issuedCoupon;
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
     * @param string $userId (from wallet)
     * @return IssuedCoupon | null
     */
    public function issueCouponViaEmail($promotionId, $userEmail, $userId = NULL)
    {
        // get 'issued' coupon with the same user_email
        $issuedCoupon = static::join('users', 'users.user_id', '=', 'issued_coupons.user_id')
            ->join('roles', 'users.user_role_id', '=', 'roles.role_id')
            ->where('roles.role_name', 'Guest')
            ->where('issued_coupons.status', self::STATUS_ISSUED)
            ->where('promotion_id', $promotionId)
            ->where('users.user_email', $userEmail)
            ->first();
        if (! is_object($issuedCoupon)) {
            // get available issued coupon
            $issuedCoupon = static::available()
                ->where('promotion_id', $promotionId)
                ->whereNull('user_email')
                ->first();
            if (is_object($issuedCoupon)) {
                // set user_email to it and make it issued
                $issuedCoupon->user_id = $userId;
                $issuedCoupon->user_email = $userEmail;
                $issuedCoupon->issued_date = date('Y-m-d H:i:s');
                $issuedCoupon->status = self::STATUS_ISSUED;
                $issuedCoupon->save();
            } else {
                $issuedCoupon = NULL;
            }
        }
        return $issuedCoupon;
    }

    /**
     * Make this issued coupon available for purchase again.
     *
     * @return [type] [description]
     */
    public function makeAvailable()
    {
        $this->user_id = NULL;
        $this->user_email = NULL;
        $this->transaction_id = NULL;
        $this->issued_date = NULL;
        $this->status = self::STATUS_AVAILABLE;

        $this->touch();
    }
}
