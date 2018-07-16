<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use App;
use Lang;
use \Exception;
use PromotionRetailer;
use CouponRetailerRedeem;
use CouponPaymentProvider;
use Coupon;
use BaseStore;
use Helper\EloquentRecordCounter as RecordCounter;

class CouponSaveRedeemLocation extends PubControllerAPI
{
    /**
     * POST - Save the selected redeem location.
     * Applicable for paid coupon Sepulsa at the moment.
     *
     * @author Budi <budi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param  payment_transaction_id $paymentId internal payment transaction id.
     * @param string merchant_id merchant id where the customer WANT to redeem.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postSaveRedeemLocation()
    {
        $httpCode = 200;
        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $couponId = OrbitInput::post('coupon_id');
            $storeId = OrbitInput::post('store_id');
            $mallId = OrbitInput::post('mall_id');
            $paymentId = OrbitInput::post('payment_transaction_id');

            $this->beginTransaction();

            $prefix = DB::getTablePrefix();

            // TODO: Validate payment exists.
            $validator = Validator::make(
                array(
                    'coupon_id' => $couponId,
                    'merchant_id' => $merchantId,
                    'payment_transaction_id' => $paymentId,
                ),
                array(
                    'coupon_id' => 'required',
                    'merchant_id' => 'required',
                    'payment_transaction_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $redeemLocation = PromotionRetailer::where('promotion_id', $couponId)->where('retailer_id', $merchantId)->first();

            if (empty($redeemLocation)) {
                $errorMessage = 'Cannot redeem coupon at the given location! Redeem location is not valid.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $baseStore = BaseStore::select('base_stores.base_store_id', 'base_stores.merchant_id', 'base_stores.phone', 'base_stores.base_merchant_id', 'base_merchants.name as store_name', 'merchants.country_id', 'timezones.timezone_name', 'merchants.name as mall_name')
                                  ->join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                                  ->join('merchants', 'merchants.merchant_id', '=', 'base_stores.merchant_id')
                                  ->leftJoin('timezones', 'timezones.timezone_id', '=', 'merchants.timezone_id')
                                  ->where('base_stores.base_store_id', $storeId)
                                  ->where('base_stores.merchant_id', $mallId)
                                  ->first();

            $payment = PaymentTransaction::with(['details.normal_paypro_detail', 'issued_coupon.coupon'])->findOrFail($paymentId);

            // TODO: Get detail according to coupon ID, not details->first()
            $transactionDetailNormalPaypro = $payment->details->first()->normal_paypro_detail;

            if (empty($transactionDetailNormalPaypro)) {
                $errorMessage = 'Transaction not found!';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (empty($payment->issued_coupon)) {
                $errorMessage = 'Can not find issued coupon related to this coupon.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $transactionDetailNormalPaypro->store_id = $baseStore->store_id;
            $transactionDetailNormalPaypro->store_name = $baseStore->store_name;
            $transactionDetailNormalPaypro->merchant_id = $baseStore->base_merchant_id;
            $transactionDetailNormalPaypro->merchant_name = $baseStore->store_name;
            $transactionDetailNormalPaypro->building_id = $baseStore->merchant_id;
            $transactionDetailNormalPaypro->building_name = $baseStore->mall_name;
            $transactionDetailNormalPaypro->save();

            $this->commit();

            $this->response->data = new stdClass();
            $this->response->data->redeem_url = $payment->issued_coupon->url;

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
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
