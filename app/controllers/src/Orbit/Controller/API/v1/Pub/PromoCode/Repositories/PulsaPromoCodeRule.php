<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use Discount;
use DiscountCode;
use ObjectDiscount;
use Pulsa;

/**
 * Class which determine if user is eligible to get promo code when purchasing
 * pulsa
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
class PulsaPromoCodeRule extends AbstractPromoCodeRule implements RuleInterface
{
    protected function isEligibleForObjectType($promo, $objectId, $objectType)
    {
        return $promo->type === 'pulsa';
    }

    /**---------------------------------------------
     * get allowed purchase per user between
     * promo code vs coupon. The less value will be returned
     *----------------------------------------------
     * @param Discount $promo
     * @param Pulsa $pulsa
     * @return int least value between two
     * ---------------------------------------------
     */
    protected function getMaxAllowedQtyPerUser($promo, $pulsa)
    {
        //pulsa does not have limit per user, only promo
        return (int) $promo->max_per_user;
    }

    /**---------------------------------------------
     * get allowed purchase per transaction between
     * promo code vs coupon. The less value will be returned
     *----------------------------------------------
     * @param Discount $promo
     * @param Pulsa $pulsa
     * @return int least value between two
     * ---------------------------------------------
     */
    protected function getMaxAllowedQtyPerTransaction($promo, $pulsa)
    {
        //pulsa does not have limit per transaction, only promo
        return $promo->max_per_transaction;
    }

    protected function getEligibleQty($promo, $user, $promoData)
    {
        $pulsa = Pulsa::find($promoData->object_id);
        return $this->isEligibleForQuantity(
            $promo,
            $pulsa,
            $pulsa->pulsa_item_id,
            'pulsa',
            $user,
            (int) $promoData->quantity,
            $promoData->is_final_check
        );
    }
}
