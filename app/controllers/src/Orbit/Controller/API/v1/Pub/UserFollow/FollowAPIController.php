<?php namespace Orbit\Controller\API\v1\Pub\UserFollow;
/**
 * An API controller for managing feedback.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\OneSignal\OneSignal;
use Config;
use Validator;
use Activity;
use Mall;
use Tenant;
use BaseStore;
use BaseMerchant;
use DB;
use Orbit\Models\Gamification\UserGameEvent;
use Event;

class FollowAPIController extends PubControllerAPI
{
    /**
     * POST - follow and unfollow
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string feedback
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postFollow()
    {
        $user = NULL;
        $httpCode = 200;
        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $object_id = OrbitInput::post('object_id');
            $object_type = OrbitInput::post('object_type');
            $city = OrbitInput::post('city');
            $city = (array) $city;
            $country = OrbitInput::post('country');
            $action = OrbitInput::post('action');
            $mall_id = OrbitInput::post('mall_id', null);

            $validator = Validator::make(
                array(
                    'object_id'   => $object_id,
                    'object_type' => $object_type,
                    'action'      => $action,
                ),
                array(
                    'object_id'   => 'required',
                    'object_type' => 'required|in:mall,store',
                    'action'      => 'required|in:follow,unfollow',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // generate date
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();
            $response = new \stdclass();
            $response->data = null;

            switch($object_type) {
                case "mall":
                    // check already follow or not
                    $queryString = [
                        'user_id'     => $user->user_id,
                        'object_id'   => $object_id,
                        'object_type' => 'mall'
                    ];

                    $existingData = $mongoClient->setQueryString($queryString)
                                         ->setEndPoint('user-follows')
                                         ->request('GET');

                    $mall = Mall::excludeDeleted('merchants')
                                  ->where('merchant_id', '=', $object_id)
                                  ->first();

                    if (is_object($mall)) {
                         $city = $mall->city;
                         $country_id = $mall->country_id;
                    }

                    $gamificationData = (object) [
                        'object_id' => $object_id,
                        'object_type' => $object_type,
                        'object_name' => $mall->name,
                        'country_id' => $country_id,
                        'city' => $city,
                    ];

                    if (count($existingData->data->records) === 0) {
                        // follow
                        $city = null;
                        $country_id = null;


                        $dataInsert = [
                            'user_id'     => $user->user_id,
                            'object_id'   => $object_id,
                            'object_type' => $object_type,
                            'city'        => $city,
                            'country_id'  => $country_id,
                            'mall_id'     => $object_id,
                            'created_at'  => $dateTime
                        ];

                        $response = $mongoClient->setFormParam($dataInsert)
                                                ->setEndPoint('user-follows')
                                                ->request('POST');

                        Event::fire('orbit.follow.postfollow.success', array($user, $gamificationData));

                    } else {
                        // unfollow
                        $id = $existingData->data->records[0]->_id;
                        $response = $mongoClient->setEndPoint("user-follows/$id")
                                                ->request('DELETE');
                        Event::fire('orbit.follow.postunfollow.success', array($user, $gamificationData));
                    }

                    break;
                case "store":
                    if ($action === 'follow')
                    {
                        $baseMerchantId = null;
                        $baseMerchantName = null;
                        $baseMerchantCountry = null;

                        if (!empty($mall_id)) {
                            // mall level

                            // User will follow all store in mall,  when store have more than one store in same mall
                            // Get store name,city and country
                            $storeInfo = Tenant::select('merchants.merchant_id', 'merchants.name', DB::raw('parent.city as city'), DB::raw('parent.country_id as country_id'), 'base_stores.base_merchant_id')
                                                ->leftJoin('merchants as parent', DB::raw('parent.merchant_id'), '=', 'merchants.parent_id')
                                                ->leftJoin('base_stores', 'base_stores.base_store_id', '=', 'merchants.merchant_id')
                                                ->where('merchants.merchant_id', $object_id)
                                                ->where('merchants.parent_id',  $mall_id)
                                                ->excludeDeleted('merchants')
                                                ->first();

                            $baseMerchantId = $storeInfo->base_merchant_id;
                            $baseMerchantName = $storeInfo->name;
                            $baseMerchantCountry = $storeInfo->country_id;

                            if (! empty($storeInfo)) {
                                $stores = Tenant::select('name', 'merchant_id as store_id')
                                                    ->where('name', $storeInfo->name)
                                                    ->where('parent_id',  $mall_id)
                                                    ->excludeDeleted()
                                                    ->get();

                                if (count($stores) > 0) {
                                    $dataStoresInsert = array();
                                    foreach ($stores as $key => $stores) {
                                        $dataStoresInsert[] = [
                                            'user_id'          => $user->user_id,
                                            'object_id'        => $stores->store_id,
                                            'object_type'      => $object_type,
                                            'city'             => $storeInfo->city,
                                            'country_id'       => $storeInfo->country_id,
                                            'base_merchant_id' => $storeInfo->base_merchant_id,
                                            'mall_id'          => $mall_id,
                                            'created_at'       => $dateTime
                                        ];
                                        $gamificationData = (object) [
                                            'object_id' => $stores->store_id,
                                            'object_type' => $object_type,
                                            'object_name' => $baseMerchantName,
                                            'country_id' => $storeInfo->country_id,
                                            'city' => $storeInfo->city,
                                        ];
                                        Event::fire('orbit.follow.postfollow.success', array($user, $gamificationData));
                                    }

                                    // Bulk Insert
                                    if(!empty($dataStoresInsert)) {
                                        $dataInsert = ['bulk_insert' => $dataStoresInsert];

                                        // insert new data
                                        $response = $mongoClient->setFormParam($dataInsert)
                                                                ->setEndPoint('user-follows')
                                                                ->request('POST');
                                    }
                                }
                            }

                        } else {
                            // gtm level
                            $baseStore = BaseStore::select('merchants.country_id', 'base_stores.base_merchant_id', 'base_merchants.name')
                                              ->leftJoin('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                                              ->leftJoin('merchants', 'merchants.merchant_id', '=', 'base_stores.merchant_id')
                                              ->where('base_stores.base_store_id', '=', $object_id)
                                              ->first();

                            $baseMerchantId = $baseStore->base_merchant_id;
                            $baseMerchantName = $baseStore->name;
                            $baseMerchantCountry = $baseStore->country_id;

                            if (is_object($baseStore)) {
                                $stores = Tenant::select('merchants.merchant_id as store_id',
                                                         'merchants.name as store_name',
                                                         DB::raw('parent.merchant_id as mall_id'),
                                                         DB::raw('parent.city as city'),
                                                         DB::raw('parent.country_id as country_id')
                                                        )
                                                ->excludeDeleted('merchants')
                                                ->leftJoin('merchants as parent', 'merchants.parent_id', '=', DB::raw('parent.merchant_id'))
                                                ->where('merchants.name', '=', $baseStore->name)
                                                ->where('merchants.status', '=', 'active')
                                                ->where(DB::raw('parent.status'), '=', 'active')
                                                ->where(DB::raw('parent.country_id'), '=', $baseStore->country_id);

                                if (! empty($city)) {
                                    $stores = $stores->whereIn(DB::raw('parent.city'), $city);
                                }

                                $stores = $stores->get();
                            }

                            if (!empty($stores)) {
                                foreach ($stores as $key => $value) {
                                    $dataStoresSearch = [
                                        'user_id'          => $user->user_id,
                                        'object_id'        => $value->store_id,
                                        'object_type'      => $object_type,
                                        'city'             => $value->city,
                                        'country_id'       => $value->country_id,
                                        'base_merchant_id' => $baseStore->base_merchant_id,
                                        'mall_id'          => $value->mall_id
                                    ];

                                    $existingData = $mongoClient->setQueryString($dataStoresSearch)
                                                                ->setEndPoint('user-follows')
                                                                ->request('GET');

                                    if (count($existingData->data->records) !== 0) {
                                        $existingIds[] = $existingData->data->records[0]->_id;
                                    }

                                    $dataStoresInsert[] = [
                                        'user_id'          => $user->user_id,
                                        'object_id'        => $value->store_id,
                                        'object_type'      => $object_type,
                                        'city'             => $value->city,
                                        'country_id'       => $value->country_id,
                                        'base_merchant_id' => $baseStore->base_merchant_id,
                                        'mall_id'          => $value->mall_id,
                                        'created_at'       => $dateTime
                                    ];

                                    $gamificationData = (object) [
                                        'object_id' => $value->store_id,
                                        'object_type' => $object_type,
                                        'object_name' => $baseMerchantName,
                                        'country_id' => $value->country_id,
                                        'city' => $value->city,
                                    ];
                                    Event::fire('orbit.follow.postfollow.success', array($user, $gamificationData));
                                }
                            }

                            if(!empty($dataStoresInsert)) {
                                $dataInsert = ['bulk_insert' => $dataStoresInsert];
                                // delete existing data
                                if (!empty($existingIds)) {
                                    foreach ($existingIds as $key => $value) {
                                        $delete = $mongoClient->setEndPoint("user-follows/$value")
                                                              ->request('DELETE');
                                    }
                                }
                                // insert new data
                                $response = $mongoClient->setFormParam($dataInsert)
                                                        ->setEndPoint('user-follows')
                                                        ->request('POST');
                            }

                        }

                    }

                    if ($action === 'unfollow')
                    {
                        $baseMerchantId = null;
                        $baseMerchantName = null;
                        $baseMerchantCountry = null;

                        if (!empty($mall_id)) {
                            // mall level
                            // User will follow all store in mall,  when store have more than one store in same mall
                            // Get store name,city and country
                            $storeInfo = Tenant::select('merchants.merchant_id', 'merchants.name', DB::raw('parent.city as city'), DB::raw('parent.country_id as country_id'), 'base_stores.base_merchant_id')
                                                ->leftJoin('merchants as parent', DB::raw('parent.merchant_id'), '=', 'merchants.parent_id')
                                                ->leftJoin('base_stores', 'base_stores.base_store_id', '=', 'merchants.merchant_id')
                                                ->where('merchants.merchant_id', $object_id)
                                                ->where('merchants.parent_id',  $mall_id)
                                                ->excludeDeleted('merchants')
                                                ->first();

                            $baseMerchantId = $storeInfo->base_merchant_id;
                            $baseMerchantName = $storeInfo->name;
                            $baseMerchantCountry = $storeInfo->country_id;

                            $stores = array();
                            if (is_array($object_id) && ! empty($object_id)) {
                                // Get store by multiple object_id
                                $stores = Tenant::select('merchants.merchant_id as store_id')
                                                    ->whereIn('merchant_id', $object_id)
                                                    ->where('parent_id',  $mall_id)
                                                    ->excludeDeleted()
                                                    ->get();
                            } else {
                                // Get store by single object_id
                                $storeInfo = Tenant::select('merchants.name')
                                                    ->where('merchants.merchant_id', $object_id)
                                                    ->where('merchants.parent_id',  $mall_id)
                                                    ->excludeDeleted('merchants')
                                                    ->first();

                                if (! empty($storeInfo)) {
                                    $stores = Tenant::select('merchants.merchant_id as store_id')
                                                        ->where('name', $storeInfo->name)
                                                        ->where('parent_id',  $mall_id)
                                                        ->excludeDeleted()
                                                        ->get();
                                }
                            }

                            $existingIds = array();
                            if (count($stores) > 0) {
                                foreach ($stores as $key => $store) {

                                    $dataStoresSearch = [
                                        'user_id'          => $user->user_id,
                                        'object_id'        => $store->store_id,
                                        'object_type'      => $object_type,
                                        'mall_id'          => $mall_id
                                    ];

                                    $existingData = $mongoClient->setQueryString($dataStoresSearch)
                                                                ->setEndPoint('user-follows')
                                                                ->request('GET');

                                    if (count($existingData->data->records) !== 0) {
                                        $existingIds[] = $existingData->data->records[0]->_id;
                                        $gamificationData = (object) [
                                            'object_id' => $store->store_id,
                                            'object_type' => $object_type,
                                            'object_name' => $baseMerchantName,
                                            'country_id' => $baseMerchantCountry,
                                        ];
                                        Event::fire('orbit.follow.postunfollow.success', array($user, $gamificationData));
                                    }
                                }
                            }

                            if (! empty($existingIds)) {
                                foreach ($existingIds as $key => $value) {
                                    $response = $mongoClient->setEndPoint("user-follows/$value")
                                                          ->request('DELETE');
                                }
                            }

                        } else {
                            $stores = null;
                            // gtm level
                            if (is_array($object_id) && ! empty($object_id)) {
                                $baseStore = BaseStore::select('merchants.country_id', 'base_stores.base_merchant_id', 'base_merchants.name')
                                                  ->leftJoin('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                                                  ->leftJoin('merchants', 'merchants.merchant_id', '=', 'base_stores.merchant_id')
                                                  ->where('base_stores.base_store_id', '=', $object_id[0])
                                                  ->first();

                                // support unfollow using array of merchant_id
                                // case of more than one store in a single mall
                                $stores = Tenant::select('merchants.merchant_id as store_id',
                                                         'merchants.name as store_name',
                                                         DB::raw('parent.merchant_id as mall_id'),
                                                         DB::raw('parent.city as city'),
                                                         DB::raw('parent.country_id as country_id')
                                                        )
                                                ->excludeDeleted('merchants')
                                                ->leftJoin('merchants as parent', 'merchants.parent_id', '=', DB::raw('parent.merchant_id'))
                                                ->where('merchants.status', '=', 'active')
                                                ->where(DB::raw('parent.status'), '=', 'active')
                                                ->whereIn('merchants.merchant_id', $object_id)
                                                ->get();
                            } else {
                                // unfollow using single merchant_id
                                $baseStore = BaseStore::select('merchants.country_id', 'base_stores.base_merchant_id', 'base_merchants.name')
                                                  ->leftJoin('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                                                  ->leftJoin('merchants', 'merchants.merchant_id', '=', 'base_stores.merchant_id')
                                                  ->where('base_stores.base_store_id', '=', $object_id)
                                                  ->first();

                                if (is_object($baseStore)) {
                                    $stores = Tenant::select('merchants.merchant_id as store_id',
                                                             'merchants.name as store_name',
                                                             DB::raw('parent.merchant_id as mall_id'),
                                                             DB::raw('parent.city as city'),
                                                             DB::raw('parent.country_id as country_id')
                                                            )
                                                    ->excludeDeleted('merchants')
                                                    ->leftJoin('merchants as parent', 'merchants.parent_id', '=', DB::raw('parent.merchant_id'))
                                                    ->where('merchants.name', '=', $baseStore->name)
                                                    ->where('merchants.status', '=', 'active')
                                                    ->where(DB::raw('parent.status'), '=', 'active')
                                                    ->where(DB::raw('parent.country_id'), '=', $baseStore->country_id);

                                    if (! empty($city)) {
                                        $stores = $stores->whereIn(DB::raw('parent.city'), $city);
                                    }

                                    $stores = $stores->get();
                                }
                            }

                            $baseMerchantId = $baseStore->base_merchant_id;
                            $baseMerchantName = $baseStore->name;
                            $baseMerchantCountry = $baseStore->country_id;

                            if (!empty($stores))
                            {
                                foreach ($stores as $key => $value)
                                {
                                    $dataStoresSearch = [
                                        'user_id'          => $user->user_id,
                                        'object_id'        => $value->store_id,
                                        'object_type'      => $object_type,
                                        'city'             => $value->city,
                                        'country_id'       => $value->country_id,
                                        'base_merchant_id' => $baseStore->base_merchant_id,
                                        'mall_id'          => $value->mall_id
                                    ];

                                    $existingData = $mongoClient->setQueryString($dataStoresSearch)
                                                                ->setEndPoint('user-follows')
                                                                ->request('GET');

                                    if (count($existingData->data->records) !== 0) {
                                        $existingIds[] = $existingData->data->records[0]->_id;
                                        $gamificationData = (object) [
                                            'object_id' => $value->store_id,
                                            'object_type' => $object_type,
                                            'object_name' => $baseMerchantName,
                                            'country_id' => $value->country_id,
                                            'city' => $value->city,
                                        ];
                                        Event::fire('orbit.follow.postunfollow.success', array($user, $gamificationData));
                                    }
                                }

                                if (!empty($existingIds)) {
                                    foreach ($existingIds as $key => $value) {
                                        $response = $mongoClient->setEndPoint("user-follows/$value")
                                                              ->request('DELETE');
                                    }
                                }
                            }
                        }
                    }
                    break;
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $response->data;

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
            $this->response->data = null;
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
        } catch (\Exception $e) {
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
