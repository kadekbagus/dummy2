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

class CouponStoreAPIController extends PubControllerAPI
{
    /**
     * GET - get store list inside coupon detil
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string mall_id
     * @param string coupon_id
     * @param string is_detail
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponStore()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try{
            $user = $this->getUser();

            $couponId = OrbitInput::get('coupon_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $mallId = OrbitInput::get('mall_id', null);
            $is_detail = OrbitInput::get('is_detail', 'n');
            $mall = null;

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

            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $prefix = DB::getTablePrefix();

            $couponLocations = PromotionRetailer::select(
                                            "merchants.merchant_id",
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.name ELSE oms.name END as name"),
                                            "merchants.object_type"
                                        )
                                    ->join('promotions', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('promotions.promotion_id', $couponId)
                                    ->groupBy("name")
                                    ->groupBy("object_type")
                                    ->orderBy($sort_by, $sort_mode);

            // filter news by mall id
            OrbitInput::get('mall_id', function($mallid) use ($is_detail, $couponLocations, &$group_by) {
                if ($is_detail != 'y') {
                    $couponLocations->where('merchants.parent_id', '=', $mallid);
                }
            });

            $_couponLocations = clone($couponLocations);

            $take = PaginationNumber::parseTakeFromGet('promotions');
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

                $activityNotes = sprintf('Page viewed: Coupon location list');
                $activity->setUser($user)
                    ->setActivityName('view_coupon_location')
                    ->setActivityNameLong('View Coupon Location Page')
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
