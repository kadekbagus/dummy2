<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Controller for Coupon specific requests for Mobile CI Angular
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

class CouponCIAPIController extends BaseAPIController
{
    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = NULL;

    public function getCouponList()
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

            $coupons = Coupon::select(
                    'promotions.promotion_id',
                    'promotions.promotion_name',
                    'promotions.description',
                    'promotions.long_description',
                    'media.path as image',
                    DB::raw("
                        (SELECT COUNT({$prefix}issued_coupons.issued_coupon_id)
                        from {$prefix}issued_coupons
                        where user_id = '{$user->user_id}'
                        AND {$prefix}issued_coupons.status = 'active'
                        AND {$prefix}issued_coupons.promotion_id = {$prefix}promotions.promotion_id
                    ) as quantity")
                )
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->join('issued_coupons', function ($join) {
                    $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                    $join->where('issued_coupons.status', '=', 'active');
                })
                ->leftJoin('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
                ->leftJoin('media', function ($join) {
                    $join->on('media.object_id', '=', 'merchants.merchant_id')
                        ->where('media_name_long', '=', 'retailer_logo_orig');
                })
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->where('merchants.parent_id', '=', $this->mall_id)
                            ->orWhere('merchants.merchant_id', '=', $this->mall_id);
                    });
                    $q->orWhere(function ($q2) {
                        $q2->whereHas('employee', function ($q3) {
                            $q3->whereHas('employee', function ($q4) {
                                $q4->whereHas('retailers', function ($q5) {
                                    $q5->where('merchants.merchant_id', $this->mall_id);
                                });
                            });
                        });
                    });
                })
                ->where('issued_coupons.user_id', $user->user_id)
                ->where('promotions.coupon_validity_in_date', '>=', $mallTime);


            if (! empty($gender_profile_query)) {
                $coupons->whereRaw($gender_profile_query);
            }
            if (! empty($age_profile_query)) {
                $coupons->whereRaw($age_profile_query);
            }

            OrbitInput::get(
                'keyword', // todo: add alternateLanguage
                function ($keyword) use ($coupons) {
                    $coupons->leftJoin('coupon_translations', function($join) {
                            $join->on('promotions.promotion_id', '=', 'coupon_translations.promotion_id');
                        })
                        ->leftJoin('keyword_object', function($join) {
                            $join->on('promotions.promotion_id', '=', 'keyword_object.object_id');
                            $join->where('keyword_object.object_type', '=', 'coupon');
                        })
                        ->leftJoin('keywords', function($join) {
                            $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                            $join->where('keywords.merchant_id', '=', $this->mall_id);
                        })
                        ->where(function($q) use ($keyword) {
                            $q->where('coupon_translations.promotion_name', 'like', "%$keyword%")
                                ->orWhere('coupon_translations.description', 'like', "%$keyword%")
                                ->orWhere('coupon_translations.long_description', 'like', "%$keyword%")
                                ->orWhere('keyword', '=', $keyword);
                        });
                }
            );

            $coupons->groupBy('promotions.promotion_id');

            $_coupons = clone($coupons);

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.coupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.coupon.per_page');
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
            $coupons->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $coupons)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $coupons->skip($skip);

            $sortBy = '';
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    '_name' => 'merchants.name',
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
                $coupons->orderBy($sortBy, $sortMode);
            }

            $coupons = $coupons->get();

            $data = new \stdclass();
            $data->records = $coupons;
            $data->returned_records = count($coupons);
            $data->total_records = RecordCounter::create($_coupons)->count();
            $data->extras = new \stdclass();

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
            $this->response->data = [$e->getFile(), $e->getLine(), $e->getMessage()];
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
    }
}
