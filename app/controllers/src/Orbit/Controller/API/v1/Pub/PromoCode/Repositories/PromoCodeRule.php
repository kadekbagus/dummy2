<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use \DB;
use PromoCode;
use Exception;

/**
 * Class which determine if user is eligible to get promo code
 * @author Zamroni <zamroni@dominopos.com>
 */
class PromoCodeRule implements RuleInterface
{
    private function findPromoCodeOrThrowIfNotFound($promoCode)
    {
        $promo = PromoCode::where('discount_code', $promoCode)->first();
        if ($promo) {
            return $promo;
        } else
        {
            throw new Exception('Promo Code not found');
        }
    }

    /**
     * Check user eligiblity for promo code
     * @param User $user, current logged in user
     * @param StdClass $promoData, promo code data
     */
    public function getEligibleStatus($user, $promoData)
    {
        $promo = $this->findPromoCodeOrThrowIfNotFound($promoData->promo_code);
        $userPromos = $user->promoCodes()->where('discount_id', $promo->discount_id);

        $availUsagePerUser = $promo->max_per_user - count($userPromos);
        if ($availUsagePerUser > 0) {
            $eligible = true;
        } else {
            $eligible = false;
            $availUsagePerUser = 0;
        }

        return (object) [
            'promo_id' => $promo->discount_id,
            'promo_code' => $promo->discount_code,
            'eligible' => $eligible,
            'avail_usage_per_transaction' => $availUsagePerTransaction,
            'avail_usage_per_user' => $availUsagePerUser,
            'user_id' => $user->user_id,
            'object_type' => $promoData->object_type,
            'object_id' => $promoData->object_id,
        ];
    }

}
