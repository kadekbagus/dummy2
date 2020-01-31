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
                                                'payment_transactions.extra_data',
                                                'payment_transactions.created_at',
                                                'payment_transactions.external_payment_transaction_id',
                                                'payment_transaction_details.object_name as product_name',
                                                'payment_transaction_details.object_type',
                                                'payment_transaction_details.price',
                                                'payment_transaction_details.quantity',
                                                DB::raw($gameLogo)
                                                )
                                            ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                            ->join('digital_products', 'digital_products.digital_product_id', '=', 'payment_transaction_details.object_id')
                                            ->leftJoin('games', 'games.game_id', '=', 'payment_transactions.extra_data')
                                            ->leftJoin('media', function($join) use ($prefix) {
                                                $join->on('games.game_id', '=', 'media.object_id')
                                                     ->on('media.media_name_long', '=', DB::raw("'game_image_orig'"));
                                            })
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
}
