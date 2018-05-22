<?php namespace Orbit\Controller\API\v1\Pub;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Validator;
use Config;
use Exception;

/**
 * Sepulsa Redeem Callback Controller
 */
class SepulsaRedeemCallbackController extends ControllerAPI
{
    public function validate()
    {
        $httpCode = 200;
        try {
            $secretToken = trim(OrbitInput::post('secret_token', null));
            $voucherCode = trim(OrbitInput::post('voucher_code', null));
            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'secret_token' => $secretToken,
                    'voucher_code' => $voucherCode
                ),
                array(
                    'secret_token' => 'required|sepulsa_secret_token',
                    'voucher_code' => 'required',
                ),
                array(
                    'sepulsa_secret_token' => 'Invalid secret token',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            // @todo: remove issued coupon from user's wallet based on sepulsa voucher_code.

        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        return $this->render(200);
    }

    /**
     * @return boolean
     * @throws Exception
     */
    private function registerCustomValidation()
    {
        Validator::extend('sepulsa_secret_token', function ($attribute, $value, $parameters) {
            if ($value !== Config::get('orbit.partners_api.sepulsa.callback_secret_token')) {
                return FALSE;
            }

            return TRUE;
        });
    }
}
