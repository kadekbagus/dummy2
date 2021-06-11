<?php
namespace Orbit\Controller\API\v1\RGP;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Validator;
use Config;
use stdclass;
use Carbon\Carbon;
use DB;
use Exception;
use RgpUser;
use PaymentTransaction;
use Orbit\Helper\AutoIssueCoupon\AutoIssueCoupon;

class TransactionDetailAPIController extends ControllerAPI
{
    /**
     * Issue an automatic issuancance coupon manually incase of some failure
     */
    public function post()
    {
        try {
            $httpCode = 200;
            // authenticate
            $sessionKey = Config::get('orbit.session.app_list.rgp_portal', 'X-OMS-RGP');
            $sessionString = OrbitInput::get($sessionKey);

            if (empty($sessionKey) || empty($sessionString)) {
                throw new Exception("Error Processing Request", 1);
            }

            $session = DB::table('sessions')
                ->where('session_id', $sessionString)
                ->first();

            if (! is_object($session)) {
                throw new Exception("You need to login to continue.", 1);
            }

            $sessionData = unserialize($session->session_data);

            if (! isset($sessionData->value['email'])) {
                throw new Exception("You need to login to continue.", 1);
            }

            $userEmail = $sessionData->value['email'];
            $user = RgpUser::active()->where('email', $userEmail)->first();

            if (! is_object($user)) {
                throw new Exception("You need to login to continue.", 1);
            }

            // get inputs
            $transactionId = OrbitInput::post('transaction_id');

            $validator = Validator::make(
                array(
                    'transaction_id' => $transactionId,
                ),
                array(
                    'transaction_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            // check if transaction is valid: status success and success response from provider
            $payment = PaymentTransaction::with('details')
                ->where('transaction_id', $transactionId)
                ->first();

            if (! is_object($payment)) {
                throw new Exception("Transaction is not found, wrong ID or the status is not success", 1);
            }

            $this->response->data = $payment;

        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }
}
