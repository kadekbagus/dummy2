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
use CouponPaymentProvider;
use Helper\EloquentRecordCounter as RecordCounter;

class CouponPaymentProviderAPIController extends PubControllerAPI
{
    /**
     * GET - get list of coupon payment provider
     *
     * @author shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponPaymentProvider()
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

            $sort_by = OrbitInput::get('sortby', 'payment_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $couponId = OrbitInput::get('coupon_id');
            $merchantId = OrbitInput::get('merchant_id');
            $language = OrbitInput::get('language', 'id');

            // set language
            App::setLocale($language);

            $at = Lang::get('label.conjunction.at');

            $prefix = DB::getTablePrefix();

            $validator = Validator::make(
                array(
                    'coupon_id' => $couponId,
                    'merchant_id' => $merchantId,
                ),
                array(
                    'coupon_id' => 'required',
                    'merchant_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $paymentProvider = CouponPaymentProvider::select('payment_providers.payment_provider_id', 'payment_providers.payment_name', 'payment_providers.descriptions', 'payment_providers.deeplink_url')
                                                    ->leftJoin('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_retailer_redeem_id', '=', 'coupon_payment_provider.promotion_retailer_redeem_id')
                                                    ->leftJoin('payment_providers', 'payment_providers.payment_provider_id', '=', 'coupon_payment_provider.payment_provider_id')
                                                    ->where('promotion_retailer_redeem.promotion_id', $couponId)
                                                    ->where('payment_providers.status', 'active')
                                                    ->where('promotion_retailer_redeem.retailer_id', $merchantId)
                                                    ->groupBy('payment_providers.payment_provider_id');

            $paymentProvider = $paymentProvider->orderBy($sort_by, $sort_mode);

            $_paymentProvider = clone $paymentProvider;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $paymentProvider->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $paymentProvider->skip($skip);

            $listPaymentProvider = $paymentProvider->get();
            $count = RecordCounter::create($_paymentProvider)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listPaymentProvider);
            $this->response->data->records = $listPaymentProvider;
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
}
