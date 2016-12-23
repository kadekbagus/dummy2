<?php namespace Orbit\Controller\API\v1\Pub\Coupon;
/**
 * Controller for getting coupon count.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Lang;
use \Exception;
use Coupon;
use DB;
use Helper\EloquentRecordCounter as RecordCounter;

class CouponCountAPIController extends PubControllerAPI
{
    public function getCouponCount()
    {
        $httpCode = 200;
        $user = NULL;

        try{
            $this->checkAuth();

            $user = $this->api->user;

            $role = $user->role->role_name;

            if (strtolower($role) === 'guest') {
                $couponCount = 0;
            } else {
                $prefix = DB::getTablePrefix();
                $coupons = Coupon::select(DB::raw("
                                    {$prefix}promotions.promotion_id as promotion_id,
                                    CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                    THEN {$prefix}campaign_status.campaign_status_name
                                    ELSE (
                                        CASE WHEN {$prefix}promotions.coupon_validity_in_date < (
                                            SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                            FROM {$prefix}promotion_retailer opt
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                        )
                                        THEN 'expired'
                                        ELSE {$prefix}campaign_status.campaign_status_name
                                        END)
                                    END AS campaign_status,
                                    CASE WHEN (
                                        SELECT count(opt.promotion_retailer_id)
                                        FROM {$prefix}promotion_retailer opt
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                        AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.coupon_validity_in_date) > 0
                                    THEN 'true'
                                    ELSE 'false'
                                    END AS is_started,
                                    {$prefix}issued_coupons.issued_coupon_id
                                ")
                            )
                            ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                            ->join('issued_coupons', function ($join) {
                                $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                                $join->where('issued_coupons.status', '=', 'issued');
                            })
                            ->where('issued_coupons.user_id', $user->user_id)
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->groupBy('issued_coupons.promotion_id');

                $couponCount = RecordCounter::create($coupons)->count();
            }

            $this->response->data = $couponCount;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';

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
}
