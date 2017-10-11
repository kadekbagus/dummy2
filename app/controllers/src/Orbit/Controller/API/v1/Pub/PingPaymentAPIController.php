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
use Coupon;
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
            $valid_language = Language::where('name', $language)->first();

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $couponImage = Coupon::select(DB::Raw("
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                    CASE WHEN ({$prefix}coupon_translations.description = '' or {$prefix}coupon_translations.description is null) THEN default_translation.description ELSE {$prefix}coupon_translations.description END as description,
                                    CASE WHEN (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id) is null
                                    THEN
                                        (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = default_translation.coupon_translation_id)
                                    ELSE
                                        (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id)
                                    END AS original_media_path
                                "))
                            ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                            ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                            ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                  ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                            })
                            ->leftJoin('coupon_translations as default_translation', function ($q) {
                                $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                                  ->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id');
                            })
                            ->where('promotions.promotion_id', $transaction->object_id)
                            ->first();

            $imageUrl = empty($couponImage) ? null : $couponImage->original_media_path;
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
            $data->transaction_amount = $transaction->amount;
            $data->coupon_image_url = $imageUrl;

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