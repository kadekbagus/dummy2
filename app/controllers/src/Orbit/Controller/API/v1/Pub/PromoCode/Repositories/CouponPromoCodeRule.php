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
     * get allowed purchase per user between
     * promo code vs coupon. The less value will be returned
     *----------------------------------------------
     * @param Discount $promo
     * @param Coupon $coupon
     * @return int least value between two
     * ---------------------------------------------
     */
    protected function getMaxAllowedQtyPerUser($promo, $coupon)
    {
        if (empty($coupon->max_quantity_per_user)) {
            //zero value in coupon is used to marked unlimited use
            //so for this special use case, we will automatically
            //use data from promo
            return $promo->max_per_user;
        }
        return min((int) $promo->max_per_user, (int) $coupon->max_quantity_per_user);
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
    protected function getMaxAllowedQtyPerTransaction($promo, $coupon)
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
                (int) $promoData->quantity,
                $promoData->is_final_check
            );

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
