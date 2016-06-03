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

            $coupons = Coupon::select(
                    'promotions.promotion_id',
                    'promotions.promotion_name',
                    'promotions.description',
                    'media.path as image',
                    DB::raw("
                        (SELECT COUNT({$prefix}issued_coupons.issued_coupon_id)
                        from {$prefix}issued_coupons
                        where user_id = '{$user->user_id}'
                        AND {$prefix}issued_coupons.status = 'active'
                        AND {$prefix}issued_coupons.promotion_id = {$prefix}promotions.promotion_id
                    ) as quantity")
                )
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

            OrbitInput::get('ids', function($ids) use ($coupons)
            {
                $coupons->whereNotIn('promotions.promotion_id', $ids);
            });

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

            $coupons->orderBy(DB::raw('RAND()'));

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
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    public function getCouponItem()
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
                    }
                ])
                ->select(
                    'promotions.promotion_id',
                    'promotions.promotion_name',
                    'promotions.description',
                    'promotions.long_description',
                    'media.path as image',
                    DB::raw("(CONCAT(DATE_FORMAT({$prefix}promotions.begin_date, '%d %b %Y'), ' - ', DATE_FORMAT({$prefix}promotions.end_date, '%d %b %Y'))) as validity"),
                    DB::raw("
                        (SELECT COUNT({$prefix}issued_coupons.issued_coupon_id)
                        FROM {$prefix}issued_coupons
                        WHERE user_id = '{$user->user_id}'
                        AND {$prefix}issued_coupons.status = 'active'
                        AND {$prefix}issued_coupons.promotion_id = {$prefix}promotions.promotion_id
                    ) as quantity")
                )
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

            // Check coupon have condition cs reedem
            $cs_reedem = false;

            // Check exist customer verification number per mall
            $employeeVerNumbersActive = \UserVerificationNumber::join('users', 'users.user_id', '=', 'user_verification_numbers.user_id')
                ->where('users.status', 'active')
                ->where('merchant_id', $this->mall_id)
                ->count('users.user_id');

            if ($coupon->is_all_employee === 'Y') {
                if ($employeeVerNumbersActive > 0) {
                    $cs_reedem = true;
                }
            } elseif ($coupon->is_all_employee === 'N') {
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
    }
}
