<?php namespace Orbit\Controller\API\v1\Pub\PromoCode;

use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\DetailRepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RepositoryExecutorInterface;
use App;

class PromoCodeDetailAPIController extends PubControllerAPI
{
    /**
     * GET - get promo code detail
     *
     * @author Zamroni <zamroni@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string promocode
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getPromoCode()
    {
        $executor = App::make(RepositoryExecutorInterface::class);
        $executor->execute($this, function($ctrl) {
            $promoCode = App::make(DetailRepositoryInterface::class);
            return $promoCode->authorizer($ctrl)->getDetail();
        });
    }
}
