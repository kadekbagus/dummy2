<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Controller for Tenant specific requests for Mobile CI Angular
 */
use Orbit\Controller\API\v1\Customer\BaseAPIController;
use OrbitShop\API\v1\ResponseProvider;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use Tenant;
use Mall;

class TenantCIAPIController extends BaseAPIController
{
    protected $validRoles = ['consumer', 'guest'];
    protected $mall_id = NULL;

    public function getTenantList()
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

            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                ),
                array(
                    'mall_id' => 'required',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $tenants = Tenant::with(
            [
                'categories' => function($q) {
                    $q->where('categories.status', 'active');
                    $q->orderBy('category_name', 'asc');
                }, 
                'media' => function($q) {
                    $q->where('media_name_long', 'retailer_logo_orig');
                },
                'merchantSocialMedia.socialMedia'
            ])
            ->active()
            ->where('parent_id', $this->mall_id);

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

            $tenants = $tenants->get();

            $data = new \stdclass();
            $data->records = $this->collectionTransformer($tenants);
            $data->returned_records = count($tenants);
            $data->total_records = RecordCounter::create($_tenants)->count();

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Fetch OK';
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

    public function getTenantItem($id)
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $tenant = Tenant::with('categories')
                ->where('merchant_id', $id)
                ->first();

            $this->response->data = $this->itemTransformer($tenant);
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Fetch OK';
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

    private function collectionTransformer(\Illuminate\Database\Eloquent\Collection $tenants)
    {
        $user = $this->api->user;

        $mall = Mall::where('merchant_id', $this->mall_id)->firstOrFail();

        $prefix = DB::getTablePrefix();

        $userAge = 0;
        if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
            $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
        }

        $userGender = 'U'; // default is Unknown
        if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
            $userGender =  $user->userDetail->gender;
        }

        $mallTime = Carbon::now($mall->timezone->timezone_name);

        $news_flag = Tenant::select('merchants.name','news.news_name')->excludeDeleted('merchants')
                    ->leftJoin('news_merchant', 'news_merchant.merchant_id', '=', 'merchants.merchant_id')
                    ->leftJoin('news', 'news.news_id', '=', 'news_merchant.news_id')
                        ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                        ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                        ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id');

        // filter by age and gender
        if ($userGender !== null) {
            $news_flag = $news_flag->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
        }
        if ($userAge !== null) {
            if ($userAge === 0){
                $news_flag = $news_flag->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
            } else {
                if ($userAge >= 55) {
                    $news_flag = $news_flag->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                } else {
                    $news_flag = $news_flag->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                }
            }
        }

        $news_flag = $news_flag->where('merchants.parent_id', '=', $mall->merchant_id)
                    ->where('news.object_type', '=', 'news')
                    ->where('news.status', '=', 'active')
                    ->whereRaw("? between {$prefix}news.begin_date and {$prefix}news.end_date", [$mallTime])
                    ->groupBy('merchants.name')->get();

        $promotion_flag = Tenant::select('merchants.name','news.news_name')->excludeDeleted('merchants')
                    ->leftJoin('news_merchant', 'news_merchant.merchant_id', '=', 'merchants.merchant_id')
                    ->leftJoin('news', 'news.news_id', '=', 'news_merchant.news_id')
                        ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                        ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                        ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id');

        // filter by age and gender
        if ($userGender !== null) {
            $promotion_flag = $promotion_flag->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
        }
        if ($userAge !== null) {
            if ($userAge === 0){
                $promotion_flag = $promotion_flag->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
            } else {
                if ($userAge >= 55) {
                    $promotion_flag = $promotion_flag->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                } else {
                    $promotion_flag = $promotion_flag->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                }
            }
        }

        $promotion_flag = $promotion_flag->where('merchants.parent_id', '=', $mall->merchant_id)
                    ->where('news.object_type', '=', 'promotion')
                    ->where('news.status', '=', 'active')
                    ->whereRaw("? between {$prefix}news.begin_date and {$prefix}news.end_date", [$mallTime])
                    ->groupBy('merchants.name')->get();

        $coupon_flag = Tenant::select('merchants.name','promotions.promotion_name')->excludeDeleted('merchants')
                    ->leftJoin('promotion_retailer', 'promotion_retailer.retailer_id', '=', 'merchants.merchant_id')
                    ->leftJoin('promotions', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                    ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                    ->join('issued_coupons', function ($join) {
                        $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                        $join->where('issued_coupons.status', '=', 'active');
                    })
                    ->where('promotions.coupon_validity_in_date', '>=', Carbon::now($mall->timezone->timezone_name))
                    ->where('issued_coupons.user_id', $user->user_id);

        // filter by age and gender
        if ($userGender !== null) {
            $coupon_flag = $coupon_flag->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
        }
        if ($userAge !== null) {
            if ($userAge === 0){
                $coupon_flag = $coupon_flag->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
            } else {
                if ($userAge >= 55) {
                    $coupon_flag = $coupon_flag->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                } else {
                    $coupon_flag = $coupon_flag->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                }
            }
        }

        $coupon_flag = $coupon_flag->where('merchants.parent_id', '=', $mall->merchant_id)
                    ->where('promotions.is_coupon', '=', 'Y')
                    ->where('promotions.status', '=', 'active')
                    ->whereRaw("? between {$prefix}promotions.begin_date and {$prefix}promotions.end_date", [$mallTime])
                    ->groupBy('merchants.name')->get();

        $output = array();
        foreach ($tenants as $tenant) {
            $categories_array = array();
            foreach ($tenant->categories as $category) {
                $category_obj = new \stdclass();
                $category_obj->category_id = $category->category_id;
                $category_obj->category_name = $category->category_name;
                $categories_array[] = $category_obj;
            }
            $facebook_like_url = '';
            if (count($tenant->merchantSocialMedia) > 0) {
                foreach ($tenant->merchantSocialMedia as $merchantSocialMedia) {
                    if ($merchantSocialMedia->socialMedia->social_media_code === 'facebook') {
                        if (! empty($merchantSocialMedia->social_media_uri)) {
                            $facebook_like_url = '//' . $merchantSocialMedia->socialMedia->social_media_main_url . '/' . $merchantSocialMedia->social_media_uri;
                        }
                    }
                }
            }
            $output[] = array(
                'merchant_id' => $tenant->merchant_id,
                'name' => $tenant->name,
                'floor' => $tenant->floor,
                'unit' => $tenant->unit,
                'logo' => isset($tenant->media[0]) ? $tenant->media[0]->path : null,
                'categories' => $categories_array,
                'facebook_like_url' => $facebook_like_url,
                'news_flag' => count($news_flag) > 0 ? "true" : "false",
                'promotion_flag' => count($promotion_flag) > 0 ? "true" : "false",
                'coupon_flag' => count($coupon_flag) > 0 ? "true" : "false",
            );
        }

        return $output;
    }

    private function itemTransformer(Tenant $tenant)
    {
        $output = array(
            'merchant_id' => $tenant->merchant_id,
            'name' => $tenant->name,
            'floor' => $tenant->floor,
            'unit' => $tenant->unit
        );

        return $output;
    }
}
