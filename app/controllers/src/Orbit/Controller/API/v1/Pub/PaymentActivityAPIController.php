<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * @author shelgi <shelgi@dominopos.com>
 * @desc Controller for ping payment
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use Language;
use Coupon;
use Validator;
use PaymentTransaction;
use BaseStore;
use User;
use IssuedCoupon;
use Activity;
use stdClass;
use Orbit\Helper\Payment\Payment as PaymentClient;
use \Carbon\Carbon as Carbon;

class PaymentActivityAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $withoutScore = FALSE;

    /**
     * POST - Payment Activity
     * Activity recorder for payment status. This is the callback function that will be ping-ed 
     * by our payment server api (lumen) once it gets any response from the Payment Gateway (PayPro, etc).
     * 
     * @author Budi <budi@dominopos.com>
     * 
     * @param string        $transaction_id     The transaction ID being recorded.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewPaymentActivity()
    {
        $httpCode = 200;
        $keyword = null;
        $user = null;
        $mall = null;

        try {

            $transactionId = OrbitInput::post('transaction_id', null);
            $language = OrbitInput::get('language', 'id');
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
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $transaction = PaymentTransaction::where('payment_transaction_id', $transactionId)->first();
            
            if (! is_object($transaction)) {
                $errorMessage = 'Transaction ' . $transactionId . ' not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::where('user_id', $transaction->user_id)->first();

            if (empty($user)) {
                $errorMessage = 'Can not find User (' . $transaction->user_id . ') 
                    related to Transaction (' . $transaction->payment_transaction_id .')';

                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $issuedCoupon = IssuedCoupon::with(['coupon'])
                                        ->where('issued_coupon_id', $transaction->issued_coupon_id)
                                        ->whereIn('status', ['issued', 'redeemed'])
                                        ->first();

            if (! is_object($issuedCoupon)) {
                $errorMessage = 'Can not find related Issued Coupon #' . $transaction->issued_coupon_id . ' for Transaction #' . 
                    $transaction->payment_transaction_id .'';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $activity = Activity::mobileci()
                          ->setActivityType('coupon');

            $activitySaved = false;

            if ($transaction->status === 'success') {

                $activityNotes = sprintf('%s, %s, %s, %s', 
                    $transaction->object_name,
                    $transaction->amount,
                    $transaction->notes,
                    $transaction->payment_method
                );

                $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption (Successful)')
                    ->setObject($issuedCoupon)
                    ->setNotes($activityNotes)
                    ->setLocation(null)
                    ->setModuleName('Coupon')
                    ->responseOK();

                $activity->coupon_id = $issuedCoupon->promotion_id;
                $activity->coupon_name = $issuedCoupon->coupon->promotion_name;

                $activity->save();

                $activity = Activity::mobileci()
                            ->setActivityType('payment')
                            ->setUser($user)
                            ->setActivityName('payment_transaction_successful')
                            ->setActivityNameLong('Payment Transaction Successful')
                            ->setObject($transaction)
                            ->setObjectDisplayName($transaction->payment_method)
                            ->setNotes($activityNotes)
                            ->setLocation(null)
                            ->setModuleName('Transaction')
                            ->responseOK();

                $activity->save();

                $activitySaved = true;
            }
            else if ($transaction->status === 'failed') {

                $activityNotes = sprintf('%s, %s, %s, %s',  
                    $transaction->object_name,
                    $transaction->amount,
                    $transaction->notes,
                    $transaction->payment_method
                );

                $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption (Failed)')
                    ->setObject($issuedCoupon)
                    ->setNotes($activityNotes)
                    ->setLocation(null)
                    ->setModuleName('Coupon')
                    ->responseFailed();

                $activity->coupon_id = $issuedCoupon->promotion_id;
                $activity->coupon_name = $issuedCoupon->coupon->promotion_name;

                $activity->save();

                // payment transaction failed
                $activity = Activity::mobileci()
                            ->setActivityType('payment')
                            ->setUser($user)
                            ->setActivityName('payment_transaction_failed')
                            ->setActivityNameLong('Payment Transaction Failed')
                            ->setObject($transaction)
                            ->setObjectDisplayName($transaction->payment_method)
                            ->setNotes($activityNotes)
                            ->setLocation(null)
                            ->setModuleName('Transaction')
                            ->responseFailed()
                            ->save();


                $activitySaved = true;
            }

            $data = new stdClass;
            $data->transaction_id = $transactionId;
            $data->activity_saved = $activitySaved;

            $this->response->data = $data;
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

            // \Log::debug('PaymentActivity: (ERR) ' . $e->getMessage());
        }

        return $this->render($httpCode);
    }
}
