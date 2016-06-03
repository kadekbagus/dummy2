<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Shelgi <shelgi@dominopos.com>
 * @desc Controller for widget in Mobile CI Angular
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
use \Validator;
use Tenant;
use Mall;
use Widget;
use News;
use Coupon;
use LuckyDraw;
use Setting;
use WidgetTemplate;
use TenantStoreAndService;
use App;
use Lang;
use Carbon\Carbon as Carbon;

class WidgetCIAPIController extends BaseAPIController
{
    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = NULL;

    public function getWidgetList()
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

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            $mallid = $this->mall_id;

            $mall = Mall::excludeDeleted()->where('merchant_id', $mallid)->first();
            $now = Carbon::now($mall->timezone->timezone_name);

            $widget_template_id = Setting::select('setting_value')
                                ->where('setting_name', 'widget_template')
                                ->where('object_id', $mallid)
                                ->first();

            if($widget_template_id) {
                $template = WidgetTemplate::active()->where('widget_template_id', $widget_template_id->setting_value)->first();
                if (! is_object($template)) {
                    $widget_template = 'default';
                } else {
                    $widget_template = $template->template_file_name;
                }
            } else {
                $widget_template = 'default';
            }

            $widgets = Widget::select('widgets.widget_id', 'widgets.widget_type', 'widgets.widget_order')
                            ->with('media')
                            ->leftJoin(DB::raw("(SELECT setting_id, setting_name, setting_value, object_id
                                        FROM {$prefix}settings
                                        WHERE setting_name like '%widget%'
                                            AND object_id = '{$mallid}') AS os"),
                                // On
                                DB::raw('os.setting_name'), '=', DB::raw("CONCAT('enable_', {$prefix}widgets.widget_type, '_widget')"))
                            ->join('widget_retailer', 'widget_retailer.widget_id', '=', 'widgets.widget_id')
                            ->where('widgets.status', '!=', 'deleted')
                            ->where('widgets.merchant_id', '=', $mallid)
                            ->whereRaw("(CASE WHEN os.setting_id IS NULL THEN 'true' ELSE os.setting_value END) = 'true'")
                            ->groupBy('widgets.widget_type')
                            ->orderBy('widgets.widget_order', 'ASC');

            $_widgets = clone($widgets);

            $widgets = $widgets->get();

