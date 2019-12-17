<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository;

use Carbon\Carbon;
use IssuedCoupon;

/**
 * class that test if user has redeemable coupon available for particular coupon id.
 * This is mostly used in coupon detail to enable disable redeem button in
 * coupon detail page
 *
 * @author Zamroni <amroni@dominopos.com>
 */
class IssuedCouponRepository
{
    private function getTotalRedeemed($couponId)
    {
        return IssuedCoupon::where('status', 'redeemed')
            ->where('promotion_id', $couponId)
            ->count();
    }

    private function getTotalIssued($couponId)
    {
        return IssuedCoupon::where('status', 'issued')
            ->where('promotion_id', $couponId)
            ->count();
    }

    private function getStats($couponId)
    {
        $totalIssued = $this->getTotalIssued($couponId);
        $totalRedeemed = $this->getTotalRedeemed($couponId);
        return (object) [
            'total' => $totalIssued + $totalRedeemed,
            'total_redeemed' => $totalRedeemed,
        ];
    }

    private function getAvailableForRedeem($initialAvail, $maxRedeem, $totalRedeemed)
    {
        $availableForRedeem = $initialAvail;
        if ($maxRedeem > 0) {
            $availableForRedeem = $maxRedeem - $totalRedeemed;
            if ($totalRedeemed >= $maxRedeem) {
                $availableForRedeem = 0;
            }
        }
        return  (int) $availableForRedeem;
    }

    /**
     * test if a user has issued coupon available for redeem
     */
    private function userHasRedeemableCoupon($userId, $couponId)
    {
        $issuedCoupons = IssuedCoupon::select('issued_coupon_id')
            ->where('user_id', $userId)
            ->where('promotion_id', $couponId)
            ->where('status', 'issued')
            ->where('expired_date', '>', Carbon::now())
            ->where(function($q){
                $q->whereNull('transfer_status')
                    ->orWhere('transfer_status', 'complete');
            })
            ->get();
        return ! $issuedCoupons->isEmpty();
    }

    private function userHasUniqueCoupon($userId, $couponId)
    {
        $checkIssued = IssuedCoupon::where('promotion_id', $couponId)
                                    ->where(function($query) use ($userId) {
                                        $query->where('user_id', $userId)
                                              ->orWhere('original_user_id', $userId);
                                   })
                                   ->whereNull('transfer_status')
                                   ->whereNotIn('status', ['issued', 'deleted'])
                                   ->first();

        return ! empty($checkIssued);
    }

    public function addIssuedCouponData($coupon, $user)
    {
        // unique coupon
        $coupon->get_unique_coupon = 'true';
        if ($coupon->is_unique_redeem === 'Y' && $user->role->role_name != 'Guest') {
            if ($this->userHasUniqueCoupon($user->user_id, $coupon->promotion_id)) {
                $coupon->get_unique_coupon = 'false';
            }
        }

        $stats = $this->getStats($coupon->promotion_id);

        // get total redeemed
        $coupon->total_redeemed = $stats->total_redeemed;
        $coupon->available_for_redeem = $this->getAvailableForRedeem(
            $coupon->available,
            $coupon->maximum_redeem,
            $stats->total_redeemed
        );
        // get total issued
        $coupon->total_issued = $stats->total;

        $coupon->hasRedeemableCoupon = $this->userHasRedeemableCoupon(
            $user->user_id,
            $coupon->promotion_id
        ) && ($coupon->available_for_redeem > 0);

        // set maximum redeemed to maximum issued when empty
        if ($coupon->maximum_redeem === '0') {
            $coupon->maximum_redeem = $coupon->maximum_issued_coupon;
        }

        return $coupon;
    }

}
