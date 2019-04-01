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
            $limitTimeCfg = Config::get('orbit.coupon_reserved_limit_time', 10);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'pulsa_id' => $pulsa_id,
                    // 'with_reserved' => $with_reserved,
                    'quantity' => $quantity,
                ),
                array(
                    'pulsa_id' => 'required|orbit.exists.pulsa',
                    // 'with_reserved' => 'required',
                    'quantity' => 'required',
                ),
                array(
                    'orbit.allowed.quantity' => 'REQUESTED_QUANTITY_NOT_AVAILABLE',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // $pulsa = Pulsa::findOrFail($pulsa_id);
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
            $prefix = DB::getTablePrefix();
            $pulsa = Pulsa::where('pulsa_item_id', $value)->where('status', 'active')->first();

            if (empty($pulsa)) {
                return false;
            }

            \App::instance('orbit.instance.pulsa', $pulsa);

            return true;
        });

        /**
         * Check if pulsa still available.
         */
        Validator::extend('orbit.allowed.quantity', function ($attribute, $requestedQuantity, $parameters) use ($user) {

            $pulsaId = OrbitInput::post('pulsa_id');

            // $pulsa = \App::make('orbit.instance.pulsa');

            // Globally issued coupon count regardless of the Customer.
            $issuedPulsa = PaymentTransaction::select(
                    'payment_transactions.payment_transaction_id',
                    'payment_transaction_details.object_id',
                    'payment_transactions.user_id'
                )
                ->join('payment_transaction_details', 'payment_transactions.payment_transaction_id', '=', 'payment_transaction_details.payment_transaction_id')
                ->where('payment_transaction_details.object_type', 'pulsa')
                ->where('payment_transaction_details.object_id', $pulsaId)
                ->whereIn('payment_transactions.status', [
                    PaymentTransaction::STATUS_SUCCESS,
                    PaymentTransaction::STATUS_SUCCESS_NO_COUPON,
                    // PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED,
                ])
                ->get();

            $issuedPulsaForUser = false;
            foreach($issuedPulsa as $issuedPulsaItem) {
                if ($issuedPulsaItem->user_id === $user->user_id) {
                    $issuedPulsaForUser = true;
                }
            }

            return $requestedQuantity <= $issuedPulsa && ! $issuedPulsaForUser;
        });
    }
}
