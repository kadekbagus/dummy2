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
use Lang;
use \Exception;
use PromotionRetailer;
use Helper\EloquentRecordCounter as RecordCounter;
use Activity;
use Coupon;
use Mall;

class CouponCityAPIController extends PubControllerAPI
{

    /**
     * GET - get list city per each coupon
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string coupon_id
     * @param string sortby
     * @param string sortmode
     * @param string mall_id
     * @param string is_detail
     * @param string location
     * @param string orbit.user_location.cookie.name
     * @param string orbit.geo_location.distance
     * @param string ul
     * @param string take
     * @param string skip
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getCouponCity()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try{
            $user = $this->getUser();

            $couponId = OrbitInput::get('coupon_id', null);
            $sort_by = OrbitInput::get('sortby', 'city');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $mall = null;
            $storeName = OrbitInput::get('store_name');
            $mallId = OrbitInput::get('mall_id');
            $skipMall = OrbitInput::get('skip_mall', 'N');

            $validator = Validator::make(
                array(
                    'coupon_id' => $couponId,
                    'skip_mall' => $skipMall,
                ),
                array(
                    'coupon_id' => 'required',
                    'skip_mall' => 'in:Y,N',
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

            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $prefix = DB::getTablePrefix();

            $couponLocations = PromotionRetailer::select(
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city")
                                        )
                                    ->leftJoin('promotions', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('promotions.promotion_id', $couponId)
                                    ->where('merchants.status', '=', 'active');

            // filter by store name
            OrbitInput::get('store_name', function($storeName) use ($couponLocations) {
                $couponLocations->where('merchants.name', $storeName);
            });

            if ($skipMall === 'Y') {
                OrbitInput::get('mall_id', function($mallId) use ($couponLocations) {
                    $couponLocations->where(function($q) use ($mallId) {
                                        $q->where('merchants.parent_id', '!=', $mallId)
                                          ->where('merchants.merchant_id', '!=', $mallId);
                                    });
                    });
            } else {
                OrbitInput::get('mall_id', function($mallId) use ($couponLocations) {
                    $couponLocations->where(function($q) use ($mallId) {
                                        $q->where('merchants.parent_id', '=', $mallId)
                                          ->orWhere('merchants.merchant_id', '=', $mallId);
                                    });
                    });
            }

            $couponLocations = $couponLocations->groupBy('city');

            $_couponLocations = clone($couponLocations);

            $take = PaginationNumber::parseTakeFromGet('city_location');
            $couponLocations->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $couponLocations->skip($skip);

            $couponLocations->orderBy($sort_by, $sort_mode);

            $listOfRec = $couponLocations->get();

            // moved from generic activity number 38
            if (empty($skip) && OrbitInput::get('is_detail', 'n') === 'y'  ) {
                $coupon = Coupon::excludeDeleted()
                    ->where('promotion_id', $couponId)
                    ->first();

                $activityNotes = sprintf('Page viewed: Coupon city list');
                $activity->setUser($user)
                    ->setActivityName('view_coupon_city')
                    ->setActivityNameLong('View Coupon City Page')
                    ->setObject($coupon)
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_couponLocations)->count();
            $data->records = $listOfRec;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

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
