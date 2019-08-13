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
     * @param string $discountId, id discount
     * @param string $objectId, id linked object (i.e coupon)
     * @param string $objectType, linked object type (i.e coupon)
     * @return true if user can use promo code to object
     * ---------------------------------------------
     */
    protected function isEligibleForObjectType($discountId, $objectId, $objectType)
    {
        $obj = ObjectDiscount::where('discount_id', $discountId)
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
     * Check user eligiblity for promo code
     *----------------------------------------------
     * @param User $user, current logged in user
     * @param StdClass $promoData, promo code data
     *---------------------------------------------
     * Note : this method assumes data has been validated
     * So promo code must be exists and active.
     * ---------------------------------------------
     */
    abstract public function getEligibleStatus($user, $promoData);

}