            foreach ($widgets as $widget) {
                if ($widget->widget_type == 'tenant') {
                    // get all tenant count
                    $tenantsCount = Tenant::active()
                        ->where('parent_id', $mallid)
                        ->count();

                    // get all new tenant after new_date
                    $newTenantsCount = Tenant::active()
                        ->where('parent_id', $mallid)
                        ->whereNotIn('merchants.merchant_id', function($q) use ($user, $mallid) {
                            $q->select('item_id')
                                ->from('viewed_item_user')
                                ->where('user_id', '=', $user->user_id)
                                ->where('mall_id', '=', $mallid)
                                ->where('item_type', '=', 'tenant')
                                ->get();
                        })
                        ->count();

                    $widget->image = 'mobile-ci/images/default_tenants_directory.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_tenants_directory.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->item_count = $tenantsCount;
                    $widget->new_item_count = $newTenantsCount > 9 ? '9+' : $newTenantsCount;
                    $widget->display_title = Lang::get('mobileci.widgets.tenant');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.tenants');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.tenants_single');
                    }
                }
                if ($widget->widget_type == 'service') {
                    // get all tenant count
                    $serviceCount = TenantStoreAndService::where('status', 'active')
                        ->where('object_type', 'service')
                        ->where('parent_id', $mallid)
                        ->count();

                    // get all new tenant after new_date
                    $newServiceCount = TenantStoreAndService::where('status', 'active')
                        ->where('object_type', 'service')
                        ->where('parent_id', $mallid)
                        ->whereNotIn('merchants.merchant_id', function($q) use ($user, $mallid) {
                            $q->select('item_id')
                                ->from('viewed_item_user')
                                ->where('user_id', '=', $user->user_id)
                                ->where('mall_id', '=', $mallid)
                                ->where('item_type', '=', 'service')
                                ->get();
                        })
                        ->count();

                    $widget->image = 'mobile-ci/images/default_services_directory.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_services_directory.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->item_count = $serviceCount;
                    $widget->new_item_count = $newServiceCount > 9 ? '9+' : $newServiceCount;
                    $widget->display_title = Lang::get('mobileci.widgets.service');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.services');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.services_single');
                    }
                }
                if ($widget->widget_type == 'promotion') {

                    $userAge = 0;
                    if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                        $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
                    }

                    $userGender = 'U'; // default is Unknown
                    if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                        $userGender =  $user->userDetail->gender;
                    }

                    // get all news count filter by age range and gender
                    $promotionsCount = News::select('news.news_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                                    ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->where(function ($q) use ($mallid) {
                                        $q->where('merchants.parent_id', '=', $mallid)
                                          ->orWhere('merchants.merchant_id', '=', $mallid);
                                    });

                    if ($userGender !== null) {
                        $promotionsCount = $promotionsCount->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                    }

                    if ($userAge !== null) {
                        if ($userAge === 0){
                            $promotionsCount = $promotionsCount->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        } else {
                            if ($userAge >= 55) {
                                $promotionsCount = $promotionsCount->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                            } else {
                                $promotionsCount = $promotionsCount->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                            }
                        }
                    }

                    $promotionsCount->where('news.status', '=', 'active')
                                ->where('news.object_type', 'promotion')
                                ->groupBy('news.news_id')
                                ->whereRaw("? between begin_date and end_date", [$now]);
                    $promotionsCount = RecordCounter::create($promotionsCount)->count();

                    // get all new news after new_date filter by age range and gender
                    $newPromotionsCount = News::select('news.news_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                                    ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->where(function ($q) use ($mallid) {
                                        $q->where('merchants.parent_id', '=', $mallid)
                                          ->orWhere('merchants.merchant_id', '=', $mallid);
                                    })
                                    ->whereNotIn('news.news_id', function($q) use ($user) {
                                        $q->select('item_id')
                                            ->from('viewed_item_user')
                                            ->where('user_id', '=', $user->user_id)
                                            ->where('item_type', '=', 'promotion')
                                            ->get();
                                    });

                    if ($userGender !== null) {
                        $newPromotionsCount = $newPromotionsCount->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                    }

                    if ($userAge !== null) {
                        if ($userAge === 0){
                            $newPromotionsCount = $newPromotionsCount->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        } else {
                            if ($userAge >= 55) {
                                $newPromotionsCount = $newPromotionsCount->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                            } else {
                                $newPromotionsCount = $newPromotionsCount->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                            }
                        }
                    }

                    $newPromotionsCount->where('news.status', '=', 'active')
                                ->where('news.object_type', 'promotion')
                                ->whereRaw("? between begin_date and end_date", [$now])
                                ->groupBy('news.news_id');
                    $newPromotionsCount = RecordCounter::create($newPromotionsCount)->count();

                    $widget->image = 'mobile-ci/images/default_promotion.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_promotion.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->item_count = $promotionsCount;
                    $widget->new_item_count = $newPromotionsCount > 9 ? '9+' : $newPromotionsCount;
                    $widget->display_title = Lang::get('mobileci.widgets.promotion');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.promotions');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.promotions_single');
                    }
                }
                if ($widget->widget_type == 'news') {

                    $userAge = 0;
                    if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                        $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
                    }

                    $userGender = 'U'; // default is Unknown
                    if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                        $userGender =  $user->userDetail->gender;
                    }

                    // get all news count filter by age range and gender
                    $newsCount = News::select('news.news_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                                    ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->where(function ($q) use ($mallid) {
                                        $q->where('merchants.parent_id', '=', $mallid)
                                          ->orWhere('merchants.merchant_id', '=', $mallid);
                                    });

                    if ($userGender !== null) {
                        $newsCount = $newsCount->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                    }

                    if ($userAge !== null) {
                        if ($userAge === 0){
                            $newsCount = $newsCount->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        } else {
                            if ($userAge >= 55) {
                                $newsCount = $newsCount->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                            } else {
                                $newsCount = $newsCount->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                            }
                        }
                    }

                    $newsCount->where('news.status', '=', 'active')
                                ->where('news.object_type', 'news')
                                ->where('news.status', 'active')
                                ->whereRaw("? between begin_date and end_date", [$now])
                                ->groupBy('news.news_id');
                    $newsCount = RecordCounter::create($newsCount)->count();

                    // get all new news after new_date filter by age range and gender
                    $newNewsCount = News::select('news.news_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                                    ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->where(function ($q) use ($mallid) {
                                        $q->where('merchants.parent_id', '=', $mallid)
                                          ->orWhere('merchants.merchant_id', '=', $mallid);
                                    })
                                    ->whereNotIn('news.news_id', function($q) use ($user) {
                                        $q->select('item_id')
                                            ->from('viewed_item_user')
                                            ->where('user_id', '=', $user->user_id)
                                            ->where('item_type', '=', 'news')
                                            ->get();
                                    });

                    if ($userGender !== null) {
                        $newNewsCount = $newNewsCount->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                    }

                    if ($userAge !== null) {
                        if ($userAge === 0){
                            $newNewsCount = $newNewsCount->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        } else {
                            if ($userAge >= 55) {
                                $newNewsCount = $newNewsCount->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                            } else {
                                $newNewsCount = $newNewsCount->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                            }
                        }
                    }

                    $newNewsCount->where('news.status', '=', 'active')
                                ->where('news.object_type', 'news')
                                ->whereRaw("? between begin_date and end_date", [$now])
                                ->groupBy('news.news_id');

                    $newNewsCount = RecordCounter::create($newNewsCount)->count();

                    $widget->image = 'mobile-ci/images/default_news.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_news.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->item_count = $newsCount;
                    $widget->new_item_count = $newNewsCount > 9 ? '9+' : $newNewsCount;
                    $widget->display_title = Lang::get('mobileci.widgets.news');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.newss');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.newss_single');
                    }
                }
                if ($widget->widget_type == 'coupon') {
                    $userAge = 0;
                    if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                        $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
                    }

                    $userGender = 'U'; // default is Unknown
                    if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                        $userGender =  $user->userDetail->gender;
                    }

                    $couponsCount = Coupon::select('promotions.promotion_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                                    ->leftJoin('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
                                    ->where(function ($q) use ($mallid) {
                                        $q->where(function ($q2) use ($mallid) {
                                            $q2->where('merchants.parent_id', '=', $mallid)
                                                ->orWhere('merchants.merchant_id', '=', $mallid);
                                        });
                                        $q->orWhere(function ($q2) use ($mallid) {
                                            $q2->whereHas('employee', function ($q3) use ($mallid) {
                                                $q3->whereHas('employee', function ($q4) use ($mallid) {
                                                    $q4->whereHas('retailers', function ($q5) use ($mallid) {
                                                        $q5->where('merchants.merchant_id', $mallid);
                                                    });
                                                });
                                            });
                                        });
                                    });

                    if ($userGender !== null) {
                        $couponsCount = $couponsCount->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                    }

                    if ($userAge !== null) {
                        if ($userAge === 0){
                            $couponsCount = $couponsCount->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        } else {
                            if ($userAge >= 55) {
                                $couponsCount = $couponsCount->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                            } else {
                                $couponsCount = $couponsCount->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                            }
                        }
                    }

                    $couponsCount->join('promotion_rules', function($join) {
                            $join->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')
                                ->where('promotions.is_coupon', '=', 'Y');
                        })->join('issued_coupons', function($join) {
                            $join->on('promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                                ->where('issued_coupons.status', '=', 'active');
                        })
                        ->where('promotions.status', '=', 'active')
                        ->where('promotions.coupon_validity_in_date', '>=', $now)
                        ->where('issued_coupons.user_id', $user->user_id)
                        ->groupBy('promotions.promotion_id');
                    $couponsCount = RecordCounter::create($couponsCount)->count();

                    $newCouponsCount = Coupon::select('promotions.promotion_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id');

                    if ($userGender !== null) {
                        $newCouponsCount = $newCouponsCount->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                    }

                    if ($userAge !== null) {
                        if ($userAge === 0){
                            $newCouponsCount = $newCouponsCount->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        } else {
                            if ($userAge >= 55) {
                                $newCouponsCount = $newCouponsCount->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                            } else {
                                $newCouponsCount = $newCouponsCount->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                            }
                        }
                    }

                    $user_id = $user->user_id;
                    $quote = function ($arg) {
                        return DB::connection()->getPdo()->quote($arg);
                    };
                    $newCouponsCount->join('promotion_rules', function($join) {
                            $join->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')
                                ->where('promotions.is_coupon', '=', 'Y');
                        })->join('issued_coupons', function($join) {
                            $join->on('promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                                ->where('issued_coupons.status', '=', 'active');
                        })
                        ->whereRaw("
                            {$prefix}promotions.promotion_id NOT IN (
                            SELECT item_id FROM {$prefix}viewed_item_user
                            WHERE user_id = {$quote($user_id)}
                            AND item_type = 'coupon'
                        )")
                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                        ->where(function ($q) use ($mallid) {
                            $q->where(function ($q2) use ($mallid) {
                                $q2->where('merchants.parent_id', '=', $mallid)
                                    ->orWhere('merchants.merchant_id', '=', $mallid);
                            });
                            $q->orWhere(function ($q2) use ($mallid) {
                                $q2->whereHas('employee', function ($q3) use ($mallid) {
                                    $q3->whereHas('employee', function ($q4) use ($mallid) {
                                        $q4->whereHas('retailers', function ($q5) use ($mallid) {
                                            $q5->where('merchants.merchant_id', $mallid);
                                        });
                                    });
                                });
                            });
                        })
                        ->where('promotions.status', '=', 'active')
                        ->where('promotions.coupon_validity_in_date', '>=', $now)
                        ->where('issued_coupons.user_id', $user->user_id)
                        ->groupBy('promotions.promotion_id');
                    $newCouponsCount = RecordCounter::create($newCouponsCount)->count();

                    $widget->image = 'mobile-ci/images/default_coupon.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_coupon.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->item_count = $couponsCount;
                    $widget->new_item_count = $newCouponsCount > 9 ? '9+' : $newCouponsCount;
                    $widget->display_title = Lang::get('mobileci.widgets.coupon');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.coupons');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.coupons_single');
                    }
                }
                if ($widget->widget_type == 'lucky_draw') {
                    $luckydrawsCount = LuckyDraw::active()
                        ->where('mall_id', $mallid)
                        ->whereRaw("? between start_date and grace_period_date", [$now])
                        ->count();

                    $newLuckydrawsCount = LuckyDraw::active()
                        ->where('mall_id', $mallid)
                        ->whereRaw("? between start_date and grace_period_date", [$now])
                        ->whereNotIn('lucky_draws.lucky_draw_id', function($q) use ($user,$mallid) {
                            $q->select('item_id')
                                ->from('viewed_item_user')
                                ->where('user_id', '=', $user->user_id)
                                ->where('mall_id', '=', $mallid)
                                ->where('item_type', '=', 'lucky_draw')
                                ->get();
                        })
                        ->count();

                    $widget->image = 'mobile-ci/images/default_lucky_number.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_lucky_number.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->item_count = $luckydrawsCount;
                    $widget->new_item_count = $newLuckydrawsCount > 9 ? '9+' : $newLuckydrawsCount;
                    $widget->display_title = Lang::get('mobileci.widgets.lucky_draw');
                    if ($widget->item_count > 1) {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.lucky_draws');
                    } else {
                        $widget->display_sub_title = Lang::get('mobileci.widgets.lucky_draws_single');
                    }
                }
                if ($widget->widget_type == 'free_wifi') {
                    $widget->image = 'mobile-ci/images/default_free_wifi_directory.png';

                    foreach ($widget->media as $media) {
                        if ($media->media_name_long === 'home_widget_orig') {
                            if (empty($media->path)) {
                                $widget->image = 'mobile-ci/images/default_free_wifi_directory.png';
                            } else {
                                $widget->image = $media->path;
                            }
                        }
                    }

                    $widget->display_title = Lang::get('mobileci.widgets.free_wifi');
                    $widget->display_sub_title = Lang::get('mobileci.widgets.free_wifi');
                }
            }

            $data = new \stdclass();
            $data->returned_records = count($widgets);
            $data->total_records = RecordCounter::create($_widgets)->count();
            $data->widget_template = $widget_template;
            $data->records = $widgets;
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
