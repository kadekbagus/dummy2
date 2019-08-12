<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use Discount;
use DiscountCode;
use ObjectDiscount;

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
     * For example if DISC10K is set to maximum number of use
     * per user 10 and per transaction is 2
     * - user never use DISC10K
     * -- For qty<=2, then eligible = true and avail_usage_count=2
     * -- For qty>2, then eligible = false but avail_usage_count=2
     * - user used DISC10K once
     * -- For qty=1, then eligible = true and avail_usage_count=1
     * -- For qty>=2, then eligible = false and avail_usage_count=1
     * - user used DISC10K twice
     * -- For qty=1, then eligible = false and avail_usage_count=1
     * -- For qty>=2, then eligible = false and avail_usage_count=1
     *
     * For example if DISC20K is set to maximum number of use
     * per user 3 and per transaction is 10
     * - user never use DISC20K
     * -- For qty<=3, then eligible = true and avail_usage_count=3
     * -- For qty>3, then eligible = false but avail_usage_count=3
     * - user never use DISC20K once
     * -- For qty<=2, then eligible = true and avail_usage_count=2
     * -- For qty>2, then eligible = false but avail_usage_count=2
     *----------------------------------------------
     * @param string $promo, discount object
     * @param string $user, user object
     * @param string $qty, asked quantity
     * @return true if user eligible for asked quantity
     * ---------------------------------------------
     */
    private function isEligibleForQuantity($promo, $user, $qty)
    {
        $totalUsage = $user->discountCodes()
            ->where('discount_id', $promo->discount_id)
            ->issued()
            ->count();

        $quotaUsagePerUser = $promo->max_per_user - $totalUsage;
        if ($quotaUsagePerUser <= 0) {
            $quotaUsagePerUser = 0;
        } else {
            $quotaUsagePerUser = $quotaUsagePerUser > $qty ? $qty : $quotaUsagePerUser;
        }

        $quotaUsagePerTransaction = $promo->max_per_transaction - $totalUsage;
        if ($quotaUsagePerTransaction <= 0) {
            $availUsagePerTransaction = 0;
        } else {
            $quotaUsagePerTransaction = $quotaUsagePerTransaction > $qty ? $qty : $quotaUsagePerTransaction;
        }

        $allowedQty = $quotaUsagePerUser < $quotaUsagePerTransaction ? $quotaUsagePerUser : $quotaUsagePerTransaction;
        return (object) [
            'eligible' => ($allowedQty > 0),
            'allowedQty' => $allowedQty
        ];
    }

    /**---------------------------------------------
     * Check if asked quantity by user is less or equal than
     * total available discount code
     *----------------------------------------------
     * @param object $promo, promo instance
     * @param User $user, current user
     * @return true if user can use promo code to object
     * ---------------------------------------------
     */
    private function isEligibleForAvailQuantity($promo, $user, $qty)
    {
        $totalAvail = DiscountCode::where('discount_id', $promo->discount_id)
            ->available()
            ->count();

        if ($totalAvail < $qty) {
            //test if current user already reserved some promo codes
            $totalReserved = $user->discountCodes()
                ->where('discount_id', $promo->discount_id)
                ->reserved()
                ->count();
            $totalAvail = $totalAvail + $totalReserved;
        }

        return (object) [
            'eligible' => ($totalAvail >= $qty),
            'allowedQty' => $totalAvail >= $qty ? $qty : $totalAvail,
        ];
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

        if ($eligible) {

            $availQtyEligible = $this->isEligibleForAvailQuantity(
                $promo,
                $user,
                $promoData->quantity
            );

            $eligible = $eligible && $availQtyEligible->eligible;
            $allowedQty = $availQtyEligible->allowedQty;

            if ($eligible) {
                $qtyEligible = $this->isEligibleForQuantity(
                    $promo,
                    $user,
                    $allowedQty
                );

                $eligible = $eligible && $qtyEligible->eligible;
                if ($eligible) {
                    $allowedQty = $qtyEligible->allowedQty;
                    $eligible = ($allowedQty >= $promoData->quantity);
                    if (! $eligible) {
                        $rejectReason = 'REQUESTED_QTY_IS_GREATER_THAN_ALLOWED_PER_USER_OR_PER_TRX';
                    }
                } else {
                    $rejectReason = 'REMAINING_DISCOUNT_CODE_USAGE_FOR_USER_GREATER_THAN_ALLOWED';
                }
            } else {
                $rejectReason = 'REMAINING_AVAIL_DISCOUNT_CODE_LESS_THAN_REQUESTED_QTY';
            }
        } else {
            $rejectReason = 'DISCOUNT_CODE_NOT_APPLICABLE_TO_PURCHASED_ITEM';
        }

        return (object) [
            'promo_id' => $promo->discount_id,
            'promo_title' => $promo->discount_title,
            'promo_code' => $promo->discount_code,
            'eligible' => $eligible,

            //when eligible = false, rejectReason contains code why user
            //is not eligible for discount othweise this is empty string
            'rejectReason' => $rejectReason,

            'avail_quota_count' => $allowedQty,
            'original_quantity' => $promoData->quantity,
            'user_id' => $user->user_id,
            'object_type' => $promoData->object_type,
            'object_id' => $promoData->object_id,
            'value_in_percent' => $promo->value_in_percent,
            'start_date' => $promo->start_date,
            'end_date' => $promo->end_date,
        ];
    }

}
