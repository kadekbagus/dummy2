<?php namespace Orbit\Controller\API\v1\Pub\PromoCode;

use OrbitShop\API\v1\PubControllerAPI;
use \Exception;
use \QueryException;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationRepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ResponseRendererInterface;
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
        $resp = App::make(ResponseRendererInterface::class);
        try {
            $promoCode = App::make(ReservationRepositoryInterface::class)->authorizer($this);
            $eligibleStatus = $promoCode->unreserved();
            return $resp->renderSuccess($this, $eligibleStatus);

        } catch (ACLForbiddenException $e) {
            return $resp->renderForbidden($this, $e);
        } catch (InvalidArgsException $e) {
            return $resp->renderInvalidArgs($this, $e);
        } catch (QueryException $e) {
            return $resp->renderQueryExcept($this, $e);
        } catch (Exception $e) {
            return $resp->renderExcept($this, $e);
        }
    }
}
