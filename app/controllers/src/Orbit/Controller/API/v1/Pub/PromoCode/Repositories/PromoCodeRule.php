<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;

/**
 * Class which determine if user is eligible to get promo code
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
class PromoCodeRule implements RuleInterface
{
    private $rules;

    public function __construct($rules)
    {
        $this->rules = $rules;
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
        $rule = $this->rules[$promoData->object_type];
        return $rule->getEligibleStatus($user, $promoData);
    }

}
