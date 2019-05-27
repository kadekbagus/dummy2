<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use Coupon;
use PromotionRetailer;
use Tenant;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use URL;
use Validator;
use Activity;
use Language;
use Lang;
use CouponRetailer;
use MerchantStorePaymentProvider;
use BaseMerchant;
use BaseStore;
use Currency;
use Carbon\Carbon;
use IssuedCoupon;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Helper\Security\Encrypter;
use \Queue;
use \App;
use \Exception;
use \UserVerificationNumber;
use Orbit\Helper\Payment\Payment as PaymentClient;

class CouponRedeemAPIController extends PubControllerAPI
{
    /**
     * POST - Pub Redeem Coupon
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string      `cid`                             (required) - Hashed issued coupon ID
     * @param string      `uid`                             (optional) - Hashed user identifier
     * @param string      `merchant_id`                     (required) - ID of the mall
     * @param string      `merchant_verification_number`    (required) - Merchant/Tenant verification number
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postPubRedeemCoupon()
    {
        $activity = Activity::mobileci()
                          ->setActivityType('coupon');

        $user = NULL;
        $mall = NULL;
        $mall_id = NULL;
        $issuedcoupon = NULL;
        $coupon = NULL;
        $httpCode = 200;

        try {
            $user = $this->getUser();

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();

            $mallId = OrbitInput::post('mall_id');
            $storeId = OrbitInput::post('store_id');
            $couponId = OrbitInput::post('cid'); // hashed issued coupon id
            $userIdentifier = OrbitInput::post('uid', NULL); // hashed user identifier
            $verificationNumber = OrbitInput::post('merchant_verification_number', null);
            $paymentProvider = OrbitInput::post('provider_id', 0);
            $phone = OrbitInput::post('phone', null);
            $amount = OrbitInput::post('amount', 0);
            $currency = OrbitInput::post('currency', 'IDR');

            $encryptionKey = Config::get('orbit.security.encryption_key');
            $encryptionDriver = Config::get('orbit.security.encryption_driver');
            $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

            $requestedCouponId = $encrypter->decrypt($couponId);

            // requested coupon before validation
            $coupon = Coupon::excludeDeleted('promotions')
                ->where('promotion_id', $requestedCouponId)
                ->first();

            $validator = Validator::make(
                array(
                    'store_id'         => $storeId,
                    'cid'              => $couponId,
                    'payment_provider' => $paymentProvider,
                ),
                array(
                    'store_id'         => 'required',
                    'cid'              => 'required',
                    'payment_provider' => 'required',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (! empty($userIdentifier)) {
                $encryptionKey = Config::get('orbit.security.encryption_key');
                $encryptionDriver = Config::get('orbit.security.encryption_driver');
                $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

                $userIdentifier = $encrypter->decrypt($userIdentifier);
            }

            $isAvailable = (new IssuedCoupon())->issueCouponViaEmail($requestedCouponId, $userIdentifier);
            if (! is_object($isAvailable)) {
                $errorMessage = 'Issued coupon is not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $redeem_retailer_id = NULL;
            $redeem_user_id = NULL;
            $providerName = 'normal';
            $paymentType = 'normal';

            $issuedCoupon = IssuedCoupon::where('promotion_id', $couponId)
                                        ->where('user_id', $user->user_id)
                                        ->where('status', 'issued')
                                        ->first();

            $baseStore = BaseStore::select('base_stores.base_store_id', 'base_stores.merchant_id', 'base_stores.phone', 'base_stores.base_merchant_id', 'base_merchants.name as store_name', 'merchants.country_id', 'timezones.timezone_name', 'merchants.name as mall_name')
                                  ->join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                                  ->join('merchants', 'merchants.merchant_id', '=', 'base_stores.merchant_id')
                                  ->leftJoin('timezones', 'timezones.timezone_id', '=', 'merchants.timezone_id')
                                  ->where('base_stores.base_store_id', $storeId)
                                  ->first();

            if (empty($mallId)) {
                $mallId = $baseStore->merchant_id;
            }

            $currencies = Currency::where('currency_code', $currency)->first();
            if (empty($currencies)) {
                $errorMessage = 'Currency not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $body = [
                'user_email'             => $user->user_email,
                'user_name'              => $user->user_firstname . ' ' . $user->user_lastname,
                'user_id'                => $user->user_id,
                'country_id'             => $baseStore->country_id,
                'payment_type'           => $paymentType,
                'merchant_id'            => $baseStore->base_merchant_id,
                'merchant_name'          => $baseStore->store_name,
                'store_id'               => $baseStore->base_store_id,
                'store_name'             => $baseStore->store_name,
                'timezone_name'          => $baseStore->timezone_name,
                'building_id'            => $baseStore->merchant_id,
                'building_name'          => $baseStore->mall_name,
                'object_id'              => $couponId,
                'object_type'            => 'coupon',
                'object_name'            => $coupon->promotion_name,
                'coupon_redemption_code' => $issuedCoupon->issued_coupon_code,
                'payment_provider_id'    => $paymentProvider,
                'payment_method'         => $providerName,
                'currency_id'            => $currencies->currency_id,
                'currency'               => $currency,
            ];

            if ($paymentProvider === '0') {
                if (empty($verificationNumber)) {
                    $errorMessage = 'Verification number is empty';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $tenant = Tenant::join('promotion_retailer_redeem', 'promotion_retailer_redeem.retailer_id', '=', 'merchants.merchant_id')
                    ->where('promotion_id', $requestedCouponId)
                    ->where('merchant_id', $storeId)
                    ->where('masterbox_number', $verificationNumber)
                    ->first();

                $csVerificationNumber = UserVerificationNumber::
                    join('promotion_employee', 'promotion_employee.user_id', '=', 'user_verification_numbers.user_id')
                    ->where('promotion_employee.promotion_id', $requestedCouponId)
                    ->where('merchant_id', $mallId)
                    ->where('verification_number', $verificationNumber)
                    ->first();

                if (! is_object($tenant) && ! is_object($csVerificationNumber)) {
                    $errorMessage = Lang::get('mobileci.coupon.wrong_verification_number');
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                } else {
                    if (is_object($tenant)) {
                        $redeem_retailer_id = $tenant->merchant_id;
                    }
                    if (is_object($csVerificationNumber)) {
                        $redeem_user_id = $csVerificationNumber->user_id;
                        $redeem_retailer_id = $mallId;
                    }
                }

                $body['commission_fixed_amount'] = $coupon->fixed_amount_commission;
            } else {
                // using paypro etc
                $paymentType = 'wallet';

                $provider = MerchantStorePaymentProvider::select('payment_providers.payment_provider_id', 'payment_providers.payment_name', 'merchant_store_payment_provider.mdr', 'payment_providers.mdr as default_mdr', 'payment_providers.mdr_commission', 'merchant_store_payment_provider.phone_number_for_sms')
                                                        ->join('payment_providers', 'payment_providers.payment_provider_id', '=', 'merchant_store_payment_provider.payment_provider_id')
                                                        ->where('merchant_store_payment_provider.payment_provider_id', $paymentProvider)
                                                        ->where('merchant_store_payment_provider.object_type', 'store')
                                                        ->where('merchant_store_payment_provider.object_id', $storeId)
                                                        ->where('payment_providers.status', 'active')
                                                        ->first();

                if (empty($provider)) {
                    $errorMessage = 'Payment profider not found';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $providerName = $provider->payment_name;

                $body['to'] = $phone;
                $body['payment_type'] = $paymentType;
                $body['amount'] = $amount;
                $body['phone_number_for_sms'] = $provider->phone_number_for_sms;
                $body['payment_provider_id'] = $paymentProvider;
                $body['payment_method'] = $providerName;
                $body['mdr'] = $provider->mdr;
                $body['default_mdr'] = $provider->default_mdr;
                $body['provider_mdr_commission_percentage'] = $provider->mdr_commission;
                $body['commission_transaction_percentage'] = $coupon->transaction_amount_commission;
            }

            $paymentConfig = Config::get('orbit.payment_server');
            $paymentClient = PaymentClient::create($paymentConfig)->setFormParam($body);
            $response = $paymentClient->setEndPoint('api/v1/pay')
                                    ->request('POST');

            if ($response->status !== 'success') {
                $errorMessage = 'Transaction Failed';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mall = App::make('orbit.empty.merchant');

            $isAvailable->redeemed_date = date('Y-m-d H:i:s');
            $isAvailable->redeem_retailer_id = $redeem_retailer_id;
            $isAvailable->redeem_user_id = $redeem_user_id;
            $isAvailable->redeem_verification_code = $verificationNumber;
            $isAvailable->status = 'redeemed';

            $isAvailable->save();

            // Commit the changes
            $this->commit();

            $this->response->message = 'Coupon has been successfully redeemed.';
            $this->response->data = $isAvailable->issued_coupon_code;

            // customize user property before saving activity
            $user = $couponHelper->customizeUserProps($user, $userIdentifier);

            $activityNotes = sprintf('Coupon Redeemed: %s. Issued Coupon Id: %s.', $coupon->promotion_name, $isAvailable->issued_coupon_id);
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Successful')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseOK();

        } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
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

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }
}
