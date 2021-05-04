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
use CouponRetailerRedeem;
use Helper\EloquentRecordCounter as RecordCounter;

class CouponWalletLocationAPIController extends PubControllerAPI
{
    /**
     * GET - get list of coupon wallet locations
     *
     * @author Ahmad <ahmad@dominopos.com>
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
    public function getCouponWalletLocations()
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

            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $couponId = OrbitInput::get('coupon_id');
            $language = OrbitInput::get('language', 'id');

            // set language
            App::setLocale($language);

            $at = Lang::get('label.conjunction.at');

            $prefix = DB::getTablePrefix();

            $validator = Validator::make(
                array(
                    'coupon_id' => $couponId,
                ),
                array(
                    'coupon_id' => 'required',
                ),
                array(
                    'required' => 'Coupon ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mall = CouponRetailerRedeem::select(
                    DB::raw("{$prefix}merchants.merchant_id as merchant_id"),
                    DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END as mall_id"),
                    DB::raw("{$prefix}merchants.object_type as location_type"),
                    DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN CONCAT({$prefix}merchants.name, ' {$at} ', oms.name) ELSE CONCAT('Customer Service {$at} ', {$prefix}merchants.name) END as name"),
                    DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.ci_domain ELSE {$prefix}merchants.ci_domain END as ci_domain"),
                    DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city"),
                    DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.description ELSE {$prefix}merchants.description END as description"),
                    'promotion_retailer_redeem.promotion_id as coupon_id',
                    'promotions.begin_date as begin_date',
                    'promotions.end_date as end_date',
                    'promotions.coupon_validity_in_date as coupon_validity_in_date',
                    DB::raw("( SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                FROM {$prefix}merchants om
                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                WHERE om.merchant_id = (CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END)
                            ) as tz"),
                    DB::Raw("img.path as location_logo"),
                    DB::Raw("{$prefix}merchants.phone as phone")
                )
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
                ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                ->leftJoin('promotions', 'promotions.promotion_id', '=', 'promotion_retailer_redeem.promotion_id')
                ->join('issued_coupons', function ($join) {
                    $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                    $join->where('issued_coupons.status', '=', 'issued');
                })
                ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix) {
                    $q->on(DB::raw('img.object_id'), '=', DB::Raw("
                                                        (select CASE WHEN t.object_type = 'tenant'
                                                                    THEN m.merchant_id
                                                                    ELSE t.merchant_id
                                                                END as mall_id
                                                        from orb_merchants t
                                                        join orb_merchants m
                                                            on m.merchant_id = t.parent_id
                                                        where t.merchant_id = {$prefix}merchants.merchant_id)
                                            "))
                        ->on(DB::raw('img.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                })
                ->where('issued_coupons.user_id', $user->user_id)
                ->where('promotion_retailer_redeem.promotion_id', '=', $couponId)
                ->where('merchants.status', 'active')
                ->groupBy('merchant_id')
                ->havingRaw('tz <= coupon_validity_in_date AND tz >= begin_date');

            OrbitInput::get('keyword', function($keyword) use ($mall) {
                $mall->where(function($search) use ($keyword) {
                    $search->where(DB::raw('oms.name'), 'like', "%{$keyword}%")
                           ->orWhere('merchants.name', 'like', "%{$keyword}%");
                });
            });

            OrbitInput::get('filter_name', function ($filterName) use ($mall, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $mall->whereRaw("SUBSTR((CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END),1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $mall->whereRaw("SUBSTR((CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END),1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            $mall = $mall->orderBy($sort_by, $sort_mode);

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            $listmall = $mall->get();
            $count = RecordCounter::create($_mall)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmall);
            $this->response->data->records = $listmall;
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
