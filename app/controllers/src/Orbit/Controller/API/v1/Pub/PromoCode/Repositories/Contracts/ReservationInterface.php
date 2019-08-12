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
     * @param  int $quantity quantity of purchase item/promo code that will be reserved.
     */
    public function markAsReserved($user, $promoCode, $quantity = 1);

    /**
     * mark promo code as available
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsAvailable($user, $promoCode);

    /**
     * mark promo code as issued
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsIssued($user, $promoCode);

}
