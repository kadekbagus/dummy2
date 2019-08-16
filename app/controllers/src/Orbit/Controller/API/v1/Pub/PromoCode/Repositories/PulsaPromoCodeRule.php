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

        return (object) [
            'promo_id' => $this->promo->discount_id,
            'promo_title' => $this->promo->discount_title,
            'promo_code' => $this->promo->discount_code,
            'eligible' => $eligible,

            //when eligible = false, rejectReason contains code why user
            //is not eligible for discount othweise this is empty string
            'rejectReason' => $rejectReason,

            'avail_quota_count' => $allowedQty,
            'original_quantity' => $promoData->quantity,
            'adjusted_quantity' => $allowedQty,

            'user_id' => $user->user_id,
            'object_type' => $promoData->object_type,
            'object_id' => $promoData->object_id,
            'value_in_percent' => $this->promo->value_in_percent,
            'start_date' => $this->promo->start_date,
            'end_date' => $this->promo->end_date,
        ];
    }

}
