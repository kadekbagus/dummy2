<?php namespace Orbit\Controller\API\v1\Pub\PromoCode;

use OrbitShop\API\v1\PubControllerAPI;
use \Exception;
use \QueryException;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \Lang;
use \Config;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RepositoryInterface;
use App;

class PromoCodeCheckAPIController extends PubControllerAPI
{
    private function renderResponse($code, $status, $msg, $data, $httpCode)
    {
        $this->response->data = $data;
        $this->response->code = $code;
        $this->response->status = $status;
        $this->response->message = $msg;
        return $this->render($httpCode);
    }

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
        try {
            $promoCode = App::make(RepositoryInterface::class)->authorizer($this);
            $eligibleStatus = $promoCode->checkAvailabilityAndReserveIfAvail();
            return $this->renderResponse(0, 'success', 'OK', $eligibleStatus, 200);

        } catch (ACLForbiddenException $e) {
            $this->renderResponse($e->getCode(), 'error', $e->getMessage(), null, 403);
        } catch (InvalidArgsException $e) {
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;
            return $this->renderResponse($e->getCode(), 'error', $e->getMessage(), $result, 403);
        } catch (QueryException $e) {

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $msg = $e->getMessage();
            } else {
                $msg = Lang::get('validation.orbit.queryerror');
            }
            return $this->renderResponse($e->getCode(), 'error', $msg, null, 500);

        } catch (Exception $e) {
            return $this->renderResponse($this->getNonZeroCode($e->getCode()), 'error', $e->getMessage(), null, 500);
        }
    }
}
