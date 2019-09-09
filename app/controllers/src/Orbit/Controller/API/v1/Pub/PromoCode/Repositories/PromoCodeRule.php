<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;

/**
 * Class which determine if user is eligible to get promo code
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
class PromoCodeRule implements RuleInterface
{
    private $couponRule;
    private $pulsaRule;

    public function __construct($couponRule, $pulsaRule)
    {
        $this->couponRule = $couponRule;
        $this->pulsaRule = $pulsaRule;
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
        if ($promoData->object_type === 'coupon')
        {
            return $this->couponRule->getEligibleStatus($user, $promoData);
        } else {
            //assume pulsa
            return $this->pulsaRule->getEligibleStatus($user, $promoData);
        }
    }

}
