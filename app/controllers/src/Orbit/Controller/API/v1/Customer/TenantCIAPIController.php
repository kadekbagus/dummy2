<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Controller for Tenant specific requests for Mobile CI Angular
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
use Tenant;
use Mall;
use App;
use Employee;
use Coupon;
use News;
use Lang;
use User;
use Activity;

class TenantCIAPIController extends BaseAPIController
{
    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = NULL;

    public function getTenantList ()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
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
                    'sortby' => 'in:store_name',
                ),
                array(
                    'sortby.in' => Lang::get('validation.orbit.empty.tenant_ci_sortby'),
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $gender_profile_query = '';
            $age_profile_query = '';

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $age_profile_query = " AND ( (min_value = {$userAge} and max_value = {$userAge} ) or is_all_age = 'Y' ) ";
                } else {
                    if ($userAge >= 55) {
                        $age_profile_query = " AND ( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ";
                    } else {
                        $age_profile_query = " AND ( (min_value <= {$userAge} and max_value >= {$userAge} ) or is_all_age = 'Y' ) ";
                    }
                }
            }
            if ($userGender !== null) {
                $gender_profile_query = " AND ( gender_value = '{$userGender}' OR is_all_gender = 'Y' ) ";
            }

            $mall = Mall::excludeDeleted()->where('merchant_id', $this->mall_id)->first();
            $mallTime = Carbon::now($mall->timezone->timezone_name);
            $redeemToCSFlag = 'false';

            $quoted_mall_id = $this->quoteStr($this->mall_id);

            $tenants = Tenant::
            with(
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
                'media.path as logo',
                'merchant_social_media.social_media_uri as facebook_like_url',
                DB::raw('CASE WHEN news_merch.news_counter > 0 THEN "true" ELSE "false" END as news_flag'),
                DB::raw('CASE WHEN promo_merch.promotion_counter > 0 THEN "true" ELSE "false" END as promotion_flag'),
                DB::raw('CASE WHEN coupon_merch.coupon_counter > 0 THEN "true" ELSE "false" END as coupon_flag')
            )
            ->leftJoin('objects', 'objects.object_id', '=', 'merchants.floor_id')
            ->leftJoin('media', function ($join) {
                $join->on('media.object_id', '=', 'merchants.merchant_id')
                    ->where('media_name_long', '=', 'retailer_logo_orig');
            })
            ->leftJoin('category_merchant', function ($join) {
                $join->on('category_merchant.merchant_id', '=', 'merchants.merchant_id');
            })
            ->leftJoin('categories', function ($join) {
                $join->on('category_merchant.category_id', '=', 'categories.category_id');
            })
            ->leftJoin('merchant_social_media', function ($join) {
                $join->on('merchant_social_media.merchant_id', '=', 'merchants.merchant_id');
            })
            ->leftJoin('social_media', function ($join) {
                $join->on('social_media.social_media_id', '=', 'merchant_social_media.social_media_id');
                $join->where('social_media_code', '=', 'facebook');
            })
            // news badge
            ->leftJoin(DB::raw("(
                    SELECT {$prefix}merchants.merchant_id, count({$prefix}news.news_id) as news_counter
                    from {$prefix}news
                    LEFT JOIN {$prefix}news_merchant on {$prefix}news_merchant.news_id = {$prefix}news.news_id
                    LEFT JOIN {$prefix}merchants on {$prefix}news_merchant.merchant_id = {$prefix}merchants.merchant_id

                    LEFT JOIN {$prefix}campaign_age on {$prefix}campaign_age.campaign_id = {$prefix}news.news_id
                    LEFT JOIN {$prefix}age_ranges on {$prefix}age_ranges.age_range_id = {$prefix}campaign_age.age_range_id

                    LEFT JOIN {$prefix}campaign_gender on {$prefix}campaign_gender.campaign_id = {$prefix}news.news_id

                    WHERE {$prefix}news_merchant.object_type = 'retailer'
                    AND {$prefix}news.object_type = 'news'
                    AND {$prefix}news.status = 'active'
                    AND {$prefix}merchants.status = 'active'
                    AND {$prefix}merchants.parent_id = {$quoted_mall_id}
                    AND '{$mallTime}' >= {$prefix}news.begin_date
                    AND '{$mallTime}' <= {$prefix}news.end_date
                    {$age_profile_query}
                    {$gender_profile_query}
                    GROUP BY {$prefix}merchants.merchant_id
            ) as news_merch"), DB::raw('news_merch.merchant_id'), '=', 'merchants.merchant_id')
            // promotion badge
            ->leftJoin(DB::raw("(
                    SELECT {$prefix}merchants.merchant_id, count({$prefix}news.news_id) as promotion_counter
                    from {$prefix}news
                    LEFT JOIN {$prefix}news_merchant on {$prefix}news_merchant.news_id = {$prefix}news.news_id
                    LEFT JOIN {$prefix}merchants on {$prefix}news_merchant.merchant_id = {$prefix}merchants.merchant_id

                    LEFT JOIN {$prefix}campaign_age on {$prefix}campaign_age.campaign_id = {$prefix}news.news_id
                    LEFT JOIN {$prefix}age_ranges on {$prefix}age_ranges.age_range_id = {$prefix}campaign_age.age_range_id

                    LEFT JOIN {$prefix}campaign_gender on {$prefix}campaign_gender.campaign_id = {$prefix}news.news_id

                    WHERE {$prefix}news_merchant.object_type = 'retailer'
                    AND {$prefix}news.object_type = 'promotion'
                    AND {$prefix}news.status = 'active'
                    AND {$prefix}merchants.status = 'active'
                    AND {$prefix}merchants.parent_id = {$quoted_mall_id}
                    AND '{$mallTime}' >= {$prefix}news.begin_date
                    AND '{$mallTime}' <= {$prefix}news.end_date
                    {$age_profile_query}
                    {$gender_profile_query}
                    GROUP BY {$prefix}merchants.merchant_id
            ) as promo_merch"), DB::raw('promo_merch.merchant_id'), '=', 'merchants.merchant_id')
            // coupon badge
            ->leftJoin(DB::raw("(
                    SELECT {$prefix}merchants.merchant_id, count({$prefix}promotions.promotion_id) as coupon_counter
                    from {$prefix}promotions
                    LEFT JOIN {$prefix}promotion_retailer on {$prefix}promotion_retailer.promotion_id = {$prefix}promotions.promotion_id
                    LEFT JOIN {$prefix}merchants on {$prefix}promotion_retailer.retailer_id = {$prefix}merchants.merchant_id

                    JOIN {$prefix}issued_coupons ON {$prefix}issued_coupons.promotion_id = {$prefix}promotions.promotion_id

                    WHERE {$prefix}promotion_retailer.object_type = 'tenant'
                    AND {$prefix}promotions.is_coupon = 'Y'
                    AND {$prefix}promotions.status = 'active'
                    AND {$prefix}merchants.parent_id = {$quoted_mall_id}
                    AND {$prefix}merchants.status = 'active'
                    AND {$prefix}promotions.begin_date <= '{$mallTime}'
                    AND {$prefix}promotions.end_date >= '{$mallTime}'
                    AND {$prefix}issued_coupons.status = 'active'
                    AND {$prefix}promotions.coupon_validity_in_date >= '{$mallTime}'
                    AND {$prefix}issued_coupons.user_id = '{$user->user_id}'
                    GROUP BY {$prefix}merchants.merchant_id
            ) as coupon_merch"), DB::raw('coupon_merch.merchant_id'), '=', 'merchants.merchant_id')
            ->active('merchants')
            ->where('parent_id', $this->mall_id);

            OrbitInput::get('news_id', function ($news_id) use ($tenants) {
                $tenants->whereHas('news', function ($q) use ($news_id) {
                    $q->where('news.news_id', $news_id);
                    $q->where('news.status', 'active');
                });
            });

            OrbitInput::get('promotion_id', function ($promotion_id) use ($tenants) {
                $tenants->whereHas('newsPromotions', function ($q) use ($promotion_id) {
                    $q->where('news.news_id', $promotion_id);
                    $q->where('news.status', 'active');
                });
            });

            OrbitInput::get('coupon_id', function ($coupon_id) use ($tenants) {
                $tenants->whereHas('coupons', function ($q) use ($coupon_id) {
                    $q->where('promotions.promotion_id', $coupon_id);
                    $q->where('promotions.status', 'active');
                });
            });

            OrbitInput::get('coupon_redeem_id', function ($coupon_id) use ($tenants, &$redeemToCSFlag) {
                $tenants->whereHas('redeemCoupons', function ($q) use ($coupon_id) {
                    $q->where('promotions.promotion_id', $coupon_id);
                    $q->where('promotions.status', 'active');
                })
                ->where('merchants.masterbox_number', '<>', '')
                ->whereNotNull('merchants.masterbox_number');

                $coupon = Coupon::active()->where('promotion_id', $coupon_id)->first();

                if (is_object($coupon)) {
                    if ($coupon->is_all_employee === 'Y') {
                        $redeemToCSFlag = 'true';
                    } else {
                        $employee = Employee::byCouponId($coupon_id)
                            ->whereHas('retailers', function ($q) {
                                $q->where('merchants.merchant_id', $this->mall_id);
                            })
                            ->get();
                        if (count($employee) > 0) {
                            $redeemToCSFlag = 'true';
                        }
                    }
                }
            });

            OrbitInput::get('category_id', function ($category_id) use ($tenants) {
                $tenants->whereHas('categories', function ($q) use ($category_id) {
                    $q->where('category_merchant.category_id', $category_id);
                });
            });

            OrbitInput::get('floor', function ($floor) use ($tenants) {
                $tenants->where('merchants.floor', $floor);
            });

            OrbitInput::get(
                'keyword',
                function ($keyword) use ($tenants, $mall) {
                    $keyword_like = "%$keyword%";
                    $tenants->leftJoin('keyword_object', function($join) {
                        $join->on('merchants.merchant_id', '=', 'keyword_object.object_id');
                        $join->where('keyword_object.object_type', '=', 'tenant');
                    })
                    ->leftJoin('keywords', function($join) use ($mall) {
                        $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                        $join->where('keywords.merchant_id', '=', $mall->merchant_id);
                    })
                    ->where(
                        function ($q) use ($keyword_like, $keyword) {
                            $q->where('merchants.name', 'like', $keyword_like)
                                ->orWhere('keyword', '=', $keyword)
                                ->orWhere('merchants.description', 'like', $keyword_like);
                            $q->orWhereHas('categories', function($q2) use ($keyword_like) {
                                $q2->where('category_name', 'like', $keyword_like);
                            });
                            // todo: insert tenant description translation here
                        }
                    );
                }
            );

            OrbitInput::get('object_type', function ($object_type) use ($tenants) {
                $tenants->where('merchants.object_type', $object_type);
            });

            $this->viewItemUserUpdate('tenant', $user, $mall);

            $tenants->groupBy('merchants.merchant_id');

            $_tenants = clone($tenants);

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
            $tenants->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $tenants)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $tenants->skip($skip);

            $sortBy = '';
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'store_name' => 'merchants.name',
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
                $tenants->orderBy($sortBy, $sortMode);
            }

            $tenants = $tenants->get();

            $data = new \stdclass();
            $data->records = $tenants;
            $data->returned_records = count($tenants);
            $data->total_records = RecordCounter::create($_tenants)->count();
            $data->extras = new \stdclass();
            $data->extras->redeem_to_cs_flag = $redeemToCSFlag;

            if (empty($skip)) {
                if (! empty(OrbitInput::get('promotion_id'))) {
                    $activityNotes = sprintf('Page viewed: Promotion Tenants List Page, promotion ID: %s', OrbitInput::get('promotion_id'));
                    $activity->setUser($user)
                        ->setActivityName('view_retailer')
                        ->setActivityNameLong('View Promotion Tenant List')
                        ->setObject(null)
                        ->setModuleName('Tenant')
                        ->setNotes($activityNotes)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();
                }

                if (! empty(OrbitInput::get('coupon_id'))) {
                    $activityNotes = sprintf('Page viewed: Coupon Tenants List Page, promotion ID: %s', OrbitInput::get('coupon_id'));
                    $activity->setUser($user)
                        ->setActivityName('view_retailer')
                        ->setActivityNameLong('View Coupon Tenant List')
                        ->setObject(null)
                        ->setModuleName('Tenant')
                        ->setNotes($activityNotes)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();
                }

                if (! empty(OrbitInput::get('coupon_redeem_id'))) {
                    $activityNotes = sprintf('Page viewed: Coupon Redemption Tenants List Page, promotion ID: %s', OrbitInput::get('coupon_redeem_id'));
                    $activity->setUser($user)
                        ->setActivityName('view_retailer')
                        ->setActivityNameLong('View Coupon Redemption Places')
                        ->setObject(null)
                        ->setModuleName('Tenant')
                        ->setNotes($activityNotes)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();
                }

                if (! empty(OrbitInput::get('news_id'))) {
                    $activityNotes = sprintf('Page viewed: News Tenants List Page, news ID: %s', OrbitInput::get('news_id'));
                    $activity->setUser($user)
                        ->setActivityName('view_retailer')
                        ->setActivityNameLong('View News Tenant List')
                        ->setObject(null)
                        ->setModuleName('Tenant')
                        ->setNotes($activityNotes)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();
                }

                if (empty(OrbitInput::get('promotion_id'))
                    && empty(OrbitInput::get('news_id'))
                    && empty(OrbitInput::get('coupon_id'))
                    && empty(OrbitInput::get('coupon_redeem_id'))
                ) {
                    $activityNotes = sprintf('Page viewed: Tenant Listing Page');
                    $activity->setUser($user)
                        ->setActivityName('view_retailer')
                        ->setActivityNameLong('View Tenant List')
                        ->setObject(null)
                        ->setModuleName('Tenant')
                        ->setNotes($activityNotes)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();
                }
            }

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

            $activity->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant Failed')
                ->setObject(null)
                ->setModuleName('Tenant')
                ->setNotes('Failed to view: Tenant Listing Page. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant Failed')
                ->setObject(null)
                ->setModuleName('Tenant')
                ->setNotes('Failed to view: Tenant Listing Page. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
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

            $activity->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant Failed')
                ->setObject(null)
                ->setModuleName('Tenant')
                ->setNotes('Failed to view: Tenant Listing Page. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activity->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant Failed')
                ->setObject(null)
                ->setModuleName('Tenant')
                ->setNotes('Failed to view: Tenant Listing Page. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        }

        return $this->render($httpCode);
    }

    public function getTenantItem ()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $tenantId = null;
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
            $store_id = OrbitInput::get('store_id', NULL);

            $promo_id = trim(OrbitInput::get('pid'));
            $news_id = trim(OrbitInput::get('nid'));

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                    'store_id' => $store_id,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                    'store_id' => 'required|orbit.empty.tenant',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $gender_profile_query = '';
            $age_profile_query = '';

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $age_profile_query = " ( (min_value = {$userAge} and max_value = {$userAge} ) or is_all_age = 'Y' ) ";
                } else {
                    if ($userAge >= 55) {
                        $age_profile_query = " ( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ";
                    } else {
                        $age_profile_query = " ( (min_value <= {$userAge} and max_value >= {$userAge} ) or is_all_age = 'Y' ) ";
                    }
                }
            }
            if ($userGender !== null) {
                $gender_profile_query = " ( gender_value = '{$userGender}' OR is_all_gender = 'Y' ) ";
            }

            $mall = Mall::excludeDeleted()->where('merchant_id', $this->mall_id)->first();
            $mallTime = Carbon::now($mall->timezone->timezone_name);
            $prefix = DB::getTablePrefix();

            $tenant = Tenant::with(
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
                'objects.object_name as floor',
                'merchants.unit',
                DB::raw("(CASE WHEN unit = '' THEN {$prefix}objects.object_name ELSE CONCAT({$prefix}objects.object_name, \" unit \", unit) END) AS location"),
                'merchants.url',
                'merchants.phone',
                'merchant_social_media.social_media_uri as facebook_like_url'
            )
            ->leftJoin('objects', 'objects.object_id', '=', 'merchants.floor_id')
            ->leftJoin('merchant_social_media', function ($join) {
                $join->on('merchant_social_media.merchant_id', '=', 'merchants.merchant_id');
            })
            ->leftJoin('social_media', function ($join) {
                $join->on('social_media.social_media_id', '=', 'merchant_social_media.social_media_id');
                $join->where('social_media_code', '=', 'facebook');
            })
            ->active('merchants')
            ->where('parent_id', $this->mall_id)
            ->where('merchants.merchant_id', $store_id)
            ->first();

            $news_flag = News::select('news.news_id')
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where('merchants.merchant_id', $store_id)
                ->where('news.status', '=', 'active')
                ->where('news.object_type', 'news')
                ->where('news.begin_date', '<=', $mallTime)
                ->where('news.end_date', '>=', $mallTime);
            if (! empty($gender_profile_query)) {
                $news_flag->whereRaw($gender_profile_query);
            }
            if (! empty($age_profile_query)) {
                $news_flag->whereRaw($age_profile_query);
            }
            $news_flag->groupBy('news.news_id');
            // filter by news id
            $news_id = OrbitInput::get('news_id');
            OrbitInput::get('news_id', function ($news_id) use ($news_flag) {
                $news_flag->where('news.news_id', $news_id);
            });
            $news_flag = RecordCounter::create($news_flag)->count();

            $promotion_flag = News::select('news.news_id')
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where('merchants.merchant_id', $store_id)
                ->where('news.status', '=', 'active')
                ->where('news.object_type', 'promotion')
                ->where('news.begin_date', '<=', $mallTime)
                ->where('news.end_date', '>=', $mallTime);
            if (! empty($gender_profile_query)) {
                $promotion_flag->whereRaw($gender_profile_query);
            }
            if (! empty($age_profile_query)) {
                $promotion_flag->whereRaw($age_profile_query);
            }
            // filter by promotion id
            $promotion_id = OrbitInput::get('promotion_id');
            OrbitInput::get('promotion_id', function ($promotion_id) use ($promotion_flag) {
                $promotion_flag->where('news.news_id', $promotion_id);
            });
            $promotion_flag->groupBy('news.news_id');
            $promotion_flag = RecordCounter::create($promotion_flag)->count();

            $coupon_flag = Coupon::select('promotions.promotion_id')
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                ->join('issued_coupons', function($join) {
                    $join->on('promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                        ->where('issued_coupons.status', '=', 'active');
                })
                ->where('merchants.merchant_id', $store_id)
                ->where('promotions.status', '=', 'active')
                ->where('promotions.coupon_validity_in_date', '>=', $mallTime)
                ->where('issued_coupons.user_id', $user->user_id);
            if (! empty($gender_profile_query)) {
                $coupon_flag->whereRaw($gender_profile_query);
            }
            if (! empty($age_profile_query)) {
                $coupon_flag->whereRaw($age_profile_query);
            }
            // filter by coupon id
            $coupon_id = OrbitInput::get('coupon_id');
            OrbitInput::get('coupon_id', function ($coupon_id) use ($coupon_flag) {
                $coupon_flag->where('promotions.promotion_id', $coupon_id);
            });
            $coupon_flag->groupBy('promotions.promotion_id');
            $coupon_flag = RecordCounter::create($coupon_flag)->count();

            $tenant->news_flag = $news_flag > 0 ? 'true' : 'false';
            $tenant->promotion_flag = $promotion_flag > 0 ? 'true' : 'false';
            $tenant->coupon_flag = $coupon_flag > 0 ? 'true' : 'false';

            // default data without filter data id
            $this->response->data = $tenant;
            $tenantId = $tenant->merchant_id;

            // overide data if filter doesn't exist
            if (!is_null($news_id) && $tenant->news_flag === 'false') {
                $this->response->data = [];
            }

            if (!is_null($promotion_id) && $tenant->promotion_flag === 'false') {
                $this->response->data = [];
            }

            if (!is_null($coupon_id) && $tenant->coupon_flag === 'false') {
                $this->response->data = [];
            }

            if (! empty($promo_id)) {
                $activityNotes = sprintf('Page viewed: Tenant Detail Page from Promotion, tenant ID: ' . $tenant->merchant_id . ', promotion ID: '. $promo_id);
                $activity->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Tenant Detail')
                    ->setObject($tenant)
                    ->setModuleName('Tenant')
                    ->setLocation($mall)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            if (! empty($news_id)) {
                $activityNotes = sprintf('Page viewed: Tenant Detail Page from News, tenant ID: ' . $tenant->merchant_id . ', news ID: '. $news_id);
                $activity->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Tenant Detail')
                    ->setObject($tenant)
                    ->setModuleName('Tenant')
                    ->setLocation($mall)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            if (empty($promo_id) && empty($news_id)) {
                $activityNotes = sprintf('Page viewed: Tenant Detail Page, tenant ID: ' . $tenant->merchant_id);
                $activity->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Tenant Detail')
                    ->setObject($tenant)
                    ->setModuleName('Tenant')
                    ->setLocation($mall)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant Detail Failed')
                ->setObject(null)
                ->setModuleName('Tenant')
                ->setNotes('Failed to view: Tenant Detail Page. Tenant ID: ' . $tenantId . '. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant Detail Failed')
                ->setObject(null)
                ->setModuleName('Tenant')
                ->setNotes('Failed to view: Tenant Detail Page. Tenant ID: ' . $tenantId . '. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
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

            $activity->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant Detail Failed')
                ->setObject(null)
                ->setModuleName('Tenant')
                ->setNotes('Failed to view: Tenant Detail Page. Tenant ID: ' . $tenantId . '. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activity->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant Detail Failed')
                ->setObject(null)
                ->setModuleName('Tenant')
                ->setNotes('Failed to view: Tenant Detail Page. Tenant ID: ' . $tenantId . '. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
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
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $tenant = Tenant::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.empty.tenant', $tenant);

            return TRUE;
        });
    }

}
