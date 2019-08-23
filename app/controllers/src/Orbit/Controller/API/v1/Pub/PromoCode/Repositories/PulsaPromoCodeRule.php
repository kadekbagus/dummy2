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
    private $promo;

    protected function isEligibleForObjectType($discountId, $objectId, $objectType)
    {
        return $this->promo->type === 'pulsa';
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
        $this->promo = $this->getPromoCodeDetail($promoData);

        $rejectReason = '';

        // TODO: Or just use promo->type === 'pulsa'
        $eligible = $this->isEligibleForObjectType(
            $this->promo->discount_id,
            $promoData->object_id,
            $promoData->object_type
        );

        $allowedQty = 0;
        $adjustedQty = 0;

        if ($eligible) {
            $pulsa = Pulsa::find($promoData->object_id);
            $qtyEligible = $this->isEligibleForQuantity(
                $promo,
                $pulsa,
                $pulsa->pulsa_item_id,
                'pulsa',
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
            $this->promo,
            $user,
            $promoData,
            $eligible,
            $rejectReason,
            $allowedQty,
            $adjustedQty
        );

    }

}
