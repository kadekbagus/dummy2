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
use Coupon;
use IssuedCoupon;
use UserDetail;
use Helper\EloquentRecordCounter as RecordCounter;

class CouponPaymentDetailAPIController extends PubControllerAPI
{
    /**
     * GET - get list of coupon payment detail
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
    public function getCouponPaymentDetail()
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

            $issuedCouponId = OrbitInput::get('issued_coupon_id');
            $merchantId = OrbitInput::get('merchant_id');
            $providerId = OrbitInput::get('provider_id');
            $language = OrbitInput::get('language', 'id');

            // set language
            App::setLocale($language);

            $at = Lang::get('label.conjunction.at');

            $prefix = DB::getTablePrefix();

            $validator = Validator::make(
                array(
                    'issued_coupon_id' => $issuedCouponId,
                    'merchant_id'      => $merchantId,
                    'provider_id'      => $providerId,
                ),
                array(
                    'issued_coupon_id' => 'required',
                    'merchant_id'      => 'required',
                    'provider_id'      => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // get redemption code
            $issuedCoupon = IssuedCoupon::where('issued_coupon_id', $issuedCouponId)
                                        ->where('user_id', $user->user_id)
                                        ->where('status', 'issued')
                                        ->first();

            if (empty($issuedCoupon)) {
                $errorMessage = "Redemption Code not found";
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $tenants = PromotionRetailer::select(DB::raw("substring_index(group_concat(distinct {$prefix}merchants.name SEPARATOR ', '), ', ', 2) as link_to_tenant"))
                                        ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                        ->where('promotion_retailer.promotion_id', $issuedCoupon->promotion_id)
                                        ->groupBy('promotion_retailer.promotion_id')
                                        ->orderBy('merchants.name')
                                        ->first();

            // get user detail
            $userDetail = UserDetail::select('users.user_id', 'users.user_firstname', 'users.user_lastname', 'users.user_email', 'user_details.phone')
                                    ->join('users', 'users.user_id', '=', 'user_details.user_id')
                                    ->where('user_details.user_id', $user->user_id)
                                    ->where('users.status', '!=', 'inactive')
                                    ->first();

            $detail = new stdClass();
            $detail->coupon_id = $issuedCoupon->promotion_id;
            $detail->issued_coupon_id = $issuedCoupon->issued_coupon_id;
            $detail->redemption_code = $issuedCoupon->issued_coupon_code;
            $detail->user_id = $user->user_id;
            $detail->user_phone = (empty($userDetail->phone)) ? null : $userDetail->phone;
            $detail->user_email = (empty($userDetail->user_email)) ? null : $userDetail->user_email;
            $detail->user_firstname = (empty($userDetail->user_firstname)) ? null : $userDetail->user_firstname;
            $detail->user_lastname = (empty($userDetail->user_lastname)) ? null : $userDetail->user_lastname;
            $detail->tenants = (empty($tenants->link_to_tenant)) ? null : $tenants->link_to_tenant;

            $this->response->data = new stdClass();
            $this->response->data->returned_records = count($detail);
            $this->response->data->records = $detail;
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
