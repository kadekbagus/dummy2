<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use \DB;
use PromoCode;

/**
 * Class that reserved promo code
 */
class PromoCodeReservation
{
    /**
     * mark promo code as reserved for current user
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsReserved($user, $promoCode)
    {
        PromoCode::find();
    }

    /**
     * mark promo code as available
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsAvailable($user, $promoCode)
    {
        PromoCode::find();
    }

}
