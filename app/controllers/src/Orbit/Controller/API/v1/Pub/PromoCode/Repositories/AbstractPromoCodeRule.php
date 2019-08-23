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
abstract class AbstractPromoCodeRule implements RuleInterface
{
    /**---------------------------------------------
     * Check if promo code is applicable to object
     *----------------------------------------------
     * For example if DISC10K is linked to Coupon A
     * and Coupon B, but user is trying to use promo
     * DISC10K when purchase other than Coupon A or B,
     * then it should return false
     *----------------------------------------------
     * @param Discount $promo, discount object
     * @param string $objectId, id linked object (i.e coupon)
     * @param string $objectType, linked object type (i.e coupon)
     * @return true if user can use promo code to object
     * ---------------------------------------------
     */
    protected function isEligibleForObjectType($promo, $objectId, $objectType)
    {
        $obj = ObjectDiscount::where('discount_id', $promo->discount_id)
            ->where('object_id', $objectId)
            ->where('object_type', $objectType)
            ->first();
        return ! (empty($obj));
    }

    protected function getPromoCodeDetail($promoData)
    {
        return Discount::where('discount_code', $promoData->promo_code)
            ->active()
            ->betweenExpiryDate()
            ->first();
    }

    /**---------------------------------------------
     * get allowed purchase per user between
     * promo code vs purchased item. The less value will be returned
     *----------------------------------------------
     * @param Discount $promo
     * @param Object $item purchased item
     * @return int least value between two
     * ---------------------------------------------
     */
    abstract protected function getMaxAllowedQtyPerUser($promo, $item);

    /**---------------------------------------------
     * get allowed purchase per transaction between
     * promo code vs coupon. The less value will be returned
     *----------------------------------------------
     * @param Discount $promo
     * @param Object $item purchased item
     * @return int least value between two
     * ---------------------------------------------
     */
    abstract protected function getMaxAllowedQtyPerTransaction($promo, $item);

    /**---------------------------------------------
     * Check if asked quantity by user is eligible
     * based on number use per user and number use per transaction
     * where least of them is used.
     *----------------------------------------------
     * @param Discount $promo, discount object
     * @param Object $purchasedItem, purchased object (coupon or pulsa)
     * @param User $user, user object
     * @param int $qty, asked quantity
     * @return Object eligible status
     * ---------------------------------------------
     */
    protected function isEligibleForQuantity(
        $promo,
        $item,
        $objectId,
        $objectType,
        $user,
        $qty,
        $isFinalCheck
    ) {
        $totalUsage = $user->discountCodes()
            ->where('discount_id', $promo->discount_id)
            ->issuedOrWaitingPayment()
            ->count();
        $maxPerUser = $this->getMaxAllowedQtyPerUser($promo, $item);
        $quotaUsagePerTransaction = $this->getMaxAllowedQtyPerTransaction($promo, $item);

        $totalReservedForCurrentItem = $user->discountCodes()
            ->where('discount_id', $promo->discount_id)
            ->where('object_id', $objectId)
            ->where('object_type', $objectType)
            ->reservedNotWaitingPayment()
            ->count();
        if (! $isFinalCheck) {
            $totalReservedForAllItems = $user->discountCodes()
                ->where('discount_id', $promo->discount_id)
                ->reservedNotWaitingPayment()
                ->count();
            $totalReserved = $totalReservedForAllItems - $totalReservedForCurrentItem;
            $quotaPerUser = $maxPerUser - $totalUsage - $totalReserved;
        } else {
            $totalReserved = $totalReservedForCurrentItem;
            $quotaPerUser = $totalReserved;
        }

        $allowedQty = min($quotaPerUser, $quotaUsagePerTransaction);
        if ($allowedQty < 0) {
            $allowedQty = 0;
        }

        $allowedQty = min($allowedQty, $qty);
        return (int) $allowedQty;
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
    protected function isEligibleForAvailQuantity($promo, $objectId, $user, $qty)
    {
        $totalAvail = DiscountCode::where('discount_id', $promo->discount_id)
            ->available()
            ->count();
        return (int) $totalAvail;
    }

    /**---------------------------------------------
     * build eligible status response
     *----------------------------------------------
     * @param Discount $promo, discount object
     * @param User $user, current logged in user
     * @param StdClass $promoData, promo code data
     *---------------------------------------------
     * Note : this method assumes data has been validated
     * So promo code must be exists and active.
     * ---------------------------------------------
     */
    protected function buildEligibleStatusResponse(
        $promo,
        $user,
        $promoData,
        $eligible,
        $rejectReason,
        $allowedQty,
        $adjustedQty
    ) {

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

    abstract protected function getEligibleQty($promo, $user, $promoData);

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
            $promo,
            $promoData->object_id,
            $promoData->object_type
        );

        $allowedQty = 0;
        $adjustedQty = 0;

        if ($eligible) {
            $qtyEligible = $this->getEligibleQty($promo, $user, $promoData);

            $availQtyEligible = $this->isEligibleForAvailQuantity(
                $promo,
                $promoData->object_id,
                $user,
                (int) $promoData->quantity
            );

            if (! $promoData->is_final_check) {
                $allowedQty = min($qtyEligible, $availQtyEligible);
            } else {
                $allowedQty = $qtyEligible;
            }
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

        return $this->buildEligibleStatusResponse(
            $promo,
            $user,
            $promoData,
            $eligible,
            $rejectReason,
            $allowedQty,
            $adjustedQty
        );
    }
}
