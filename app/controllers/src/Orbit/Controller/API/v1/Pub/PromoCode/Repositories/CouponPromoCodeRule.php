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

    protected function getEligibleQty($promo, $user, $promoData)
    {
        $coupon = Coupon::find($promoData->object_id);
        return $this->isEligibleForQuantity(
            $promo,
            $coupon,
            $coupon->promotion_id,
            'coupon',
            $user,
            (int) $promoData->quantity,
            $promoData->is_final_check
        );
    }

}
