<?php

use DominoPOS\OrbitACL\ACL;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

/**
 * Controller to handle listing age range
 *
 * Read only methods do not check ACL.
 */
class AgeRangeAPIController extends ControllerAPI
{
    /**
     * Returns Age Ranges
     *
     * @return \Illuminate\Support\Facades\Response
     */
    public function getSearchAgeRanges()
    {
        $httpCode = 200;
        try {

            $this->checkAuth();

            $this->registerCustomValidation();
            $merchant_id = OrbitInput::get('merchant_id', null);
            $validator = Validator::make(['merchant_id' => $merchant_id],
                ['merchant_id' => 'required|orbit.empty.merchant.public'],
                ['orbit.empty.merchant.public' => Lang::get('validation.orbit.empty.merchant')]);
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $ageRanges = AgeRange::excludeDeleted()
                            ->where('merchant_id', '=', $merchant_id)
                            ->get();

            $count = count($ageRanges);

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = $count;
            $this->response->data->records = $ageRanges;
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

        $output = $this->render($httpCode);

        return $output;
    }

    private function registerCustomValidation()
    {
        Validator::extend('orbit.empty.merchant.public', function ($attribute, $value, $parameters) {
            $merchant = Mall::excludeDeleted()
                ->where('merchant_id', $value)
                ->first();

            if (empty($merchant)) {
                return false;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return true;
        });

        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user) {
            $merchant = Mall::excludeDeleted()
                /* ->allowedForUser($user) */
                ->where('merchant_id', $value)
                /* ->where('is_mall', 'yes') */
                ->first();

            if (empty($merchant)) {
                return false;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return true;
        });

    }

}
