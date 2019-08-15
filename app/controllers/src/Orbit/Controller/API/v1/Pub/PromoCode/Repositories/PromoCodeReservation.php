<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Discount;
use DiscountCode;
use Config;
use Carbon\Carbon;
use Queue;
use Exception;
use DB;

/**
 * Class that reserved promo code
 */
class PromoCodeReservation implements ReservationInterface
{
    private function getAvailableDiscountCodes($promoCode, $quantity = 1)
    {
        return DiscountCode::where('discount_code', $promoCode)
            ->available()
            ->take($quantity)
            ->get();
    }

    private function getReservedDiscountCodes($user, $promoData, $quantity = 9999)
    {
        return $user->discountCodes()
            ->where('discount_code', $promoData->promo_code)
            ->where('object_id', $promoData->object_id)
            ->where('object_type', $promoData->object_type)
            ->reserved()
            ->take($quantity)
            ->get();
    }

    /**
     * mark promo code as reserved for current user.
     * Only mark as reserved if not reserved yet.
     * Before marking as reserved, we need to check if the requested quantity changed or not.
     * If changed, we should only reserve or unreserve the remaining (diff quantity).
     *
     * @todo  start a job to check for reserved promo state in the future.
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsReserved($user, $promoData, $quantity = 1)
    {
        DB::transaction(function() use($user, $promoData, $quantity) {
            $reservedPromoCodes = $this->getReservedDiscountCodes($user, $promoData);
            $reservedPromoCodesCount = $reservedPromoCodes->count();

            // If new quantity is greater than reserved or reserved is 0 (means new "use" request), then try reserving new ones.
            // If new quantity is lower than reserved, then unreserved the diff.
            if ($reservedPromoCodesCount === 0 || $reservedPromoCodesCount < $quantity) {
                $this->reservePromoCodes($user, $promoData, $quantity - $reservedPromoCodesCount, $reservedPromoCodes);
            }
            else if ($reservedPromoCodesCount > $quantity) {
                $this->unreservePromoCodes($user, $promoData, $reservedPromoCodesCount - $quantity);
            }
        });
    }

    /**
     * mark promo code as available
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsAvailable($user, $promoData)
    {
        DB::transaction(function() use ($user, $promoData) {
            $discounts = $this->getReservedDiscountCodes($user, $promoData);
            foreach($discounts as $discount) {
                $discount->status = 'available';
                $discount->payment_transaction_id = null;
                $discount->user_id = null;
                $discount->object_id = '';
                $discount->object_type = '';
                $discount->save();
            }
        });
    }

    /**
     * mark promo code as issued for current user
     *
     * @param User $user, current logged in user
     * @param string $promoCode, promo code
     */
    public function markAsIssued($user, $promoData)
    {
        DB::transaction(function() use ($user, $promoData) {
            $discounts = $this->getReservedDiscountCodes($user, $promoData);
            foreach($discounts as $discount) {
                $discount->status = 'issued';
                $discount->save();
            }
        });
    }

    /**
     * Reserve promo codes.
     *
     * @param  [type] $user      [description]
     * @param  [type] $promoCode [description]
     * @param  [type] $quantity  [description]
     * @return [type]            [description]
     */
    private function reservePromoCodes($user, $promoData, $quantity, $reservedPromoCodes)
    {
        // Only reserve if available quantity = requested quantity.
        // Otherwise, throw exception.
        //
        $discounts = $this->getAvailableDiscountCodes($promoData->promo_code, $quantity);
        if ($discounts->count() === $quantity) {
            $reservedPromoCodesArray = [];
            foreach($reservedPromoCodes as $reservedPromoCode) {
                $reservedPromoCodesArray[] = $reservedPromoCode->discount_code_id;
            }

            foreach($discounts as $discount) {
                $discount->user_id = $user->user_id;
                $discount->object_id = $promoData->object_id;
                $discount->object_type = $promoData->object_type;
                $discount->status = 'reserved';
                $discount->save();
                $reservedPromoCodesArray[] = $discount->discount_code_id;
            }

            // Register new queue to check reserved promo codes status later.
            $this->cleanUpReservedPromoCodesLater($user->user_id, $reservedPromoCodesArray);
        }
        else {
            // If we get here, that means requested amount of promo code not available anymore.
            throw new Exception("REQUESTED_DISCOUNT_CODE_QUANTITY_NOT_AVAILABLE", 1);
        }
    }

    /**
     * Unreserve promo codes.
     *
     * @param  [type] $user      [description]
     * @param  [type] $promoCode [description]
     * @param  [type] $quantity  [description]
     * @return [type]            [description]
     */
    private function unreservePromoCodes($user, $promoData, $quantity)
    {
        $reservedPromoCodes = $this->getReservedDiscountCodes($user, $promoData, $quantity);
        foreach($reservedPromoCodes as $reservedPromoCode) {
            $reservedPromoCode->status = 'available';
            $reservedPromoCode->payment_transaction_id = null;
            $reservedPromoCode->user_id = null;
            $reservedPromoCode->object_id = null;
            $reservedPromoCode->object_type = null;
            $reservedPromoCode->save();
        }
    }

    /**
     * Push a job to queue to clean up reserved promo codes
     * later if the status not changed (still reserved).
     *
     * @param  [type] $userId             [description]
     * @param  array  $reservedPromoCodes [description]
     * @return [type]                     [description]
     */
    private function cleanUpReservedPromoCodesLater($userId, $reservedPromoCodes = [])
    {
        $limitTimeCfg = Config::get('orbit.coupon_reserved_limit_time', 10);
        $date = Carbon::now()->addMinutes($limitTimeCfg);
        Queue::later(
            $date,
            'Orbit\\Queue\\PromoCode\\CheckReservedPromoCode',
            ['user_id' => $userId, 'discount_codes' => $reservedPromoCodes]
        );
    }
}
