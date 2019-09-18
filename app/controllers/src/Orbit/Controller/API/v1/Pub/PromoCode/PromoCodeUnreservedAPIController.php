<?php namespace Orbit\Controller\API\v1\Pub\PromoCode;

use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationRepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RepositoryExecutorInterface;
use App;

class PromoCodeUnreservedAPIController extends PubControllerAPI
{

    /**
     * POST - make promo code available after being reserved
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
    public function postUnreservedPromoCode()
    {
        $executor = App::make(RepositoryExecutorInterface::class);
        return $executor->execute($this, function($ctrl) {
            $reservationSvc = App::make(ReservationRepositoryInterface::class);
            return $reservationSvc->authorizer($this)->unreserved();
        });
    }
}
