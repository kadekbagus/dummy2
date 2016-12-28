<?php namespace Orbit\Controller\API\v1\Pub\Promotion;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use News;
use NewsMerchant;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\Promotion\PromotionHelper;
use Mall;

class PromotionLocationAPIController extends PubControllerAPI
{

    /**
     * GET - Get list location (stores and malls) of the promotion
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string promotion_id
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

    public function getPromotionLocations()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try{
            $user = $this->getUser();

            $promotionId = OrbitInput::get('promotion_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $mallId = OrbitInput::get('mall_id', null);
            $is_detail = OrbitInput::get('is_detail', 'n');
            $location = OrbitInput::get('location');
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $ul = OrbitInput::get('ul', null);
            $mall = null;

            $validator = Validator::make(
                array(
                    'promotion_id' => $promotionId,
                ),
                array(
                    'promotion_id' => 'required',
                ),
                array(
                    'required' => 'Promotion ID is required',
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

            $promotionLocation = NewsMerchant::select(
                                        DB::raw("{$prefix}merchants.merchant_id as merchant_id"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END as mall_id"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN CONCAT({$prefix}merchants.name, ' at ', oms.name) ELSE {$prefix}merchants.name END as name"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.address_line1 ELSE {$prefix}merchants.address_line1 END as address"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.floor ELSE '' END as floor"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.unit ELSE '' END as unit"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.operating_hours ELSE '' END as operating_hours"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.is_subscribed ELSE {$prefix}merchants.is_subscribed END as is_subscribed"),
                                        DB::raw("{$prefix}merchants.object_type as location_type"),
                                        DB::raw("img.path as location_logo"),
                                        DB::raw("map.path as map_image"),
                                        DB::raw("{$prefix}merchants.phone as phone"),
                                        DB::raw("x(position) as latitude"),
                                        DB::raw("y(position) as longitude")
                                    )
                                    ->leftJoin('news', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', DB::raw("IF({$prefix}merchants.object_type = 'tenant', {$prefix}merchants.parent_id, {$prefix}merchants.merchant_id)"))
                                    // Map
                                    ->leftJoin(DB::raw("{$prefix}media as map"), function($q) use ($prefix){
                                        $q->on(DB::raw('map.object_id'), '=', "merchants.merchant_id")
                                          ->on(DB::raw('map.media_name_long'), 'IN', DB::raw("('mall_map_orig', 'retailer_map_orig')"));
                                    })
                                    // Logo
                                    ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix){
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
                                    ->where('news_merchant.news_id', '=', $promotionId)
                                    ->where('merchants.status', '=', 'active');

            // filter news by mall id
            $group_by = '';
            OrbitInput::get('mall_id', function($mallid) use ($promotionLocation, &$group_by) {
                $promotionLocation->where(function($q) use ($mallid){
                                    $q->where('merchants.parent_id', '=', $mallid)
                                      ->orWhere('merchants.merchant_id', '=', $mallid);
                                });
                $group_by = 'mall';
            });

            OrbitInput::get('location', function($location) use ($promotionLocation, $userLocationCookieName, $ul, $distance, $prefix) {
                $position = isset($ul)?explode("|", $ul):null;
                $lon = isset($position[0])?$position[0]:null;
                $lat = isset($position[0])?$position[0]:null;

                if ($location == 'mylocation' && ! empty($lon) && ! empty($lat)) {
                    if (! empty($ul)) {
                        $position = explode("|", $ul);
                        $lon = $position[0];
                        $lat = $position[1];
                    } else {
                        // get lon lat from cookie
                        $userLocationCookieArray = isset($_COOKIE[$userLocationCookieName]) ? explode('|', $_COOKIE[$userLocationCookieName]) : NULL;
                        if (! is_null($userLocationCookieArray) && isset($userLocationCookieArray[0]) && isset($userLocationCookieArray[1])) {
                            $lon = $userLocationCookieArray[0];
                            $lat = $userLocationCookieArray[1];
                        }
                    }

                    $promotionLocation->addSelect(DB::raw("6371 * acos( cos( radians({$lat}) ) * cos( radians( x({$prefix}merchant_geofences.position) ) ) * cos( radians( y({$prefix}merchant_geofences.position) ) - radians({$lon}) ) + sin( radians({$lat}) ) * sin( radians( x({$prefix}merchant_geofences.position) ) ) ) AS distance"))
                                        ->havingRaw("distance <= {$distance}");
                } else {
                    $promotionLocation->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END)"), $location);
                }
            });

            if ($group_by === 'mall') {
                $promotionLocation->groupBy('mall_id');
            } else {
                $promotionLocation->groupBy('merchants.merchant_id');
            }

            $_promotionLocation = clone($promotionLocation);

            $take = PaginationNumber::parseTakeFromGet('news');
            $promotionLocation->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $promotionLocation->skip($skip);

            $promotionLocation->orderBy($sort_by, $sort_mode);

            $listOfRec = $promotionLocation->get();

            // moved from generic activity number 36
            if (empty($skip) && OrbitInput::get('is_detail', 'n') === 'y'  ) {
                $promotion = News::excludeDeleted()
                    ->where('news_id', $promotionId)
                    ->first();

                $activityNotes = sprintf('Page viewed: Promotion location list');
                $activity->setUser($user)
                    ->setActivityName('view_promotion_location')
                    ->setActivityNameLong('View Promotion Location Page')
                    ->setObject($promotion)
                    ->setLocation($mall)
                    ->setModuleName('Promotion')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_promotionLocation)->count();
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