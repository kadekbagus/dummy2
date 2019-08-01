<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts;


/**
 * interface for any class having capability to reserved/unreserved
 * promo code
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
interface ReservationInterface
{
    /**
     * mark promo code as reserved for current user
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsReserved($user, $promoCode);

    /**
     * mark promo code as available
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsAvailable($user, $promoCode);

}
