<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * @author shelgi <shelgi@dominopos.com>
 * @desc Controller for ping payment
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use Language;
use Validator;
use PaymentTransaction;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use Elasticsearch\ClientBuilder;
use stdClass;
use Orbit\Helper\Payment\Payment as PaymentClient;
use \Carbon\Carbon as Carbon;

class PingPaymentAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - Ping payment
     *
     * @author Shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string country
     * @param string cities
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getPingPayment()
    {
        $httpCode = 200;
        $keyword = null;
        $user = null;
        $mall = null;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $transactionId = OrbitInput::get('transaction_id', null);
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

            $body['transaction_id'] = $transactionId;

            $paymentConfig = Config::get('orbit.payment_server');
            $paymentClient = PaymentClient::create($paymentConfig)->setFormParam($body);
            $response = $paymentClient->setEndPoint('api/v1/ping-pay')
                                    ->request('POST');

            $responseData = $response->data;

            $date = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->created_at, 'UTC');
            $dateTime = $date->setTimezone($transaction->timezone_name)->toDateTimeString();

            $data = new stdClass();
            $data->transactions = $responseData;
            $data->transaction_time = $dateTime;
            $data->coupon_id = $transaction->object_id;
            $data->coupon_name = $transaction->object_name;
            $data->store_name = $transaction->store_name;
            $data->mall_name = $transaction->building_name;

            $this->response->data = $responseData;
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
        }

        return $this->render($httpCode);
    }

    /**
     * Force $withScore value to FALSE, ignoring previously set value
     * @param $bool boolean
     */
    public function setWithOutScore()
    {
        $this->withoutScore = TRUE;

        return $this;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}