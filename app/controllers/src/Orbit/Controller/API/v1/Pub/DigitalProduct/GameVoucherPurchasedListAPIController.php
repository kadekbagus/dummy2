<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

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
use Orbit\Controller\API\v1\Pub\DigitalProduct\PaymentHelper;
use Orbit\Helper\Util\CdnUrlGenerator;
use PromotionRetailer;
use PaymentTransaction;
use Helper\EloquentRecordCounter as RecordCounter;
use SimpleXMLElement;

class GameVoucherPurchasedListAPIController extends PubControllerAPI
{

    public function getGameVoucherPurchasedList()
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

            $sort_by = OrbitInput::get('sortby', 'product_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

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

            $gameLogo = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as logo_url";
            if ($usingCdn) {
                $gameLogo = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as logo_url";
            }

            $game_voucher = PaymentTransaction::select('payment_transactions.payment_transaction_id',
                                                'payment_transactions.amount',
                                                'payment_transactions.currency',
                                                'payment_transactions.status',
                                                'payment_transactions.payment_method',
                                                'payment_transactions.extra_data',
                                                'payment_transactions.created_at',
                                                'payment_transaction_details.object_name as product_name',
                                                'payment_transaction_details.object_type',
                                                'payment_transaction_details.price',
                                                'payment_transaction_details.quantity',
                                                'payment_transaction_details.payload',
                                                'provider_products.provider_name',
                                                DB::raw($gameLogo),
                                                DB::raw("(SELECT D.value_in_percent FROM {$prefix}payment_transaction_details PTD
                                                            LEFT JOIN {$prefix}discounts D on D.discount_id = PTD.object_id
                                                            WHERE PTD.object_type = 'discount'
                                                            AND PTD.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id) as discount_percent"),
                                                DB::raw("(SELECT PTD.price FROM {$prefix}payment_transaction_details PTD
                                                            WHERE PTD.object_type = 'discount'
                                                            AND PTD.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id) as discount_amount")
                                                )
                                            ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                            ->join('digital_products', 'digital_products.digital_product_id', '=', 'payment_transaction_details.object_id')
                                            ->leftJoin('games', 'games.game_id', '=', 'payment_transactions.extra_data')
                                            ->leftJoin('media', function($join) use ($prefix) {
                                                $join->on('games.game_id', '=', 'media.object_id')
                                                     ->on('media.media_name_long', '=', DB::raw("'game_image_orig'"));
                                            })
                                            ->leftJoin('provider_products', 'payment_transaction_details.provider_product_id', '=', 'provider_products.provider_product_id')
                                            ->where('payment_transactions.user_id', $user->user_id)
                                            ->where('payment_transaction_details.object_type', 'digital_product')
                                            ->where('payment_transactions.payment_method', '!=', 'normal')
                                            ->whereNotIn('payment_transactions.status', array('starting', 'denied', 'abort'))
                                            ->where('digital_products.product_type', 'game_voucher')
                                            ->groupBy('payment_transactions.payment_transaction_id');


            $game_voucher = $game_voucher->orderBy(DB::raw("{$prefix}payment_transactions.created_at"), 'desc');

            $_game_voucher = clone $game_voucher;

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $game_voucher->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $game_voucher->skip($skip);

            $listpulsa = $game_voucher->get();
            $count = RecordCounter::create($_game_voucher)->count();

            foreach ($listpulsa as $key => $value) {
                $value->digital_product_code = isset($value->provider_name) ? $this->getVoucherCode($value) : null;
            }

            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: Landing Page Game Voucher Wallet List Page');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_game_voucher_wallet_list')
                    ->setActivityNameLong('View GoToMalls Game Voucher Wallet List')
                    ->setObject(NULL)
                    ->setLocation($mall)
                    ->setModuleName('GameVoucher')
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

    protected function getVoucherCode($gameVoucher)
    {
        $voucherCode = null;

        if (isset($gameVoucher->provider_name)) {
            switch ($gameVoucher->provider_name) {
                case 'ayopay':
                    if (! empty($gameVoucher->payload)) {
                        $voucherString = ',';
                        $voucherXml = new SimpleXMLElement($gameVoucher->payload);

                        if (isset($voucherXml->voucher)) {
                            if (strpos($voucherXml->voucher, $voucherString) !== false) {
                                $voucherData = explode($voucherString, $voucherXml->voucher);
                                $voucherCode = $voucherData[0]."\n".$voucherData[1];
                            }
                        }
                    }

                    break;

                case 'upoint-dtu':
                    $payload = unserialize($gameVoucher->payload);

                    $payloadObj = json_decode($payload);

                    if (isset($payloadObj->info)) {
                        if (isset($payloadObj->info->user_info)) {
                            if (isset($payloadObj->info->user_info->user_id)) {
                                $voucherCode = $voucherCode . "User ID: " . $payloadObj->info->user_info->user_id . "\n";
                            }
                        }
                        if (isset($payloadObj->info->details)) {
                            if (isset($payloadObj->info->details[0])) {
                                if (isset($payloadObj->info->details[0]->server_name)) {
                                    if (! empty($payloadObj->info->details[0]->server_name)) {
                                        $voucherCode = $voucherCode . "Server Name: " . $payloadObj->info->details[0]->server_name;
                                    }
                                }
                            }
                        }
                        if (isset($payloadObj->info->details)) {
                            if (isset($payloadObj->info->details[0])) {
                                if (isset($payloadObj->info->details[0]->username)) {
                                    if (! empty($payloadObj->info->details[0]->username)) {
                                        $voucherCode = $voucherCode . "User Name: " . $payloadObj->info->details[0]->username;
                                    }
                                }
                            }
                        }
                    }
                    break;

                case 'upoint-voucher':
                    # code...
                    break;

                default:

                    break;
            }
        }

        return $voucherCode;
    }
}
