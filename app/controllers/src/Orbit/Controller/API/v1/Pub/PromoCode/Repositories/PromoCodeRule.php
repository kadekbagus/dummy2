<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use Discount;
use DiscountCode;
use ObjectDiscount;

/**
 * Class which determine if user is eligible to get promo code
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
class PromoCodeRule implements RuleInterface
{
    /**---------------------------------------------
     * Check if promo code is applicable to object
     *----------------------------------------------
     * For example if DISC10K is linked to Coupon A
     * and Coupon B, but user is trying to use promo
     * DISC10K when purchase other than Coupon A or B,
     * then it should return false
     *----------------------------------------------
     * @param string $discountId, id discount
     * @param string $objectId, id linked object (i.e coupon)
     * @param string $objectType, linked object type (i.e coupon)
     * @return true if user can use promo code to object
     * ---------------------------------------------
     */
    private function isEligibleForObjectType($discountId, $objectId, $objectType)
    {
        $obj = ObjectDiscount::where('discount_id', $discountId)
            ->where('object_id', $objectId)
            ->where('object_type', $objectType)
            ->first();
        return ! (empty($obj));
    }

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
     * @param string $discountId, id discount
     * @param string $objectId, id linked object (i.e coupon)
     * @param string $objectType, linked object type (i.e coupon)
     * @return true if user can use promo code to object
     * ---------------------------------------------
     */
    private function isEligibleForQuantity($promo, $user, $qty)
    {
        $userPromos = $user->discountCodes()
            ->where('discount_id', $promo->discount_id)
            ->get();

        $totalUsage = count($userPromos);
        $quotaUsagePerUser = $promo->max_per_user - $totalUsage;
        if ($quotaUsagePerUser <= 0) {
            $quotaUsagePerUser = 0;
        } else {
            $quotaUsagePerUser = $quotaUsagePerUser > $qty ? $quotaUsagePerUser : $qty;
        }

        $quotaUsagePerTransaction = $promo->max_per_transaction - $totalUsage;
        if ($quotaUsagePerTransaction <= 0) {
            $availUsagePerTransaction = 0;
        } else {
            $quotaUsagePerTransaction = $quotaUsagePerTransaction > $qty ? $quotaUsagePerTransaction : $qty;
        }

        $availQuotaUsage = $quotaUsagePerUser < $quotaUsagePerTransaction ? $quotaUsagePerUser : $quotaUsagePerTransaction;
        return (object) [
            'eligible' => ($availQuotaUsage > 0),
            'availQuotaUsage' => $availQuotaUsage
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
        $promo = Discount::where('discount_code', $promoData->promo_code)
            ->active()
            ->betweenExpiryDate()
            ->first();

        $eligible = $this->isEligibleForObjectType(
            $promo->discount_id,
            $promoData->object_id,
            $promoData->object_type
        );

        $qtyEligible = $this->isEligibleForQuantity(
            $promo,
            $user,
            $promoData->quantity
        );

        $eligible = $eligible && $qtyEligible->eligible;

        return (object) [
            'promo_id' => $promo->discount_id,
            'promo_code' => $promo->discount_code,
            'eligible' => $eligible,
            'avail_quota_count' => $qtyEligible->availQuotaUsage,
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
