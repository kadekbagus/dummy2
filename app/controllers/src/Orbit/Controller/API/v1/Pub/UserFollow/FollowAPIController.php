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

                    if (count($existingData->data->records) === 0) {
                        // follow
                        $city = null;
                        $country_id = null;

                        $mall = Mall::excludeDeleted('merchants')
                                     ->where('merchant_id', '=', $object_id)
                                     ->first();

                        if (is_object($mall)) {
                            $city = $mall->city;
                            $country_id = $mall->country_id;
                        }

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

                    } else {
                        // unfollow
                        $id = $existingData->data->records[0]->_id;
                        $response = $mongoClient->setEndPoint("user-follows/$id")
                                                ->request('DELETE');
                    }

                    break;
                case "store":
                    if ($action === 'follow')
                    {
                        if (!empty($mall_id)) {
                            // mall level
                            $baseStore = BaseStore::select('merchants.city', 'merchants.country_id', 'base_merchants.base_merchant_id')
                                              ->leftJoin('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                                              ->leftJoin('merchants', 'merchants.merchant_id', '=', 'base_stores.merchant_id')
                                              ->where('base_stores.base_store_id', '=', $object_id)
                                              ->where('base_stores.merchant_id', '=', $mall_id)
                                              ->first();

                            if (is_object($baseStore)) {
                                $dataInsert = [
                                    'user_id'          => $user->user_id,
                                    'object_id'        => $object_id,
                                    'object_type'      => $object_type,
                                    'city'             => $baseStore->city,
                                    'country_id'       => $baseStore->country_id,
                                    'base_merchant_id' => $baseStore->base_merchant_id,
                                    'mall_id'          => $mall_id,
                                    'created_at'       => $dateTime
                                ];

                                $response = $mongoClient->setFormParam($dataInsert)
                                                    ->setEndPoint('user-follows')
                                                    ->request('POST');
                            }

                        } else {
                            // gtm level
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
                        if (!empty($mall_id)) {
                            // mall level
                            foreach ((array) $object_id as $objectId) {
                                $queryString = [
                                    'user_id'     => $user->user_id,
                                    'object_id'   => $objectId,
                                    'object_type' => $object_type
                                ];

                                $existingData = $mongoClient->setQueryString($queryString)
                                                     ->setEndPoint('user-follows')
                                                     ->request('GET');

                                if (count($existingData->data->records) !== 0) {
                                    $id = $existingData->data->records[0]->_id;
                                    $response = $mongoClient->setEndPoint("user-follows/$id")
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