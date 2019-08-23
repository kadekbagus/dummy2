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
class PulsaPromoCodeRule extends AbstractPromoCodeRule implements RuleInterface
{
    private $promo;

    protected function isEligibleForObjectType($discountId, $objectId, $objectType)
    {
        return $this->promo->type === 'pulsa';
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

        $allowedQty = $promoData->quantity;

        if (! $eligible) {
            $rejectReason = 'DISCOUNT_CODE_NOT_APPLICABLE_TO_PURCHASED_ITEM';
        }
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
