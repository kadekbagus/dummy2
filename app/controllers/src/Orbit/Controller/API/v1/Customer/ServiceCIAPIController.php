<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Firmansyah <firmansyah@dominopos.com>
 * @desc Controller for Service specific requests for Mobile CI Angular
 */
use Orbit\Controller\API\v1\Customer\BaseAPIController;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use TenantStoreAndService;
use Mall;
use App;
use Employee;
use Lang;
use User;

class ServiceCIAPIController extends BaseAPIController
{

    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = NULL;

    public function getServiceList()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->validRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->mall_id = OrbitInput::get('mall_id', NULL);
            $sort_by = OrbitInput::get('sortby');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                    'sort_by' => $sort_by,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                    'sortby' => 'in:service_name',
                ),
                array(
                    'sortby.in' => Lang::get('validation.orbit.empty.service_ci_sortby'),
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // temporary parameter, should be removed when user authentication is present
            OrbitInput::get('user_email', function($user_email) use(&$user) {
                $user = User::excludeDeleted()
                    ->where('user_email', $user_email)
                    ->first();

                if (! is_object($user)) {
                    $errorMessage = 'User with given email not found.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            });

            $prefix = DB::getTablePrefix();

            $mall = Mall::excludeDeleted()->where('merchant_id', $this->mall_id)->first();
            $mallTime = Carbon::now($mall->timezone->timezone_name);
            $redeemToCSFlag = 'false';

            $quoted_mall_id = $this->quoteStr($this->mall_id);

            $service = TenantStoreAndService::where('merchants.object_type', 'service')
            ->with(
            [
                'categories' => function($q) {
                    $q->select('category_name');
                    $q->where('categories.status', 'active');
                    $q->orderBy('category_name', 'asc');
                }
            ])
            ->select(
                'merchants.merchant_id',
                'name',
                'objects.object_name as floor',
                'unit',
                DB::raw("(CASE WHEN unit = '' THEN {$prefix}objects.object_name ELSE CONCAT({$prefix}objects.object_name, \" unit \", unit) END) AS location"),
                'media.path as logo'
            )
            ->leftJoin('objects', 'objects.object_id', '=', 'merchants.floor_id')
            ->leftJoin('media', function ($join) {
                $join->on('media.object_id', '=', 'merchants.merchant_id')
                    ->where('media_name_long', '=', 'service_logo_orig');
            })
            ->leftJoin('category_merchant', function ($join) {
                $join->on('category_merchant.merchant_id', '=', 'merchants.merchant_id');
            })
            ->leftJoin('categories', function ($join) {
                $join->on('category_merchant.category_id', '=', 'categories.category_id');
            })
            ->active('merchants')
            ->where('parent_id', $this->mall_id);

            OrbitInput::get('category_id', function ($category_id) use ($service) {
                $service->whereHas('categories', function ($q) use ($category_id) {
                    $q->where('category_merchant.category_id', $category_id);
                });
            });

            OrbitInput::get('floor', function ($floor) use ($service) {
                $service->where('merchants.floor', $floor);
            });

            OrbitInput::get(
                'keyword',
                function ($keyword) use ($service, $mall) {
                    $keyword_like = "%$keyword%";
                    $service->leftJoin('keyword_object', function($join) {
                        $join->on('merchants.merchant_id', '=', 'keyword_object.object_id');
                        $join->where('keyword_object.object_type', '=', 'service');
                    })
                    ->leftJoin('keywords', function($join) use ($mall) {
                        $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                        $join->where('keywords.merchant_id', '=', $mall->merchant_id);
                    })
                    ->where(
                        function ($q) use ($keyword_like, $keyword) {
                            $q->where('merchants.name', 'like', $keyword_like)
                                ->orWhere('keyword', '=', $keyword);
                            $q->orWhereHas('categories', function($q2) use ($keyword_like) {
                                $q2->where('category_name', 'like', $keyword_like);
                            });
                            // todo: insert tenant description translation here
                        }
                    );
                }
            );

            OrbitInput::get('object_type', function ($object_type) use ($service) {
                $service->where('merchants.object_type', $object_type);
            });

            $service->groupBy('merchants.merchant_id');

            $_service = clone($service);

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.retailer.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.retailer.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $service->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $service)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $service->skip($skip);

            $sortBy = '';
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'service_name' => 'merchants.name',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            if (! empty($sortBy)) {
                $service->orderBy($sortBy, $sortMode);
            }

            $service = $service->get();

            $data = new \stdclass();
            $data->records = $service;
            $data->returned_records = count($service);
            $data->total_records = RecordCounter::create($_service)->count();
            $data->extras = new \stdclass();
            $data->extras->redeem_to_cs_flag = $redeemToCSFlag;

            $this->response->data = $data;
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

    public function getServiceItem()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->validRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->mall_id = OrbitInput::get('mall_id', NULL);
            $service_id = OrbitInput::get('service_id', NULL);

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                    'service_id' => $service_id,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                    'service_id' => 'required|orbit.empty.service',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // temporary parameter, should be removed when user authentication is present
            OrbitInput::get('user_email', function($user_email) use(&$user) {
                $user = User::excludeDeleted()
                    ->where('user_email', $user_email)
                    ->first();

                if (! is_object($user)) {
                    $errorMessage = 'User with given email not found.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            });

            $mall = Mall::excludeDeleted()->where('merchant_id', $this->mall_id)->first();
            $mallTime = Carbon::now($mall->timezone->timezone_name);

            $service = TenantStoreAndService::with(
                [
                    // added foreign key(object_id) to make this work
                    'mediaLogoOrig' => function($q) {
                        $q->select('object_id', 'path', 'media_name_long');
                    },
                    'mediaMapOrig' => function($q) {
                        $q->select('object_id', 'path', 'media_name_long');
                    },
                    'mediaImageOrig' => function($q) {
                        $q->select('object_id', 'path', 'media_name_long');
                    },
                    'categories' => function($q) {
                        $q->select('categories.category_id', 'category_name');
                        $q->where('categories.status', 'active');
                        $q->orderBy('category_name', 'asc');
                    }
                ])
            ->select(
                'merchants.merchant_id',
                'merchants.name',
                'merchants.description',
                'merchants.floor',
                'merchants.unit'
            )
            ->active('merchants')
            ->where('parent_id', $this->mall_id)
            ->where('merchants.merchant_id', $service_id)
            ->first();


            // default data without filter data id
            $this->response->data = $service;

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

    protected function registerCustomValidation()
    {
        // Check the existance of merchant id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

        // Check the existance of store id
        Validator::extend('orbit.empty.service', function ($attribute, $value, $parameters) {
            $service = TenantStoreAndService::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->where('object_type', 'service')
                        ->first();

            if (empty($service)) {
                return FALSE;
            }

            App::instance('orbit.empty.service', $service);

            return TRUE;
        });

    }

}
