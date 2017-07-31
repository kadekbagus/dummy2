<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

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
use Promotion;
use PromotionRetailer;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Mall;
use App;
use Lang;
use Orbit\Helper\MongoDB\Client as MongoClient;

class CouponRatingLocationAPIController extends PubControllerAPI
{
    /**
     * GET - get store list inside news/events detil
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
     * @param string news_id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponRatingLocation()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try{
            $user = $this->getUser();

            $objectId = OrbitInput::get('object_id', null);
            $sortBy = OrbitInput::get('sortby', 'name');
            $sortMode = OrbitInput::get('sortmode','asc');
            $mallId = OrbitInput::get('mall_id', null);
            $language = OrbitInput::get('language', 'id');
            $mongoConfig = Config::get('database.mongodb');

            // set language
            App::setLocale($language);

            $at = Lang::get('label.conjunction.at');

            $validator = Validator::make(
                array(
                    'object_id' => $objectId
                ),
                array(
                    'object_id' => 'required'
                ),
                array(
                    'required' => 'Object ID is required',
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
            $ratingLocation = PromotionRetailer::select('merchants.merchant_id as location_id', DB::raw("IF({$prefix}merchants.object_type = 'tenant', CONCAT({$prefix}merchants.name,' {$at} ', oms.name), CONCAT('Mall {$at} ', {$prefix}merchants.name)) as location_name"))
                                        ->join('promotions', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                        ->where('promotions.promotion_id', '=', $objectId);

            OrbitInput::get('cities', function($cities) use ($ratingLocation, $prefix) {
                foreach ($cities as $key => $value) {
                    if (empty($value)) {
                       unset($cities[$key]);
                    }
                }

                if (! empty($cities)) {
                    $ratingLocation->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.city ELSE oms.city END)"), $cities);
                }
            });

            OrbitInput::get('country', function($country) use ($ratingLocation, $prefix) {
                if (! empty($country)) {
                    $ratingLocation->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.country ELSE oms.country END)"), $country);
                }
            });

            OrbitInput::get('mall_id', function($mallId) use ($ratingLocation, $prefix) {
                if (! empty($mallId)) {
                    $ratingLocation->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.merchant_id ELSE oms.merchant_id END)"), $mallId);
                }
            });

            OrbitInput::get('name_like', function($nameLike) use ($ratingLocation, $prefix) {
                $nameLike = substr($this->quote($nameLike), 1, -1);
                $ratingLocation->havingRaw("location_name like '%{$nameLike}%'");
            });

            $role = $user->role->role_name;
            if (strtolower($role) === 'consumer') {
                $queryString = [
                    'object_id'   => $objectId,
                    'object_type' => 'coupon',
                    'user_id'     => $user->user_id
                ];

                $mongoClient = MongoClient::create($mongoConfig);
                $endPoint = "reviews";
                $response = $mongoClient->setQueryString($queryString)
                                        ->setEndPoint($endPoint)
                                        ->request('GET');

                $listOfRec = $response->data;

                $storeIds = array();
                foreach ($listOfRec->records as $location) {
                    $storeIds[] = $location->store_id;
                }

                if (! empty($storeIds)) {
                    $ratingLocation->whereNotIn("merchants.merchant_id", $storeIds);
                }
            }

            $ratingLocation = $ratingLocation->groupBy('location_name');

            $ratingLocation = clone($ratingLocation);

            $take = PaginationNumber::parseTakeFromGet('promotions');
            $ratingLocation->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $ratingLocation->skip($skip);

            $ratingLocation->orderBy('location_name', 'asc');

            $listOfRec = $ratingLocation->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($ratingLocation)->count();
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}