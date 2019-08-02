<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Discount;
use DiscountCode;

/**
 * Class that reserved promo code
 */
class PromoCodeReservation implements ReservationInterface
{
    private function getAvailableDiscountCode($user, $promoCode)
    {
        return $user->discountCodes()
            ->where('discount_code', $promoCode)
            ->available()
            ->first();
    }

    private function getReservedDiscountCode($user, $promoCode)
    {
        return $user->discountCodes()
            ->where('discount_code', $promoCode)
            ->reserved()
            ->first();
    }

    private function createDiscountCode($user, $promoCode)
    {
        $discount = Discount::where('discount_code', $promoCode)->active()->first();
        $discountCode = new DiscountCode();
        $discountCode->discount_id = $discount->discount_id;
        $discountCode->discount_code = $discount->discount_code;
        $discountCode->user_id = $user->user_id;
        return $discountCode;
    }

    /**
     * mark promo code as reserved for current user
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsReserved($user, $promoCode)
    {
        $discount = $this->getAvailableDiscountCode($user, $promoCode);
        if (empty($discount)) {
            $discount = createDiscountCode($user, $promoCode);
        }
        $discountCode->status = 'reserved';
        $discountCode->save();
    }

    /**
     * mark promo code as available
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsAvailable($user, $promoCode)
    {
        $discount = $this->getReservedDiscountCode($user, $promoCode);
        if (empty($discount)) {
            $discount = createDiscountCode($user, $promoCode);
        }
        $discountCode->status = 'available';
        $discountCode->save();
    }

}
