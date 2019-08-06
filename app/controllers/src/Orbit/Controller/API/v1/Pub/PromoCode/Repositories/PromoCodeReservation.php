<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Discount;
use DiscountCode;

/**
 * Class that reserved promo code
 */
class PromoCodeReservation implements ReservationInterface
{
    private function getAvailableDiscountCode($promoCode)
    {
        return DiscountCode::where('discount_code', $promoCode)
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

    /**
     * mark promo code as reserved for current user.
     * Only mark as reserved if not reserved yet.
     *
     * @todo  start a job to check for reserved promo state in the future.
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsReserved($user, $promoCode)
    {
        if ($this->getReservedDiscountCode($user, $promoCode) === null) {
            $discount = $this->getAvailableDiscountCode($promoCode);
            $discount->user_id = $user->user_id;
            $discount->status = 'reserved';
            $discount->save();
        }
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
        $discount->status = 'available';
        $discount->payment_transaction_id = null;
        $discount->user_id = null;
        $discount->save();
    }

    /**
     * mark promo code as issued for current user
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsIssued($user, $promoCode)
    {
        $discount = $this->getReservedDiscountCode($user, $promoCode);
        $discount->status = 'issued';
        $discount->save();
    }


}
