<?php namespace Orbit\Controller\API\v1\Pub\PromoCode;

use OrbitShop\API\v1\PubControllerAPI;
use \Exception;
use \QueryException;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \Lang;
use \Config;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RepositoryInterface;

class PromoCodeCheckAPIController extends PubControllerAPI
{
    private $promoCode;

    public function __construct(RepositoryInterface $promoCode)
    {
        parent::__construct();
        $this->promoCode = $promoCode;
        $this->promoCode->authorizer($this);
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
        $httpCode = 200;
        try {

            $eligibleStatus = $this->promoCode->checkAvailabilityAndReserveIfAvail();
            $this->response->data = $eligibleStatus;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'OK';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }
}
