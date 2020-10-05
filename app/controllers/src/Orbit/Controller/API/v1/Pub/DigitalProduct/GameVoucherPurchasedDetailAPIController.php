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
use ProviderProduct;

class GameVoucherPurchasedDetailAPIController extends PubControllerAPI
{

    public function getGameVoucherPurchasedDetail()
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

            $payment_transaction_id = OrbitInput::get('payment_transaction_id');
            $language = OrbitInput::get('language', 'id');

            $validator = Validator::make(
                array(
                    'language' => $language,
                    'payment_transaction_id' => $payment_transaction_id,
                ),
                array(
                    'language' => 'required',
                    'payment_transaction_id' => 'required',
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
                                            'payment_transactions.notes',
                                            'payment_transactions.extra_data',
                                            'payment_transactions.created_at',
                                            DB::raw("convert_tz({$prefix}payment_transactions.created_at, '+00:00', {$prefix}payment_transactions.timezone_name) as date_tz"),
                                            'payment_transactions.external_payment_transaction_id',
                                            'payment_transaction_details.object_name as product_name',
                                            'payment_transaction_details.object_type',
                                            'payment_transaction_details.provider_product_id',
                                            'payment_transaction_details.price',
                                            'payment_transaction_details.quantity',
                                            'payment_midtrans.payment_midtrans_info',
                                            'digital_products.digital_product_id as item_id',
                                            'payment_transaction_details.payload',
                                            DB::raw($gameLogo)
                                            )

                                        ->with(['discount_code' => function($discountCodeQuery) {
                                            $discountCodeQuery->select('payment_transaction_id', 'discount_code_id', 'discount_id', 'discount_code as used_discount_code')->with(['discount' => function($discountDetailQuery) {
                                                $discountDetailQuery->select('discount_id', 'discount_code as parent_discount_code', 'discount_title', 'value_in_percent as percent_discount');
                                            }]);
                                        }])
                                        ->with(['discount' => function($discountQuery) {
                                            $discountQuery->select('payment_transaction_id', 'object_id', 'price as discount_amount')->with(['discount' => function($discountQuery) {
                                                $discountQuery->select('discount_id', 'discount_code as parent_discount_code', 'discount_title', 'value_in_percent as percent_discount');
                                            }]);
                                        }])

                                        ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                        ->join('digital_products', 'digital_products.digital_product_id', '=', 'payment_transaction_details.object_id')
                                        ->leftJoin('games', 'games.game_id', '=', 'payment_transactions.extra_data')
                                        ->leftJoin('media', function($join) use ($prefix) {
                                            $join->on('games.game_id', '=', 'media.object_id')
                                                    ->on('media.media_name_long', '=', DB::raw("'game_image_orig'"));
                                        })
                                        ->leftJoin('payment_midtrans', 'payment_midtrans.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                        ->where('payment_transactions.user_id', $user->user_id)
                                        ->where('payment_transaction_details.object_type', 'digital_product')
                                        ->where('payment_transactions.payment_method', '!=', 'normal')
                                        ->whereNotIn('payment_transactions.status', array('starting', 'denied', 'abort'))
                                        ->where('digital_products.product_type', 'game_voucher')
                                        ->where(function($query) use($payment_transaction_id) {
                                            $query->where('payment_transactions.payment_transaction_id', '=', $payment_transaction_id)
                                                    ->orWhere('payment_transactions.external_payment_transaction_id', '=', $payment_transaction_id);
                                            })

                                        ->first();

            if (! $game_voucher) {
                OrbitShopAPI::throwInvalidArgument('purchased detail not found');
            }

            $game_voucher->payment_midtrans_info = json_decode(unserialize($game_voucher->payment_midtrans_info));
            $game_voucher->digital_product_code = $this->getVoucherCode($game_voucher);

            $this->response->data = $game_voucher;
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
            $this->response->data = null;
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
        $provider = '';

        $provider = ProviderProduct::where('provider_product_id', $gameVoucher->provider_product_id)
            ->first();

        $voucherCode = null;

        if (isset($provider->provider_name)) {
            switch ($provider->provider_name) {
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
                    $payload = unserialize($gameVoucher->notes);

                    if (isset($payload['inquiry'])) {
                        $payloadObj = json_decode($payload['inquiry']);

                        if (isset($payloadObj->info)) {
                            if (isset($payloadObj->info->user_info)) {
                                // append user_id
                                if (isset($payloadObj->info->user_info->user_id)) {
                                    $voucherCode = $voucherCode . "User ID: " . $payloadObj->info->user_info->user_id . "\n";
                                }
                                // append server_id
                                if (isset($payloadObj->info->user_info->server_id) && $payloadObj->info->user_info->server_id != '1') {
                                    $voucherCode = $voucherCode . "Server ID: " . $payloadObj->info->user_info->server_id . "\n";
                                }
                            }

                            if (isset($payloadObj->info->details)) {
                                if (is_array($payloadObj->info->details) && isset($payloadObj->info->details[0])) {
                                    if (isset($payloadObj->info->details[0])) {
                                        // append server_name
                                        if (isset($payloadObj->info->details[0]->server_name)) {
                                            if (! empty($payloadObj->info->details[0]->server_name)) {
                                                $voucherCode = $voucherCode . "Server Name: " . $payloadObj->info->details[0]->server_name;
                                            }
                                        }
                                        // append user name
                                        if (isset($payloadObj->info->details[0]->username)) {
                                            if (! empty($payloadObj->info->details[0]->username)) {
                                                $voucherCode = $voucherCode . "User Name: " . $payloadObj->info->details[0]->username;
                                            }
                                        } elseif (isset($payloadObj->info->details[0]->role_name)) {
                                            if (! empty($payloadObj->info->details[0]->role_name)) {
                                                $voucherCode = $voucherCode . "User Name: " . $payloadObj->info->details[0]->role_name;
                                            }
                                        }
                                    }
                                }
                            }
                            // append user_name if detail is object
                            if (isset($payloadObj->info->details)) {
                                if (is_object($payloadObj->info->details) && isset($payloadObj->info->details->username)) {
                                    $voucherCode = $voucherCode . "User Name: " . $payloadObj->info->details->username;
                                }
                            }
                        }
                    }

                    break;

                case 'upoint-voucher':
                    if (! empty($gameVoucher->payload)) {
                        $payload = unserialize($gameVoucher->payload);
                        $payloadObj = json_decode($payload);

                        if (isset($payloadObj->item) && is_array($payloadObj->item)) {
                            $voucherData = array_filter($payloadObj->item, function($key) {
                                return $key->name === 'voucher';
                            });

                            if (isset($voucherData[0]) && isset($voucherData[0]->value)) {
                                $voucherCode = str_replace(';', "\n", $voucherData[0]->value);
                            }
                        }
                    }

                    break;

                default:

                    break;
            }
        }

        return $voucherCode;
    }
}
