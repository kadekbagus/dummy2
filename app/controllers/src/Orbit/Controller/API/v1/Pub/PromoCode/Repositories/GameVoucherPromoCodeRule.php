<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use Discount;
use DiscountCode;
use ObjectDiscount;
use DigitalProduct;

/**
 * Class which determine if user is eligible to get promo code when purchasing
 * game voucher
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
class GameVoucherPromoCodeRule extends AbstractPromoCodeRule implements RuleInterface
{
    protected function isEligibleForObjectType($promo, $objectId, $objectType)
    {
        return $promo->type === 'game_voucher';
    }

    /**---------------------------------------------
     * get allowed purchase per user between
     * promo code vs coupon. The less value will be returned
     *----------------------------------------------
     * @param Discount $promo
     * @param DigitalProduct $gameVoucher
     * @return int least value between two
     * ---------------------------------------------
     */
    protected function getMaxAllowedQtyPerUser($promo, $gameVoucher)
    {
        return (int) $promo->max_per_user;
    }

    /**---------------------------------------------
     * get allowed purchase per transaction between
     * promo code vs coupon. The less value will be returned
     *----------------------------------------------
     * @param Discount $promo
     * @param DigitalProduct $gameVoucher
     * @return int least value between two
     * ---------------------------------------------
     */
    protected function getMaxAllowedQtyPerTransaction($promo, $gameVoucher)
    {
        return $promo->max_per_transaction;
    }

    protected function getEligibleQty($promo, $user, $promoData)
    {
        $gameVoucher = DigitalProduct::find($promoData->object_id);
        return $this->isEligibleForQuantity(
            $promo,
            $gameVoucher,
            $gameVoucher->digital_product_id,
            'game_voucher',
            $user,
            (int) $promoData->quantity,
            $promoData->is_final_check
        );
    }
}
