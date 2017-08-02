<?php namespace Orbit\Controller\API\v1\Pub\Store;

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
use Tenant;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Mall;
use App;
use Lang;
use Orbit\Helper\MongoDB\Client as MongoClient;

class StoreRatingLocationAPIController extends PubControllerAPI
{
    /**
     * GET - get store location
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
     * @param string object_id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getStoreRatingLocation()
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

            // get store name and country
            $tenant = Tenant::select('name', 'country')->where('merchant_id', $objectId)->first();
            if (! is_object($tenant)) {
                OrbitShopAPI::throwInvalidArgument('Store ID not found');
            }

            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $prefix = DB::getTablePrefix();
            $ratingLocation = Tenant::select('merchants.merchant_id as location_id', DB::raw("CONCAT({$prefix}merchants.name,' {$at} ', oms.name) as location_name"))
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('merchants.status', '=', 'active')
                                    ->where(DB::raw('oms.status'), '=', 'active')
                                    ->where('merchants.name', '=', $tenant->name);

            OrbitInput::get('cities', function($cities) use ($ratingLocation, $prefix) {
                foreach ($cities as $key => $value) {
                    if (empty($value)) {
                       unset($cities[$key]);
                    }
                }

                if (! empty($cities)) {
                    $ratingLocation->whereIn(DB::raw("oms.city"), $cities);
                }
            });

            OrbitInput::get('country', function($country) use ($ratingLocation, $prefix) {
                if (! empty($country)) {
                    $ratingLocation->where(DB::raw("oms.country"), $country);
                }
            });

            OrbitInput::get('mall_id', function($mallId) use ($ratingLocation, $prefix) {
                if (! empty($mallId)) {
                    $ratingLocation->where('merchants.parent_id', $mallId);
                }
            });

            OrbitInput::get('name_like', function($nameLike) use ($ratingLocation, $prefix) {
                $nameLike = substr($this->quote($nameLike), 1, -1);
                $ratingLocation->havingRaw("location_name like '%{$nameLike}%'");
            });

            $role = $user->role->role_name;
            if (strtolower($role) === 'consumer') {
                $prefix = DB::getTablePrefix();
                $storeInfo = Tenant::select('merchants.name', DB::raw("oms.country"))
                            ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                            ->where('merchants.merchant_id', $objectId)
                            ->first();

                if (! is_object($storeInfo)) {
                    throw new OrbitCustomException('Unable to find store.', Tenant::NOT_FOUND_ERROR_CODE, NULL);
                }

                $objectIds = [];
                $storeIdList = Tenant::select('merchants.merchant_id')
                                ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                ->where('merchants.status', '=', 'active')
                                ->where(DB::raw('oms.status'), '=', 'active')
                                ->where('merchants.name', $storeInfo->name)
                                ->where(DB::raw("oms.country"), $storeInfo->country)
                                ->get();

                foreach ($storeIdList as $storeId) {
                    $objectIds[] = $storeId->merchant_id;
                }

                $arrayQuery = 'object_id[]=' . implode('&object_id[]=', $objectIds);

                $queryString = [
                    'object_type' => 'store',
                    'user_id'     => $user->user_id
                ];

                $mongoClient = MongoClient::create($mongoConfig);
                $endPoint = "reviews";
                if (! empty($arrayQuery)) {
                    $endPoint = "reviews?" . $arrayQuery;
                }

                $response = $mongoClient->setCustomQuery(TRUE)
                                        ->setQueryString($queryString)
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

            $_ratingLocation = clone($ratingLocation);

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $ratingLocation->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $ratingLocation->skip($skip);

            $ratingLocation->orderBy('location_name', 'asc');

            $listOfRec = $ratingLocation->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_ratingLocation)->count();
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