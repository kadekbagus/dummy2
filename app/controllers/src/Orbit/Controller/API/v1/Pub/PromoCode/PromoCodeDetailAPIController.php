<?php namespace Orbit\Controller\API\v1\Pub\PromoCode;

use OrbitShop\API\v1\PubControllerAPI;
use \Exception;
use \QueryException;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\DetailRepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ResponseRendererInterface;
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
        $resp = App::make(ResponseRendererInterface::class);

        try {
            $promoCode = App::make(DetailRepositoryInterface::class)->authorizer($this);
            return $resp->renderSuccess($this, $promoCode->getDetail());

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
