<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use Discount;
use DiscountCode;
use ObjectDiscount;

/**
 * Class which determine if user is eligible to get promo code when purchasing
 * pulsa
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
class PulsaPromoCodeRule implements RuleInterface
{
    /**---------------------------------------------
     * Check if promo code is applicable to purchased item
     *----------------------------------------------
     * For example if DISC10K is linked to telcom operator A
     * and telcom operator B, but user is trying to use promo
     * DISC10K when purchase other than A or B,
     * then it should return false
     *----------------------------------------------
     * @param string $discountId, id discount
     * @param string $objectId, id linked object (i.e pulsa)
     * @param string $objectType, linked object type (i.e pulsa)
     * @return true if user can use promo code to item to be purchase
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

        $rejectReason = '';

        $eligible = $this->isEligibleForObjectType(
            $promo->discount_id,
            $promoData->object_id,
            $promoData->object_type
        );

        $allowedQty = $promoData->quantity;

        if (! $eligible) {
            $rejectReason = 'DISCOUNT_CODE_NOT_APPLICABLE_TO_PURCHASED_ITEM';
        }

        return (object) [
            'promo_id' => $promo->discount_id,
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
