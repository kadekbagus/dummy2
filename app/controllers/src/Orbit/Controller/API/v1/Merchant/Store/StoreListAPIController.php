<?php namespace Orbit\Controller\API\v1\Merchant\Store;

use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use BaseMerchant;
use BaseStore;
use Validator;
use Lang;
use DB;
use Config;
use stdClass;
use \Exception;
use Orbit\Controller\API\v1\Merchant\Store\StoreHelper;

class StoreListAPIController extends ControllerAPI
{
    protected $storeViewRoles = ['super admin', 'merchant database admin'];
    protected $returnBuilder = FALSE;
    protected $useChunk = FALSE;

    /**
     * GET - get store
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchStore()
    {
        $store = NULL;
        $user = NULL;
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->storeViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $sort_by = OrbitInput::get('sortby', 'merchant');
            $sort_mode = OrbitInput::get('sortmode','asc');

            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            $validator = Validator::make(
                array(
                    'sortby'   => $sort_by,
                ),
                array(
                    'sortby'   => 'in:merchant,location,created_date,floor,status',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            $store = BaseStore::excludeDeleted('base_stores')
                            ->select('base_merchants.base_merchant_id', 'base_merchants.country_id', 'countries.name as country_name',
                                DB::raw("{$prefix}base_merchants.name AS merchant"),
                                'base_stores.base_store_id',
                                DB::raw("{$prefix}merchants.merchant_id AS mall_id"),
                                DB::raw("{$prefix}merchants.name AS location"),
                                'base_stores.floor_id',
                                DB::raw("{$prefix}objects.object_name AS floor"),
                                'base_stores.unit', 'base_stores.phone',
                                'base_stores.verification_number',
                                'base_stores.is_payment_acquire',
                                'base_stores.status',
                                'base_stores.created_at',
                                'base_stores.url',
                                'base_stores.facebook_url',
                                'base_stores.instagram_url',
                                'base_stores.twitter_url',
                                'base_stores.youtube_url',
                                'base_stores.line_url',
                                'base_stores.video_id_1',
                                'base_stores.video_id_2',
                                'base_stores.video_id_3',
                                'base_stores.video_id_4',
                                'base_stores.video_id_5',
                                'base_stores.video_id_6',
                                'base_stores.description',
                                'base_stores.custom_title',
                                'base_stores.disable_ads',
                                'base_stores.disable_ymal',
                                'base_merchants.mobile_default_language'
                                )
                            ->with('baseStoreTranslation','supportedLanguage','mediaBanner')
                            ->join('base_merchants', 'base_stores.base_merchant_id', '=', 'base_merchants.base_merchant_id')
                            ->leftJoin('objects', 'base_stores.floor_id', '=', 'objects.object_id')
                            ->leftJoin('merchants', 'base_stores.merchant_id', '=', 'merchants.merchant_id')
                            ->leftJoin('countries', 'base_merchants.country_id', '=', 'countries.country_id')
                            ->groupBy('base_stores.base_store_id')
                            ;

            // Filter store by merchant name
            OrbitInput::get('merchant_name_like', function($merchant_name) use ($store)
            {
                $store->where('base_merchants.name', 'like', "%$merchant_name%");
            });

            // Filter store by location
            OrbitInput::get('location_name_like', function($location_name) use ($store)
            {
                $store->where('merchants.name', 'like', "%$location_name%");
            });

            // Filter store by base_merchant_id
            OrbitInput::get('base_merchant_id', function($base_merchant_id) use ($store)
            {
                $store->where('base_stores.base_merchant_id', $base_merchant_id);
            });

            // Filter store by base_store_id
            OrbitInput::get('base_store_id', function($base_store_id) use ($store)
            {
                $store->where('base_stores.base_store_id', $base_store_id);
            });

            // Filter store by country
            OrbitInput::get('country_id', function($country_id) use ($store)
            {
                $store->where('base_merchants.country_id', $country_id);
            });

            // Filter store by country
            OrbitInput::get('status', function($status) use ($store)
            {
                $store->where('base_stores.status', 'like', $status);
            });


            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($store) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'media') {
                        $store->with('media');
                    } elseif ($relation === 'mediaOrig') {
                        $store->with('mediaOrig');
                    } elseif ($relation === 'mediaCroppedDefault') {
                        $store->with('mediaCroppedDefault');
                    } elseif ($relation === 'mediaResizedDefault') {
                        $store->with('mediaResizedDefault');
                    } elseif ($relation === 'mediaImage') {
                        $store->with('mediaImage');
                    } elseif ($relation === 'mediaImageOrig') {
                        $store->with('mediaImageOrig');
                    } elseif ($relation === 'mediaImageCroppedDefault') {
                        $store->with('mediaImageCroppedDefault');
                    } elseif ($relation === 'mediaMap') {
                        $store->with('mediaMap');
                    } elseif ($relation === 'mediaMapOrig') {
                        $store->with('mediaMapOrig');
                    } elseif ($relation === 'mediaImageGrab') {
                        $store->with('mediaImageGrab');
                    } elseif ($relation === 'mediaImageGrabOrig') {
                        $store->with('mediaImageGrabOrig');
                    } elseif ($relation === 'mediaImageGrabCroppedDefault') {
                        $store->with('mediaImageGrabCroppedDefault');
                    }
                }
            });

            $_store = clone $store;
            $_storeActiveInactive = clone $store;

            $sortByMapping = array(
                'merchant'     => 'base_merchants.name',
                'location'     => 'merchants.name',
                'created_date' => 'base_merchants.created_at',
                'floor'        => 'floor',
                'status'       => 'base_stores.status',
            );

            $sort_by = $sortByMapping[$sort_by];

            OrbitInput::get('sortmode', function($_sortMode) use (&$sort_mode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sort_mode = 'desc';
                }
            });

            $store = $store->orderBy($sort_by, $sort_mode)
                           ->orderBy('location', 'asc');

            if (! $this->returnBuilder) {
                $take = PaginationNumber::parseTakeFromGet('retailer');
                $store->take($take);

                $skip = PaginationNumber::parseSkipFromGet();
                $store->skip($skip);
            }

            if ($this->useChunk) {
                $storeList = [];
                $store->chunk(500, function($chunks) use(&$storeList) {
                    foreach($chunks as $chunk) {
                        $storeList[] = $chunk;
                    }
                });
            } else {
                $store->with('bank', 'objectContact', 'financialContactDetail', 'paymentProvider', 'productTags');
                $storeList = $store->get();
            }

            $count = RecordCounter::create($_store)->count();

            // Get total active inactive stores
            $totalActiveStore = 0;
            $totalInactiveStore = 0;

            if ($count > 0) {
                $sql = $_storeActiveInactive->toSql();
                foreach($_storeActiveInactive->getBindings() as $binding)
                {
                 // DB::connection()->getPdo()->quote()
                  $value = is_numeric($binding) ? $binding : DB::connection()->getPdo()->quote($binding);
                  $sql = preg_replace('/\?/', $value, $sql, 1);
                }

                $totalActiveInactiveStore = DB::table(DB::raw('(' . $sql . ')  as tbl'))
                                                        ->select(DB::raw("count(tbl.base_merchant_id) as total "), DB::raw('tbl.status'))
                                                        ->groupBy(DB::raw('tbl.status'))
                                                        ->limit(15)
                                                        ->get();

                if (count($totalActiveInactiveStore) > 0) {
                    foreach ($totalActiveInactiveStore as $key => $value) {
                        if ($value->status == 'active') {
                            $totalActiveStore = $value->total;
                        } elseif ($value->status == 'inactive') {
                            $totalInactiveStore = $value->total;
                        }
                    }
                }
            }

            // for store csv report (MDMStorePrinterController)
            if ($this->returnBuilder && $this->useChunk) {
                return ['stores' => $storeList,
                    'count' => $count,
                    'active_store' => $totalActiveStore,
                    'inactive_store' => $totalInactiveStore];
            }

            if ($this->returnBuilder) {
                return ['builder' => $store,
                        'count' => $count,
                        'active_store' => $totalActiveStore,
                        'inactive_store' => $totalInactiveStore];
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($storeList);
            $this->response->data->total_active_stores = $totalActiveStore;
            $this->response->data->total_inactive_stores = $totalInactiveStore;
            $this->response->data->records = $storeList;

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

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }

    public function setUseChunk($bool)
    {
        $this->useChunk = $bool;

        return $this;
    }
}
