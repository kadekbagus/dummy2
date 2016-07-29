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
use User;
use IssuedCoupon;
use UserVerificationNumber;
use Activity;
use Event;
use Orbit\Helper\Net\SessionPreparer;

class CouponCIAPIController extends BaseAPIController
{
    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = NULL;

    public function getCouponList()
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
            $mallDefaultLanguage = $this->getDefaultLanguage($mall);
            $mallDefaultLanguageId = ! is_null($mallDefaultLanguage) ? $mallDefaultLanguage->language_id : null;
            $language_id = OrbitInput::get('language_id', $mallDefaultLanguageId);

            $coupons = Coupon::select(
                    'promotions.promotion_id',
                    'coupon_translations.promotion_name',
                    'coupon_translations.description',
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
                ->leftJoin('coupon_translations', function ($join) use ($language_id) {
                    $join->on('promotions.promotion_id', '=', 'coupon_translations.promotion_id');
                    if (! is_null($language_id)) {
                        $join->where('coupon_translations.merchant_language_id', '=', $language_id);
                    }
                })
                ->leftJoin('media', function ($join) {
                    $join->on('media.object_id', '=', 'coupon_translations.coupon_translation_id')
                        ->where('media_name_long', '=', 'coupon_translation_image_orig');
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
                ->where('promotions.status', 'active')
                ->where('promotions.coupon_validity_in_date', '>=', $mallTime);

            if (! empty($gender_profile_query)) {
                $coupons->whereRaw($gender_profile_query);
            }
            if (! empty($age_profile_query)) {
                $coupons->whereRaw($age_profile_query);
            }

            OrbitInput::get('ids', function($ids) use ($coupons)
            {
                $coupons->whereNotIn('promotions.promotion_id', $ids);
            });

            OrbitInput::get('tenant_id', function($id) use ($coupons)
            {
                $coupons->where('merchants.merchant_id', $id);
            });

            OrbitInput::get(
                'keyword', // todo: add alternateLanguage
                function ($keyword) use ($coupons) {
                    $coupons->leftJoin('keyword_object', function($join) {
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

            $this->viewItemUserUpdate('coupon', $user, $mall);

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

            $coupons->orderBy(DB::raw('RAND()'));

            $coupons = $coupons->get();

            $data = new \stdclass();
            $data->records = $coupons;
            $data->returned_records = count($coupons);
            $data->total_records = RecordCounter::create($_coupons)->count();
            $data->extras = new \stdclass();

            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: %s List Page', 'Coupon');
                $activity->setUser($user)
                    ->setActivityName('view_coupon_list')
                    ->setActivityNameLong('View Coupon List')
                    ->setObject(null)
                    ->setModuleName('Coupon')
                    ->setNotes($activityNotes)
                    ->setLocation($mall)
                    ->responseOK()
                    ->save();
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

            $activityNotes = sprintf('Failed to view Page: Coupon List. Err: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_coupon_list')
                ->setActivityNameLong('View Coupon List Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
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

            $activityNotes = sprintf('Failed to view Page: Coupon List. Err: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_coupon_list')
                ->setActivityNameLong('View Coupon List Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
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

            $activityNotes = sprintf('Failed to view Page: Coupon List. Err: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_coupon_list')
                ->setActivityNameLong('View Coupon List Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activityNotes = sprintf('Failed to view Page: Coupon List. Err: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_coupon_list')
                ->setActivityNameLong('View Coupon List Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        }

        return $this->render($httpCode);
    }

    public function getCouponItem()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $couponId = 0;
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
            $coupon_id = OrbitInput::get('coupon_id', NULL);

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                    'coupon_id' => $coupon_id,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                    'coupon_id' => 'required|orbit.empty.coupon',
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
            $mallDefaultLanguage = $this->getDefaultLanguage($mall);
            $mallDefaultLanguageId = ! is_null($mallDefaultLanguage) ? $mallDefaultLanguage->language_id : null;
            $language_id = OrbitInput::get('language_id', $mallDefaultLanguageId);

            $quoted_mall_id = $this->quoteStr($this->mall_id);
            $quoted_coupon_id = $this->quoteStr($coupon_id);

            $coupon = Coupon::with([
                    'tenants' => function($q) {
                        $q->select('merchants.merchant_id')
                            ->where('merchants.status', 'active')
                            ->where('merchants.parent_id', $this->mall_id);
                    },
                    'linkToTenants' => function($q) {
                        $q->select('merchants.merchant_id')
                            ->where('merchants.status', 'active')
                            ->where('merchants.parent_id', $this->mall_id);
                    },
                    'linkToMalls' => function($q) {
                        $q->select('merchants.merchant_id')
                            ->where('merchants.merchant_id', $this->mall_id);
                    }
                ])
                ->select(
                    'promotions.promotion_id',
                    'promotions.promotion_name',
                    'promotions.description',
                    'promotions.long_description',
                    'media.path as image',
                    DB::raw("(CONCAT(DATE_FORMAT({$prefix}promotions.begin_date, '%d %M %Y'), ' - ', DATE_FORMAT({$prefix}promotions.end_date, '%d %M %Y'))) as validity"),
                    DB::raw("
                        (SELECT COUNT({$prefix}issued_coupons.issued_coupon_id)
                        FROM {$prefix}issued_coupons
                        WHERE user_id = '{$user->user_id}'
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
                ->leftJoin('coupon_translations', function ($join) use ($language_id) {
                    $join->on('promotions.promotion_id', '=', 'coupon_translations.promotion_id');
                    if (! is_null($language_id)) {
                        $join->where('coupon_translations.merchant_language_id', '=', $language_id);
                    }
                })
                ->leftJoin('media', function ($join) {
                    $join->on('media.object_id', '=', 'coupon_translations.coupon_translation_id')
                        ->where('media_name_long', '=', 'coupon_translation_image_orig');
                })
                ->where('promotions.promotion_id', $coupon_id)
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
                ->where('promotions.status', 'active')
                ->where('promotions.coupon_validity_in_date', '>=', $mallTime);

            if (! empty($gender_profile_query)) {
                $coupon->whereRaw($gender_profile_query);
            }
            if (! empty($age_profile_query)) {
                $coupon->whereRaw($age_profile_query);
            }

            OrbitInput::get(
                'keyword', // todo: add alternateLanguage
                function ($keyword) use ($coupon) {
                    $coupon->leftJoin('coupon_translations', function($join) {
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

            $coupon->groupBy('promotions.promotion_id');

            $coupon = $coupon->first();

            $couponId = $coupon->promotion_id;

            // Check coupon have condition cs reedem
            $cs_reedem = false;

            // Check exist customer verification number per mall
            $employeeVerNumbersActive = \UserVerificationNumber::join('users', 'users.user_id', '=', 'user_verification_numbers.user_id')
                ->where('users.status', 'active')
                ->where('merchant_id', $this->mall_id)
                ->count('users.user_id');

            if (is_object($coupon)) {

                $issued_coupon = IssuedCoupon::active()
                    ->where('promotion_id', $coupon->promotion_id)
                    ->where('user_id', $user->user_id)
                    ->orderBy('expired_date', 'DESC')
                    ->first();

                $coupon->issued_coupon_id = null;
                if (is_object($issued_coupon)) {
                    $coupon->issued_coupon_id = $issued_coupon->issued_coupon_id;
                }

                if ($coupon->is_all_employee === 'Y') {
                    if ($employeeVerNumbersActive > 0) {
                        $cs_reedem = true;
                    }
                } else {
                    // Check exist link to cs, and cs must have active status
                    $promotionEmployee = \CouponEmployee::join('users', 'users.user_id', '=', 'promotion_employee.user_id')
                        ->where('users.status', 'active')
                        ->where('promotion_employee.promotion_id', $coupon->promotion_id)
                        ->count('promotion_employee_id');

                    if ($promotionEmployee > 0) {
                        $cs_reedem = true;
                    }
                }
                $coupon->linked_to_cs = $cs_reedem;
            }

            $activityNotes = sprintf('Page viewed: Coupon Detail, Coupon Id: %s', $coupon->promotion_id);
            $activity->setUser($user)
                ->setActivityName('view_coupon')
                ->setActivityNameLong('View Coupon Detail')
                ->setObject($coupon)
                ->setCoupon($coupon)
                ->setModuleName('Coupon')
                ->setLocation($mall)
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

            $this->response->data = $coupon;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            $activityNotes = sprintf('Page viewed: Coupon Detail Failed, Coupon Id: %s. Err: %s', $couponId, $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_coupon')
                ->setActivityNameLong('View Coupon Detail Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
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

            $activityNotes = sprintf('Page viewed: Coupon Detail Failed, Coupon Id: %s. Err: %s', $couponId, $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_coupon')
                ->setActivityNameLong('View Coupon Detail Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
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

            $activityNotes = sprintf('Page viewed: Coupon Detail Failed, Coupon Id: %s. Err: %s', $couponId, $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_coupon')
                ->setActivityNameLong('View Coupon Detail Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activityNotes = sprintf('Page viewed: Coupon Detail Failed, Coupon Id: %s. Err: %s', $couponId, $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_coupon')
                ->setActivityNameLong('View Coupon Detail Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        }

        return $this->render($httpCode);
    }

    public function postRedeemCoupon()
    {
        $activity = Activity::mobileci()->setActivityType('coupon');
        $user = NULL;
        $mall = NULL;
        $mall_id = NULL;
        $issuedcoupon = NULL;
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

            $this->mall_id = OrbitInput::post('mall_id', NULL);
            $issuedCouponId = OrbitInput::post('issued_coupon_id');
            $verificationNumber = OrbitInput::post('merchant_verification_number');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                    'merchant_verification_number' => $verificationNumber,
                    'issued_coupon_id' => $issuedCouponId,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                    'merchant_verification_number'  => 'required',
                    'issued_coupon_id'              => 'required|orbit.empty.issuedcoupon:' . $user->user_id . ',' . $this->mall_id . ',' . $verificationNumber,
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $tenant = Tenant::active()
                ->where('parent_id', $this->mall_id)
                ->where('masterbox_number', $verificationNumber)
                ->first();

            $csVerificationNumber = UserVerificationNumber::
                where('merchant_id', $this->mall_id)
                ->where('verification_number', $verificationNumber)
                ->first();

            $redeem_retailer_id = NULL;
            $redeem_user_id = NULL;
            if (! is_object($tenant) && ! is_object($csVerificationNumber)) {
                // @Todo replace with language
                $message = 'Tenant is not found.';
                ACL::throwAccessForbidden($message);
            } else {
                if (is_object($tenant)) {
                    $redeem_retailer_id = $tenant->merchant_id;
                }
                if (is_object($csVerificationNumber)) {
                    $redeem_user_id = $csVerificationNumber->user_id;
                }
            }

            $mall = App::make('orbit.empty.mall');
            $issuedcoupon = App::make('orbit.empty.issuedcoupon');

            // The coupon information
            $coupon = $issuedcoupon->coupon;

            $issuedcoupon->redeemed_date = date('Y-m-d H:i:s');
            $issuedcoupon->redeem_retailer_id = $redeem_retailer_id;
            $issuedcoupon->redeem_user_id = $redeem_user_id;
            $issuedcoupon->redeem_verification_code = $verificationNumber;
            $issuedcoupon->status = 'redeemed';

            $issuedcoupon->save();

            // Commit the changes
            $this->commit();

            $this->response->message = 'Coupon has been successfully redeemed.';
            $this->response->data = $issuedcoupon->issued_coupon_code;

            $activityNotes = sprintf('Coupon Redeemed: %s', $issuedcoupon->coupon->promotion_name);
            $activity->setUser($user)
                ->setActivityName('redeem_coupon')
                ->setActivityNameLong('Coupon Redemption (Successful)')
                ->setObject($coupon)
                ->setNotes($activityNotes)
                ->setLocation($mall)
                ->setModuleName('Coupon')
                ->responseOK();

            $activity->coupon_id = $issuedcoupon->promotion_id;
            $activity->coupon_name = $issuedcoupon->coupon->promotion_name;

            Event::fire('orbit.coupon.postissuedcoupon.after.commit', array($this, $issuedcoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.redeemcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            $activity->setUser($user)
                ->setActivityName('redeem_coupon')
                ->setActivityNameLong('Coupon Redemption (Failed)')
                ->setObject($issuedcoupon)
                ->setNotes($e->getMessage())
                ->setLocation($mall)
                ->setModuleName('Coupon')
                ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.redeemcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            $activity->setUser($user)
                ->setActivityName('redeem_coupon')
                ->setActivityNameLong('Coupon Redemption (Failed)')
                ->setObject($issuedcoupon)
                ->setNotes($e->getMessage())
                ->setLocation($mall)
                ->setModuleName('Coupon')
                ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.redeemcoupon.query.error', array($this, $e));

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

            $this->rollBack();

            $activity->setUser($user)
                ->setActivityName('redeem_coupon')
                ->setActivityNameLong('Coupon Redemption (Failed)')
                ->setObject($issuedcoupon)
                ->setNotes($e->getMessage())
                ->setLocation($mall)
                ->setModuleName('Coupon')
                ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.coupon.redeemcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $this->rollBack();

            $activity->setUser($user)
                ->setActivityName('redeem_coupon')
                ->setActivityNameLong('Coupon Redemption (Failed)')
                ->setObject($issuedcoupon)
                ->setNotes($e->getMessage())
                ->setLocation($mall)
                ->setModuleName('Coupon')
                ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    public function issueAutoCoupon()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $this->checkAuth();
            $session = SessionPreparer::prepareSession();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->validRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->mall_id = OrbitInput::get('mall_id', NULL);

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                ),
                array(
                    'sortby.in' => Lang::get('validation.orbit.empty.tenant_ci_sortby'),
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $retailer = Mall::excludeDeleted()->where('merchant_id', $this->mall_id)->first();
            $mallTime = Carbon::now($retailer->timezone->timezone_name);

            Coupon::issueAutoCoupon($retailer, $user, $session);

            $this->response->data = null;
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

        // Check the existance of coupon id
        Validator::extend('orbit.empty.coupon', function ($attribute, $value, $parameters) {
            $coupon = Coupon::active()
                        ->where('promotion_id', $value)
                        ->first();

            if (empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.empty.coupon', $coupon);

            return TRUE;
        });

        Validator::extend('orbit.empty.issuedcoupon', function ($attribute, $value, $parameters) {
            $now = date('Y-m-d H:i:s');
            $user_id = $parameters[0];
            $mall_id = $parameters[1];
            $number = $parameters[2];

            $prefix = DB::getTablePrefix();

            $issuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                        ->where('issued_coupons.issued_coupon_id', $value)
                        ->where('issued_coupons.user_id', $user_id)
                        // ->whereRaw("({$prefix}issued_coupons.expired_date >= ? or {$prefix}issued_coupons.expired_date is null)", [$now])
                        ->with('coupon')
                        ->whereHas('coupon', function($q) use($now) {
                            $q->where('promotions.status', 'active');
                            $q->where('promotions.coupon_validity_in_date', '>=', $now);
                        })
                        ->first();

            if (empty($issuedCoupon)) {
                $errorMessage = sprintf('Issued coupon ID %s is not found.', htmlentities($value));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            //Checking verification number in cs and tenant verification number
            //Checking in tenant verification number first
            if ($issuedCoupon->coupon->is_all_retailer === 'Y') {
                $checkIssuedCoupon = Tenant::where('parent_id','=', $mall_id)
                            ->where('status', 'active')
                            ->where('masterbox_number', $number)
                            ->first();
            } elseif ($issuedCoupon->coupon->is_all_retailer === 'N') {
                $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                            ->join('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_id', '=', 'issued_coupons.promotion_id')
                            ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
                            ->where('issued_coupons.issued_coupon_id', $value)
                            ->where('issued_coupons.user_id', $user_id)
                            // ->whereRaw("({$prefix}issued_coupons.expired_date >= ? or {$prefix}issued_coupons.expired_date is null)", [$now])
                            ->whereHas('coupon', function($q) use($now) {
                                $q->where('promotions.status', 'active');
                                $q->where('promotions.coupon_validity_in_date', '>=', $now);
                            })
                            ->where('merchants.masterbox_number', $number)
                            ->first();
            }

            // Continue checking to tenant verification number
            if (empty($checkIssuedCoupon)) {
                // Checking cs verification number
                if ($issuedCoupon->coupon->is_all_employee === 'Y') {
                    $checkIssuedCoupon = UserVerificationNumber::
                                join('users', 'users.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('status', 'active')
                                ->where('merchant_id', $mall_id)
                                ->where('verification_number', $number)
                                ->first();
                } elseif ($issuedCoupon->coupon->is_all_employee === 'N') {
                    $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                                ->join('promotion_employee', 'promotion_employee.promotion_id', '=', 'issued_coupons.promotion_id')
                                ->join('user_verification_numbers', 'user_verification_numbers.user_id', '=', 'promotion_employee.user_id')
                                ->join('employees', 'employees.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('employees.status', 'active')
                                ->where('issued_coupons.issued_coupon_id', $value)
                                ->where('issued_coupons.user_id', $user_id)
                                // ->whereRaw("({$prefix}issued_coupons.expired_date >= ? or {$prefix}issued_coupons.expired_date is null)", [$now])
                                ->whereHas('coupon', function($q) use($now) {
                                    $q->where('promotions.status', 'active');
                                    $q->where('promotions.coupon_validity_in_date', '>=', $now);
                                })
                                ->where('user_verification_numbers.verification_number', $number)
                                ->first();
                }
            }

            if (! isset($checkIssuedCoupon) || empty($checkIssuedCoupon)) {
                $errorMessage = Lang::get('mobileci.coupon.wrong_verification_number');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (! empty($checkIssuedCoupon)) {
                App::instance('orbit.empty.issuedcoupon', $issuedCoupon);
            }

            return TRUE;
        });
    }
}
