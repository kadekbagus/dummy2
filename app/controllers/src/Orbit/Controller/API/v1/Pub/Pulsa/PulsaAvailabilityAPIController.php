<?php namespace Orbit\Controller\API\v1\Pub\Pulsa;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \Orbit\Helper\Exception\OrbitCustomException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use DB;
use Validator;
use Activity;
use Mall;
use Lang;
use \Exception;
use \Queue;
use Carbon\Carbon as Carbon;
use Log;
use Pulsa;
use PaymentTransaction;

class PulsaAvailabilityAPIController extends PubControllerAPI
{
    /**
     * POST - For checking pulsa availability.
     *
     * @author Budi <budi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string pulsa_id
     * @param string with_reserved
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postAvailability()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $response = NULL;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $pulsa_id = OrbitInput::post('pulsa_id');
            $quantity = OrbitInput::post('quantity', 1);
            $phone_number = OrbitInput::post('phone_number');
            $limitTimeCfg = Config::get('orbit.coupon_reserved_limit_time', 10);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'pulsa_id' => $pulsa_id,
                    'quantity' => $quantity,
                    'phone_number' => $phone_number,
                ),
                array(
                    'pulsa_id' => 'required|orbit.exists.pulsa',
                    'quantity' => 'required|orbit.allowed.quantity',
                    'phone_number' => 'required|min:10|orbit.limit.pending|orbit.limit.purchase',
                ),
                array(
                    'orbit.exists.pulsa' => 'Requested Pulsa does not exist.',
                    'orbit.allowed.quantity' => 'REQUESTED_QUANTITY_NOT_AVAILABLE',
                    'orbit.limit.purchase' => 'PURCHASE_TIME_LIMITED',
                    'orbit.limit.pending' => 'FINISH_PENDING_FIRST',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $pulsa = new \stdClass;
            $pulsa->available = true;
            $pulsa->limit_time = Carbon::now()->addMinutes($limitTimeCfg);

            $this->response->data = $pulsa;

        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            $this->rollBack();
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            $this->rollBack();
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
            $this->rollBack();
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
            $this->rollBack();
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * Register custom request validations.
     *
     * @return [type] [description]
     */
    private function registerCustomValidation()
    {
        $user = $this->getUser();

        // Check if pulsa is exists.
        Validator::extend('orbit.exists.pulsa', function ($attribute, $value, $parameters) {
            $pulsa = Pulsa::where('pulsa_item_id', $value)
                ->where('displayed', 'yes')
                ->where('status', 'active')
                ->first();

            if (empty($pulsa)) {
                return false;
            }

            // Why it doesn't
            // \App::instance('orbit.exists.pulsa', $pulsa);

            return true;
        });

        /**
         * Check if pulsa still available.
         */
        Validator::extend('orbit.allowed.quantity', function ($attribute, $requestedQuantity, $parameters) {

            $pulsaId = OrbitInput::post('pulsa_id');
            $pulsa = Pulsa::where('pulsa_item_id', $pulsaId)->first();

            if (! empty($pulsa) && (int) $pulsa->quantity === 0) {
                return true;
            }

            // Globally issued coupon count regardless of the Customer.
            $issuedPulsa = PaymentTransaction::select(
                    'payment_transactions.payment_transaction_id',
                    'payment_transaction_details.object_id',
                    'payment_transactions.user_id'
                )
                ->join('payment_transaction_details', 'payment_transactions.payment_transaction_id', '=', 'payment_transaction_details.payment_transaction_id')
                ->join('pulsa', 'payment_transaction_details.object_id', '=', 'pulsa.pulsa_item_id')
                ->where('payment_transaction_details.object_type', 'pulsa')
                ->where('payment_transaction_details.object_id', $pulsaId)
                ->whereIn('payment_transactions.status', [
                    PaymentTransaction::STATUS_SUCCESS,
                    PaymentTransaction::STATUS_SUCCESS_NO_COUPON,
                    PaymentTransaction::STATUS_SUCCESS_NO_PULSA,
                ])
                ->count();

            return (int) $pulsa->quantity > $issuedPulsa;
        });

        Validator::extend('orbit.limit.pending', function($attribute, $phoneNumber, $parameters) {

            $pulsaId = OrbitInput::post('pulsa_id');
            $pulsa = Pulsa::where('pulsa_item_id', $pulsaId)->first();

            if (empty($pulsa)) {
                return false;
            }

            $pendingPurchase = PaymentTransaction::select('payment_transactions.payment_transaction_id')
                                ->join('payment_transaction_details', 'payment_transactions.payment_transaction_id', '=', 'payment_transaction_details.payment_transaction_id')
                                ->join('pulsa', 'payment_transaction_details.object_id', '=', 'pulsa.pulsa_item_id')
                                ->where('payment_transaction_details.object_type', 'pulsa')
                                ->where('pulsa.pulsa_code', $pulsa->pulsa_code)
                                ->where('payment_transactions.extra_data', $phoneNumber)
                                ->whereIn('payment_transactions.status', [
                                    PaymentTransaction::STATUS_PENDING,
                                ])
                                ->count();

            return $pendingPurchase === 0;
        });

        Validator::extend('orbit.limit.purchase', function($attribute, $phoneNumber, $parameters) {

            $pulsaId = OrbitInput::post('pulsa_id');
            $pulsa = Pulsa::where('pulsa_item_id', $pulsaId)->first();
            if (empty($pulsa)) {
                return false;
            }

            $interval = Config::get('orbit.partners_api.mcash.interval_before_next_purchase', 60);
            $lastTime = Carbon::now('UTC')->subMinutes($interval)->format('Y-m-d H:i:s');

            $samePurchase = PaymentTransaction::select('payment_transactions.payment_transaction_id')
                            ->join('payment_transaction_details', 'payment_transactions.payment_transaction_id', '=', 'payment_transaction_details.payment_transaction_id')
                            ->join('pulsa', 'payment_transaction_details.object_id', '=', 'pulsa.pulsa_item_id')
                            ->where('payment_transaction_details.object_type', 'pulsa')
                            ->where('pulsa.pulsa_code', $pulsa->pulsa_code)
                            ->where('payment_transactions.extra_data', $phoneNumber)
                            ->where('payment_transactions.updated_at', '>', $lastTime)
                            ->whereIn('payment_transactions.status', [
                                PaymentTransaction::STATUS_SUCCESS,
                                PaymentTransaction::STATUS_SUCCESS_NO_COUPON,
                                PaymentTransaction::STATUS_SUCCESS_NO_PULSA,
                            ])
                            ->count();

            return $samePurchase === 0;
        });
    }
}
