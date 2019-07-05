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
    private function getCurrentDate()
    {
        // generate date
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        return $date->toDateTimeString();
    }

    /**
     * test if user followed a brand or mall
     *
     * @param MongoClient mongoClient, instance of mongo client
     * @param string userId, user id of user
     * @param string brandId, user id of user
     */
    private function getFollow($mongoClient, $userId, $objectId, $objectType)
    {
        $queryString = [
            'user_id'     => $userId,
            'object_id'   => $objectId,
            'object_type' => $objectType
        ];

        return $mongoClient->setQueryString($queryString)
                             ->setEndPoint('user-follows')
                             ->request('GET');
    }

    /**
     * get total all stores of brands followed by user
     *
     * @param MongoClient mongoClient, instance of mongo client
     * @param string userId, user id of user
     * @param string brandId, user id of user
     * @return int number of stores of brand followed by user
     */
    private function countAllStoresOfBrandFollowedByUser($mongoClient, $userId, $brandId)
    {
        //TODO: implement API that does count instead of returning all result
        $existingFollowedStores = $mongoClient->setQueryString([
            'user_id'          => $userId,
            'object_type'      => 'store',
            'base_merchant_id' => $brandId,
        ])->setEndPoint('user-follows')->request('GET');

        return count($existingFollowedStores->data->records);
    }

    private function fireEventMall($eventName, $user, $mallId, $mall)
    {
        $gamificationData = (object) [
            'object_id' => $mallId,
            'object_type' => 'mall',
            'object_name' => $mall->name,
            'country_id' => $mall->country_id,
            'city' => $mall->city,
        ];
        Event::fire($eventName, [ $user, $gamificationData ]);
    }

    private function followMall($mongoClient, $user, $mallId, $mall)
    {
        $dataInsert = [
            'user_id'     => $user->user_id,
            'object_id'   => $mallId,
            'object_type' => 'mall',
            'city'        => null,
            'country_id'  => null,
            'mall_id'     => $mallId,
            'created_at'  => $this->getCurrentDate()
        ];

        $response = $mongoClient->setFormParam($dataInsert)
                            ->setEndPoint('user-follows')
                            ->request('POST');

        $this->fireEventMall('orbit.follow.postfollow.success', $user, $mallId, $mall);

        return $response;
    }

    private function unfollowMall($mongoClient, $user, $mallId, $mall, $existingData)
    {
        $id = $existingData->data->records[0]->_id;
        $response = $mongoClient->setEndPoint("user-follows/$id")
                            ->request('DELETE');
        $this->fireEventMall('orbit.follow.postunfollow.success', $user, $mallId, $mall);

        return $response;
    }

    private function followUnfollowMall($mongoClient, $user, $mallId, $action)
    {
        $mall = Mall::excludeDeleted('merchants')
            ->where('merchant_id', '=', $mallId)
            ->first();

        // check already follow or not
        $existingData = $this->getFollow($mongoClient, $user->user_id, $mallId, 'mall');

        if (count($existingData->data->records) === 0) {
            $response = $this->followMall($mongoClient, $user, $mallId, $mall);
        } else {
            $response = $this->unfollowMall($mongoClient, $user, $mallId, $mall, $existingData);
        }

        return $response;
    }

    private function followStoreInMall($mongoClient, $user, $storeId, $mallId)
    {
        // mall level

        // User will follow all store in mall,  when store have more than one store in same mall
        // Get store name,city and country
        $storeInfoModel = Tenant::select('merchants.merchant_id', 'merchants.name', DB::raw('parent.city as city'), DB::raw('parent.country_id as country_id'), 'base_stores.base_merchant_id')
                            ->leftJoin('merchants as parent', DB::raw('parent.merchant_id'), '=', 'merchants.parent_id')
                            ->leftJoin('base_stores', 'base_stores.base_store_id', '=', 'merchants.merchant_id')
                            ->where('merchants.parent_id',  $mallId)
                            ->excludeDeleted('merchants');
        if (is_array($storeId)) {
            $storeInfoModel->whereIn('merchants.merchant_id', $storeId);
        } else {
            $storeInfoModel->where('merchants.merchant_id', $storeId);
        }
        $storeInfo = $storeInfoModel->first();

        $resp = new \StdClass()    ;
        $resp->baseMerchantId = $storeInfo->base_merchant_id;
        $resp->baseMerchantName = $storeInfo->name;
        $resp->baseMerchantCountry = $storeInfo->country_id;
        $resp->response = null;

        $resp->totalPreviouslyFollowed = $this->countAllStoresOfBrandFollowedByUser(
            $mongoClient,
            $user->user_id,
            $resp->baseMerchantId
        );

        if (! empty($storeInfo)) {
            $stores = Tenant::select('name', 'merchant_id as store_id')
                                ->where('name', $storeInfo->name)
                                ->where('parent_id',  $mallId)
                                ->excludeDeleted()
                                ->get();

            if (count($stores) > 0) {
                $dataStoresInsert = array();
                $dateTime = $this->getCurrentDate();
                foreach ($stores as $key => $stores) {
                    $dataStoresInsert[] = [
                        'user_id'          => $user->user_id,
                        'object_id'        => $stores->store_id,
                        'object_type'      => 'store',
                        'city'             => $storeInfo->city,
                        'country_id'       => $storeInfo->country_id,
                        'base_merchant_id' => $storeInfo->base_merchant_id,
                        'mall_id'          => $mallId,
                        'created_at'       => $dateTime
                    ];
                }

                // Bulk Insert
                if(!empty($dataStoresInsert)) {
                    $dataInsert = ['bulk_insert' => $dataStoresInsert];

                    // insert new data
                    $resp->response = $mongoClient->setFormParam($dataInsert)
                                            ->setEndPoint('user-follows')
                                            ->request('POST');
                }
            }
        }

        return $resp;
    }

    private function followStoreInGtm($mongoClient, $user, $storeId, $city, $country)
    {
        // gtm level
        $baseStore = BaseStore::select('merchants.country_id', 'base_stores.base_merchant_id', 'base_merchants.name')
                            ->leftJoin('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'base_stores.merchant_id')
                            ->where('base_stores.base_store_id', '=', $storeId)
                            ->first();

        $resp = new \StdClass();
        $resp->baseMerchantId = $baseStore->base_merchant_id;
        $resp->baseMerchantName = $baseStore->name;
        $resp->baseMerchantCountry = $baseStore->country_id;
        $resp->response = null;

        $resp->totalPreviouslyFollowed = $this->countAllStoresOfBrandFollowedByUser(
            $mongoClient,
            $user->user_id,
            $resp->baseMerchantId
        );

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
            $dateTime = $this->getCurrentDate();
            foreach ($stores as $key => $value) {
                $dataStoresSearch = [
                    'user_id'          => $user->user_id,
                    'object_id'        => $value->store_id,
                    'object_type'      => 'store',
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
                    'object_type'      => 'store',
                    'city'             => $value->city,
                    'country_id'       => $value->country_id,
                    'base_merchant_id' => $baseStore->base_merchant_id,
                    'mall_id'          => $value->mall_id,
                    'created_at'       => $dateTime
                ];

            }
        }

        if(!empty($dataStoresInsert)) {
            $dataInsert = ['bulk_insert' => $dataStoresInsert];
            // delete existing data
            if (!empty($existingIds)) {
                //TODO : implement API that does bulk delete
                foreach ($existingIds as $key => $value) {
                    $delete = $mongoClient->setEndPoint("user-follows/$value")
                                            ->request('DELETE');
                }
            }
            // insert new data
            $resp->response = $mongoClient->setFormParam($dataInsert)
                                    ->setEndPoint('user-follows')
                                    ->request('POST');
        }

        return $resp;
    }

    private function fireEventStore($eventName, $user, $storeId, $resp)
    {
        $gamificationData = (object) [
            'object_id' => $storeId,
            'object_type' => 'store',
            'object_name' => $resp->baseMerchantName,
            'country_id' => $resp->baseMerchantCountry,
            'base_merchant_id' => $resp->baseMerchantId,
            'existing_stores_count' => $resp->totalPreviouslyFollowed,
        ];
        Event::fire($eventName, array($user, $gamificationData));
    }

    private function followStore($mongoClient, $user, $storeId, $mall_id, $city, $country)
    {
        if (!empty($mall_id)) {
            $resp = $this->followStoreInMall($mongoClient, $user, $storeId, $mall_id);

        } else {
            $resp = $this->followStoreInGtm($mongoClient, $user, $storeId, $city, $country);
        }

        $this->fireEventStore('orbit.follow.postfollow.success', $user, $storeId, $resp);

        return $resp->response;
    }

    private function unfollowStoreInMallPage($mongoClient, $user, $storeId, $mall_id)
    {
        // mall level
        // User will follow all store in mall,  when store have more than one store in same mall
        // Get store name,city and country
        $storeInfoModel = Tenant::select(
            'merchants.merchant_id', 'merchants.name', DB::raw('parent.city as city'), DB::raw('parent.country_id as country_id'),
            'base_stores.base_merchant_id')
            ->leftJoin('merchants as parent', DB::raw('parent.merchant_id'), '=', 'merchants.parent_id')
            ->leftJoin('base_stores', 'base_stores.base_store_id', '=', 'merchants.merchant_id')
            ->where('merchants.parent_id',  $mall_id)
            ->excludeDeleted('merchants');

        if (is_array($storeId)) {
            $storeInfoModel->whereIn('merchants.merchant_id', $storeId);
        } else {
            $storeInfoModel->where('merchants.merchant_id', $storeId);
        }
        $storeInfo = $storeInfoModel->first();

        $resp = new \StdClass();
        $resp->baseMerchantId = $storeInfo->base_merchant_id;
        $resp->baseMerchantName = $storeInfo->name;
        $resp->baseMerchantCountry = $storeInfo->country_id;
        $resp->response = null;
        $resp->totalPreviouslyFollowed = $this->countAllStoresOfBrandFollowedByUser(
            $mongoClient,
            $user->user_id,
            $resp->baseMerchantId
        );

        $stores = array();
        if (is_array($storeId) && ! empty($storeId)) {
            // Get store by multiple object_id
            $stores = Tenant::select('merchants.merchant_id as store_id')
                                ->whereIn('merchant_id', $storeId)
                                ->where('parent_id',  $mall_id)
                                ->excludeDeleted()
                                ->get();
        } else {
            // Get store by single object_id
            $storeInfo = Tenant::select('merchants.name')
                                ->where('merchants.merchant_id', $storeId)
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
                    'object_type'      => 'store',
                    'mall_id'          => $mall_id
                ];

                $existingData = $mongoClient->setQueryString($dataStoresSearch)
                                            ->setEndPoint('user-follows')
                                            ->request('GET');

                if (count($existingData->data->records) !== 0) {
                    $existingIds[] = $existingData->data->records[0]->_id;
                }
            }
        }

        if (! empty($existingIds)) {
            foreach ($existingIds as $key => $value) {
                $resp->response = $mongoClient->setEndPoint("user-follows/$value")
                                        ->request('DELETE');
            }
        }

        return $resp;
    }

    private function unfollowStoreInGtm($mongoClient, $user, $storeId, $city)
    {
        $stores = null;

        $baseStoreId = (is_array($storeId) && ! empty($storeId)) ? $storeId[0] : $storeId;
        $baseStore = BaseStore::select('merchants.country_id', 'base_stores.base_merchant_id', 'base_merchants.name')
            ->leftJoin('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'base_stores.merchant_id')
            ->where('base_stores.base_store_id', '=', $baseStoreId)
            ->first();

        if (is_array($storeId) && ! empty($storeId)) {

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
                            ->whereIn('merchants.merchant_id', $storeId)
                            ->get();
        } else {
            // unfollow using single merchant_id

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

        $resp = new \StdClass();
        $resp->baseMerchantId = $baseStore->base_merchant_id;
        $resp->baseMerchantName = $baseStore->name;
        $resp->baseMerchantCountry = $baseStore->country_id;
        $resp->totalPreviouslyFollowed = $this->countAllStoresOfBrandFollowedByUser(
            $mongoClient,
            $user->user_id,
            $resp->baseMerchantId
        );


        if (!empty($stores))
        {
            $existingIds = [];
            foreach ($stores as $key => $value)
            {
                $dataStoresSearch = [
                    'user_id'          => $user->user_id,
                    'object_id'        => $value->store_id,
                    'object_type'      => 'store',
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
            }

            if (!empty($existingIds)) {
                foreach ($existingIds as $key => $value) {
                    $resp->response = $mongoClient->setEndPoint("user-follows/$value")
                                          ->request('DELETE');
                }
            }
        }

        return $resp;
    }

    private function unfollowStore($mongoClient, $user, $storeId, $mall_id, $city, $country)
    {
        if (!empty($mall_id)) {
            $resp = $this->unfollowStoreInMallPage($mongoClient, $user, $storeId, $mall_id);
        } else {
            $resp = $this->unfollowStoreInGtm($mongoClient, $user, $storeId, $city, $country);
        }

        $this->fireEventStore('orbit.follow.postunfollow.success', $user, $storeId, $resp);

        return $resp->response;
    }

    private function followUnfollowStore($mongoClient, $user, $storeId, $mall_id, $city, $country, $action)
    {
        if ($action === 'follow')
        {
            return $this->followStore(
                $mongoClient,
                $user,
                $storeId,
                $mall_id,
                $city,
                $country
            );
        } else
        if ($action === 'unfollow')
        {
            return $this->unfollowStore(
                $mongoClient,
                $user,
                $storeId,
                $mall_id,
                $city,
                $country
            );
        }
    }

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


            switch($object_type) {
                case "mall":
                    $response = $this->followUnfollowMall(
                        $mongoClient,
                        $user,
                        $object_id,
                        $mall_id,
                        $city,
                        $country,
                        $action
                    );
                    break;
                case "store":
                    $response = $this->followUnfollowStore(
                        $mongoClient,
                        $user,
                        $object_id,
                        $mall_id,
                        $city,
                        $country,
                        $action
                    );
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
