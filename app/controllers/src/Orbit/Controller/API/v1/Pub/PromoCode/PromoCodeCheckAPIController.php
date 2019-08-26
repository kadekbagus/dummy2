<?php namespace Orbit\Controller\API\v1\Pub\PromoCode;

use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RepositoryExecutorInterface;
use App;

class PromoCodeCheckAPIController extends PubControllerAPI
{
    /**
     * POST - check availability of promo code
     *
     * @author Zamroni <zamroni@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string promocode
     * @param string object_id
     * @param string object_type
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postCheckPromoCode()
    {
        $executor = App::make(RepositoryExecutorInterface::class);
        return $executor->execute($this, function($ctrl) {
            $promoCode = App::make(RepositoryInterface::class)->authorizer($ctrl);
            return $promoCode->checkAvailabilityAndReserveIfAvail();
        });
    }
}
