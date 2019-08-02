<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts;

/**
 * interface for aby class having capability to check
 * eligibility of user to get promo code
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
interface RuleInterface
{
    /**
     * Check user eligiblity for promo code
     * @param User $user, current logged in user
     * @param StdClass $promoData, promo code data
     */
    public function getEligibleStatus($user, $promoData);
}
