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
        return IssuedCoupon::whereIn('status', ['issued', 'reserved'])
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
        return (int) $availableForRedeem;
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

    /**
     * test if a user has issued ongoing coupon transfer
     */
    private function userHasOngoingCouponTransfer($userId, $couponId)
    {
        $issuedCoupons = IssuedCoupon::select('issued_coupon_id')
            ->where('user_id', $userId)
            ->where('promotion_id', $couponId)
            ->where('status', 'issued')
            ->where('expired_date', '>', Carbon::now())
            ->where('transfer_status', 'in_progress')
            ->get();
        return ! $issuedCoupons->isEmpty();
    }

    private function userHasUniqueCoupon($userId, $couponId)
    {
        $claimedUniqueCoupon = IssuedCoupon::select('issued_coupon_id')
            ->where('promotion_id', $couponId)
            ->where(function ($qry) use ($userId) {
                $qry->where(function($query) use ($userId) {
                    //original user of non transfered coupon is considered the one that is
                    //restricted to get another unique coupon after coupon is redeemed
                    $query->where('user_id', $userId)
                        ->whereNull('transfer_status')
                        ->whereNotIn('status', ['issued', 'deleted']);
                })->orWhere(function($query) use ($userId) {
                    //original user of transfered coupon is considered the one that is
                    //restricted to get another unique coupon
                    $query->where('original_user_id', $userId)
                        ->where('transfer_status', 'complete');
                });
            })
            ->first();

        return ! empty($claimedUniqueCoupon);
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

        // Force availability to 0 if max issued coupon reached
        if ($coupon->total_issued >= $coupon->maximum_issued_coupon) {
            $coupon->available = 0;
            $coupon->available_coupons_count = 0;
        }

        $coupon->hasRedeemableCoupon = $this->userHasRedeemableCoupon(
            $user->user_id,
            $coupon->promotion_id
        ) && ($coupon->available_for_redeem > 0);

        $coupon->hasOngoingCouponTransfer = $this->userHasOngoingCouponTransfer(
            $user->user_id,
            $coupon->promotion_id
        );

        // set maximum redeemed to maximum issued when empty
        if ($coupon->maximum_redeem === '0') {
            $coupon->maximum_redeem = $coupon->maximum_issued_coupon;
        }

        return $coupon;
    }

}
