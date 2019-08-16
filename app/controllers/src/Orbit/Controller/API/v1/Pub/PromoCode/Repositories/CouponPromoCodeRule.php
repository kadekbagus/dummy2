<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use Discount;
use DiscountCode;
use ObjectDiscount;
use Coupon;

/**
 * Class which determine if user is eligible to get promo code when purchase coupon
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
class CouponPromoCodeRule extends AbstractPromoCodeRule implements RuleInterface
{

    /**---------------------------------------------
     * Check if asked quantity by user is eligible
     * based on number use per user and number use per transaction
     * where least of them is used.
     *----------------------------------------------
     * @param Discount $promo, discount object
     * @param Coupon $coupon, coupon object
     * @param User $user, user object
     * @param int $qty, asked quantity
     * @return Object eligible status
     * ---------------------------------------------
     */
    private function isEligibleForQuantity($promo, $coupon, $user, $qty, $isFinalCheck)
    {
        $totalUsage = $user->discountCodes()
            ->where('discount_id', $promo->discount_id)
            ->issuedOrWaitingPayment()
            ->count();

        if (! $isFinalCheck) {
            $totalReservedFoAllCoupon = $user->discountCodes()
                ->where('discount_id', $promo->discount_id)
                ->reservedNotWaitingPayment()
                ->count();
            $totalReservedForCurrentCoupon = $user->discountCodes()
                ->where('discount_id', $promo->discount_id)
                ->where('object_id', $coupon->promotion_id)
                ->where('object_type', 'coupon')
                ->reservedNotWaitingPayment()
                ->count();
            $totalReserved = $totalReservedFoAllCoupon - $totalReservedForCurrentCoupon;
        } else {
            $totalReserved = 0;
        }


        $maxPerUser = $this->getMaxAllowedQtyPerUser($promo, $coupon);
        $quotaPerUser = $maxPerUser - $totalUsage - $totalReserved;
        $quotaUsagePerTransaction = $this->getMaxAllowedQtyPerTransaction($promo, $coupon);

        $allowedQty = min($quotaPerUser, $quotaUsagePerTransaction);
        if ($allowedQty < 0) {
            $allowedQty = 0;
        }

        $allowedQty = min($allowedQty, $qty);

        return (object) [
            'eligible' => ($allowedQty > 0),
            'allowedQty' => $allowedQty
        ];
    }

    /**---------------------------------------------
     * Check if asked quantity by user is less or equal than
     * total available discount code
     *----------------------------------------------
     * @param Discount $promo, promo instance
     * @param User $user, current user
     * @param int $qty, asked quantity
     * @return Object eligible status
     * ---------------------------------------------
     */
    private function isEligibleForAvailQuantity($promo, $objectId, $user, $qty)
    {
        $totalAvail = DiscountCode::where('discount_id', $promo->discount_id)
            ->available()
            ->count();

        return (object) [
            'eligible' => ($totalAvail >= $qty),
            'allowedQty' => $totalAvail,
        ];
    }

    /**---------------------------------------------
     * get allowed purchase per user between
     * promo code vs coupon. The less value will be returned
     *----------------------------------------------
     * @param Discount $promo
     * @param Coupon $coupon
     * @return int least value between two
     * ---------------------------------------------
     */
    private function getMaxAllowedQtyPerUser($promo, $coupon)
    {
        if (empty($coupon->max_quantity_per_user)) {
            //zero value in coupon is used to marked unlimited use
            //so for this special use case, we will automatically
            //use data from promo
            return $promo->max_per_user;
        }
        return min($promo->max_per_user, $coupon->max_quantity_per_user);
    }

    /**---------------------------------------------
     * get allowed purchase per transaction between
     * promo code vs coupon. The less value will be returned
     *----------------------------------------------
     * @param Discount $promo
     * @param Coupon $coupon
     * @return int least value between two
     * ---------------------------------------------
     */
    private function getMaxAllowedQtyPerTransaction($promo, $coupon)
    {
        if (empty($coupon->max_quantity_per_purchase)) {
            //zero value in coupon is used to marked unlimited use
            //so for this special use case, we will automatically
            //use data from promo
            return $promo->max_per_transaction;
        }
        return min($promo->max_per_transaction, $coupon->max_quantity_per_purchase);
    }

    /**---------------------------------------------
     * Check user eligiblity for promo code
     *----------------------------------------------
     * @param User $user, current logged in user
     * @param StdClass $promoData, promo code data
     *---------------------------------------------
     * Note : this method assumes data has been validated
     * So promo code must be exists and active.
     * ---------------------------------------------
     */
    public function getEligibleStatus($user, $promoData)
    {
        $promo = $this->getPromoCodeDetail($promoData);

        $rejectReason = '';

        $eligible = $this->isEligibleForObjectType(
            $promo->discount_id,
            $promoData->object_id,
            $promoData->object_type
        );

        $allowedQty = 0;
        $adjustedQty = 0;

        if ($eligible) {

            $qtyEligible = $this->isEligibleForQuantity(
                $promo,
                Coupon::find($promoData->object_id),
                $user,
                $promoData->quantity,
                $promoData->is_final_check
            );

            $availQtyEligible = $this->isEligibleForAvailQuantity(
                $promo,
                $promoData->object_id,
                $user,
                $promoData->quantity
            );

            $allowedQty = min($qtyEligible->allowedQty, $availQtyEligible->allowedQty);
            $eligible = ($allowedQty > 0);
            if (! $eligible) {
                $rejectReason = 'REMAINING_AVAIL_DISCOUNT_CODE_LESS_THAN_REQUESTED_QTY';
            }
        } else {
            $rejectReason = 'DISCOUNT_CODE_NOT_APPLICABLE_TO_PURCHASED_ITEM';
        }

        //if asked quantity > allowed quantity
        //adjust qty and if adjustedQty is greater than zero assume eligible
        $adjustedQty = min($allowedQty, $promoData->quantity);
        $eligible = $eligible || ($adjustedQty > 0);

        return (object) [
            'promo_id' => $promo->discount_id,
            'promo_title' => $promo->discount_title,
            'promo_code' => $promo->discount_code,
            'eligible' => $eligible,

            //when eligible = false, rejectReason contains code why user
            //is not eligible for discount othweise this is empty string
            'rejectReason' => ! $eligible ? $rejectReason : '',

            'avail_quota_count' => $allowedQty,
            'original_quantity' => $promoData->quantity,
            'adjusted_quantity' => $adjustedQty,

            'user_id' => $user->user_id,
            'object_type' => $promoData->object_type,
            'object_id' => $promoData->object_id,
            'value_in_percent' => $promo->value_in_percent,
            'start_date' => $promo->start_date,
            'end_date' => $promo->end_date,
        ];
    }

}
