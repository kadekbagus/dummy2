<?php namespace Orbit\Controller\API\v1\Pub\Payment;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Coupon;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Activity;
use Mall;
use Lang;
use \Exception;
use Orbit\Controller\API\v1\Pub\Payment\PaymentHelper;
use Orbit\Helper\Util\CdnUrlGenerator;
use PromotionRetailer;
use PaymentTransaction;
use Helper\EloquentRecordCounter as RecordCounter;

class PaymentPulsaPurchasedListAPIController extends PubControllerAPI
{

    public function getPulsaPurchasedList()
    {
    	$httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = NULL;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $sort_by = OrbitInput::get('sortby', 'coupon_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $paymentHelper = PaymentHelper::create();
            $paymentHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                ),
                array(
                    'language' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $telcoLogo = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as logo_url";
            if ($usingCdn) {
                $telcoLogo = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as logo_url";
            }

            $pulsa = PaymentTransaction::select('payment_transactions.payment_transaction_id',
                                                'payment_transactions.amount',
                                                'payment_transactions.currency',
                                                'payment_transactions.status',
                                                'payment_transactions.phone',
                                                'payment_transactions.payment_method',
                                                'payment_transactions.extra_data',
                                                'payment_transactions.created_at',
                                                'payment_transaction_details.object_name',
                                                'payment_transaction_details.price',
                                                'payment_transaction_details.quantity',
                                                'pulsa.pulsa_display_name',
                                                'pulsa.pulsa_code',
                                                'pulsa.description',
                                                'pulsa.value as pulsa_value',
                                                'pulsa.price as pulsa_price',
                                                'telco_operators.name as operator_name',
                                                DB::raw($telcoLogo),
                                                DB::raw("(SELECT D.value_in_percent FROM {$prefix}payment_transaction_details PTD
                                                            LEFT JOIN {$prefix}discount_codes DC on DC.discount_code_id = PTD.object_id
                                                            LEFT JOIN {$prefix}discounts D on D.discount_id = DC.discount_id
                                                            WHERE PTD.object_type = 'discount'
                                                            AND PTD.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id) as discount_percent"),
                                                DB::raw("(SELECT PTD.price FROM {$prefix}payment_transaction_details PTD
                                                            WHERE PTD.object_type = 'discount'
                                                            AND PTD.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id) as discount_amount")
                                                )
                                        ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                        ->join('pulsa', 'pulsa.pulsa_item_id', '=', 'payment_transaction_details.object_id')
                                        ->join('telco_operators', 'telco_operators.telco_operator_id', '=', 'pulsa.telco_operator_id')
                                        ->leftJoin('media', function($join) use ($prefix) {
                                            $join->on('telco_operators.telco_operator_id', '=', 'media.object_id')
                                                 ->on('media.media_name_long', '=', DB::raw("'telco_operator_logo_orig'"));
                                        })
                                        ->where('payment_transactions.user_id', $user->user_id)
                                        ->where('payment_transaction_details.object_type', 'pulsa')
                                        ->where('payment_transactions.payment_method', '!=', 'normal')
                                        ->whereNotIn('payment_transactions.status', array('starting', 'denied', 'abort'))
                                        ->groupBy('payment_transactions.payment_transaction_id');


            $pulsa = $pulsa->orderBy(DB::raw("{$prefix}payment_transactions.created_at"), 'desc');

            $_pulsa = clone $pulsa;

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $pulsa->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $pulsa->skip($skip);

            $listpulsa = $pulsa->get();
            $count = RecordCounter::create($_pulsa)->count();

            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: Landing Page Pulsa Wallet List Page');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_pulsa_wallet_list')
                    ->setActivityNameLong('View GoToMalls Pulsa Wallet List')
                    ->setObject(NULL)
                    ->setLocation($mall)
                    ->setModuleName('Pulsa')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listpulsa);
            $this->response->data->records = $listpulsa;
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}