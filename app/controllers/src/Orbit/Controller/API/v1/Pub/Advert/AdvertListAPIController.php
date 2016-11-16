<?php namespace Orbit\Controller\API\v1\Pub\Advert;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use \Exception;
use Config;
use Lang;
use Validator;
use stdClass;
use Orbit\Controller\API\v1\Pub\Advert\AdvertTopBanner;
use Orbit\Controller\API\v1\Pub\Advert\AdvertFooterBanner;

class AdvertListAPIController extends ControllerAPI
{
    public function getAdvertList()
    {
        $httpCode = 200;
        try {
            $advert = new stdClass();
            $advert_type = OrbitInput::get('advert_type', 'top');
            $location_type = OrbitInput::get('location_type', 'mall');
            $location_id = OrbitInput::get('mall_id', null);

            $advertHelper = AdvertHelper::create();
            $advertHelper->advertCustomValidator();

            $validator = Validator::make(
                array(
                    'mall_id'   => $location_id,
                    'location_type'   => $location_type,
                ),
                array(
                    'mall_id' => 'orbit.empty.mall',
                    'location_type'   => 'in:gtm,mall',
                ),
                array(
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (empty($location_id) || $location_id === '') {
                $location_type = 'gtm';
                $location_id = '0';
            }

            if ($location_type === 'gtm') {
                $location_id = '0';
            }

            if ($advert_type === 'top') {
               $topBanner = AdvertTopBanner::create();
               $advert = $topBanner->getAdvertTopBanner($location_type, $location_id);
            }
            if ($advert_type === 'footer') {
               $footerBanner = AdvertFooterBanner::create();
               $advert = $footerBanner->getAdvertFooterBanner($location_type, $location_id);
            }

            $this->response->data = $advert;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
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
            $this->response->data = null;

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