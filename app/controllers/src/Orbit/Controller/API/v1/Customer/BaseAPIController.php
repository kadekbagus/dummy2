<?php namespace Orbit\Controller\API\v1\Customer;
/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Base controller used for Mobile CI Angular
 */
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Helper\Net\SessionPreparer;
use App;
use Config;
use URL;
use Validator;
use Setting;
use DB;
use MerchantLanguage;
use Language;
use Carbon\Carbon as Carbon;
use Tenant;
use Coupon;
use News;
use Mall;
use User;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Activity;



class BaseAPIController extends ControllerAPI
{
    protected $user = NULL;
    protected $mall_id = NULL;
    protected $session = NULL;

    /**
     * Calculate the Age
     *
     * @author Firmansyah <firmansyah@myorbit.com>
     * @param string $birth_date format date : YYYY-MM-DD
     * @return string
     */
    public function calculateAge($birth_date)
    {
        $age = date_diff(date_create($birth_date), date_create('today'))->y;

        if ($birth_date === null) {
            return null;
        }

        return $age;
    }

    // get the url for Facebook Share dummy page
    protected function getFBShareDummyPage($type, $id, $lang = null) {
        $oldRouteSessionConfigValue = Config::get('orbit.session.availability.query_string');
        Config::set('orbit.session.availability.query_string', false);

        $url = '';
        switch ($type) {
            case 'tenant':
                $url = URL::route('share-tenant', ['id' => $id, 'lang' => $lang]);
                break;
            case 'promotion':
                $url = URL::route('share-promotion', ['id' => $id, 'lang' => $lang]);
                break;
            case 'news':
                $url = URL::route('share-news', ['id' => $id, 'lang' => $lang]);
                break;
            case 'coupon':
                $url = URL::route('share-coupon', ['id' => $id, 'lang' => $lang]);
                break;
            case 'lucky-draw':
                $url = URL::route('share-lucky-draw', ['id' => $id, 'lang' => $lang]);
                break;
            case 'home':
                $url = URL::route('share-home');
                break;

            default:
                $url = '';
                break;
        }
        Config::set('orbit.session.availability.query_string', $oldRouteSessionConfigValue);

        return $url;
    }

    protected function quoteStr($str)
    {
        return DB::connection()->getPdo()->quote($str);
    }

    protected function getMerchantLanguage($mall, $languageId = null)
    {
        $merchantLanguage = MerchantLanguage::where('merchant_languages.merchant_id', '=', $mall->merchant_id)
                                            ->where('merchant_languages.language_id', '=', $languageId)
                                            ->first();
        if (!is_object($merchantLanguage)) {
            $merchantLanguage = $this->getDefaultLanguage($mall);
        }
        return $merchantLanguage;
    }


    /**
     * Returns an appropriate MerchantLanguage (if any) that the user wants and the mall supports.
     *
     * @param \Mall $mall the mall
     * @return \MerchantLanguage the language or null if a matching one is not found.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    protected function getDefaultLanguage($mall)
    {
        $defaultLanguageStr = 'en';
        $mallDefaultLanguageStr = $mall->mobile_default_language;
        if (! empty($mallDefaultLanguageStr)) {
            $defaultLanguageStr = $mallDefaultLanguageStr;
        }
        $language = \Language::where('name', '=', $defaultLanguageStr)->first();
        if(isset($language) && count($language) > 0){
            $defaultLanguage = MerchantLanguage::
                where('merchant_id', '=', $mall->merchant_id)
                ->where('language_id', '=', $language->language_id)
                ->first();
            if ($defaultLanguage !== null) {
                return $defaultLanguage;
            }
        }

        // above methods did not result in any selected language, use mall default
        return null;
    }

    protected function getLoggedInUser($mallId)
    {
        $this->session = SessionPreparer::prepareSession();

        $userId = $this->session->read('user_id');

        // @todo: Why we query membership also? do we need it on every page?
        $user = User::with(['userDetail',
            'membershipNumbers' => function($q) use ($mallId) {
                $q->select('membership_numbers.*')
                    ->with('membership.media')
                    ->join('memberships', 'memberships.membership_id', '=', 'membership_numbers.membership_id')
                    ->excludeDeleted('membership_numbers')
                    ->excludeDeleted('memberships')
                    ->where('memberships.merchant_id', $mallId);
            }])
            ->where('user_id', $userId)
            ->whereHas('role', function($q) {
                $q->where('role_name', 'Consumer');
            })
            ->first();

        if (! $user) {
            $user = $this->getLoggedInGuest($this->session);
        } else {
            $_user = clone($user);
            if (count($_user->membershipNumbers)) {
               $user->membership_number = $_user->membershipNumbers[0]->membership_number;
            }
        }

        return $user;
    }

    /**
     * Get current guest user from session.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @return User $user
     */
    protected function getLoggedInGuest($session)
    {
        $userId = $session->read('guest_user_id');

        $generateGuest = function ($session) {
            $user = GuestUserGenerator::create()->generate();

            $sessionData = $session->read(NULL);
            $sessionData['logged_in'] = TRUE;
            $sessionData['guest_user_id'] = $user->user_id;
            $sessionData['guest_email'] = $user->user_email;
            $sessionData['role'] = $user->role->role_name;
            $sessionData['fullname'] = '';

            $session->update($sessionData);

            return $user;
        };

        if (! empty($userId)) {
            $user = User::with('userDetail')
                ->where('user_id', $userId)
                ->whereHas('role', function($q) {
                    $q->where('role_name', 'guest');
                })
                ->first();

            if (! is_object($user)) {
                $user = $generateGuest($session);
            }
        } else {
            $user = $generateGuest($session);
        }

        return $user;
    }

    protected function viewItemUserUpdate($type, $user, $retailer)
    {
        $viewedItems = [];
        $now = Carbon::now($retailer->timezone->timezone_name);
        $mallid = $retailer->merchant_id;

        if ($type == 'tenant') {
            $newTenantsCount = Tenant::active()
                ->where('parent_id', $retailer->merchant_id)
                ->lists('merchant_id');

            foreach ($newTenantsCount as $counter) {
                $viewedItems[] = array(
                        'item_id' => $counter,
                        'user_id' => $user->user_id,
                        'mall_id' => $retailer->merchant_id,
                        'item_type' => 'tenant',
                        'created_at' => Carbon::now($retailer->timezone->timezone_name),
                        'updated_at' => NULL
                    );
            }
        }
        if ($type == 'service') {
            $newTenantsCount = \TenantStoreAndService::active()
                ->where('object_type', 'service')
                ->where('parent_id', $retailer->merchant_id)
                ->lists('merchant_id');

            foreach ($newTenantsCount as $counter) {
                $viewedItems[] = array(
                        'item_id' => $counter,
                        'user_id' => $user->user_id,
                        'mall_id' => $retailer->merchant_id,
                        'item_type' => 'service',
                        'created_at' => Carbon::now($retailer->timezone->timezone_name),
                        'updated_at' => NULL
                    );
            }
        }
        if ($type == 'promotion') {

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            // get all new news after new_date filter by age range and gender
            $newPromotionsCount = News::select('news.news_id')
                            // active()
                            ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
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
                $newPromotionsCount->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $newPromotionsCount->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $newPromotionsCount->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $newPromotionsCount->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            $newPromotionsCount->where('news.status', '=', 'active')
                        ->where('news.object_type', 'promotion')
                        ->whereRaw("? between begin_date and end_date", [$now]);

            $promotionData = $newPromotionsCount->get();

            foreach ($promotionData as $counter) {
                $viewedItems[] = array(
                        'item_id' => $counter->news_id,
                        'user_id' => $user->user_id,
                        'mall_id' => $retailer->merchant_id,
                        'item_type' => 'promotion',
                        'created_at' => Carbon::now($retailer->timezone->timezone_name),
                        'updated_at' => NULL
                    );
            }
        }
        if ($type == 'news') {

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            // get all new news after new_date filter by age range and gender
            $newNewsCount = \News::select('news.news_id')
                            // active()
                            ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
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
                $newNewsCount->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $newNewsCount->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $newNewsCount->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $newNewsCount->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            $newNewsCount->where('news.status', '=', 'active')
                        ->where('news.object_type', 'news')
                        ->whereRaw("? between begin_date and end_date", [$now]);

            $newsData = $newNewsCount->get();

            foreach ($newsData as $counter) {
                $viewedItems[] = array(
                        'item_id' => $counter->news_id,
                        'user_id' => $user->user_id,
                        'mall_id' => $retailer->merchant_id,
                        'item_type' => 'news',
                        'created_at' => Carbon::now($retailer->timezone->timezone_name),
                        'updated_at' => NULL
                    );
            }
        }
        if ($type == 'coupon') {
            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $newCoupons = Coupon::select('promotions.promotion_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                            ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                            ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id');

            if ($userGender !== null) {
                $newCoupons->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $newCoupons->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $newCoupons->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $newCoupons->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }
            $prefix = DB::getTablePrefix();
            $merchant_id = $retailer->merchant_id;
            $user_id = $user->user_id;
            $quote = function ($arg) {
                return DB::connection()->getPdo()->quote($arg);
            };
            $newCoupons->join('promotion_rules', function($join) {
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
                    AND mall_id = {$quote($merchant_id)}
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

            $couponData = $newCoupons->get();

            foreach ($couponData as $counter) {
                $viewedItems[] = array(
                        'item_id' => $counter->promotion_id,
                        'user_id' => $user->user_id,
                        'mall_id' => $retailer->merchant_id,
                        'item_type' => 'coupon',
                        'created_at' => Carbon::now($retailer->timezone->timezone_name),
                        'updated_at' => NULL
                    );
            }
        }
        if ($type == 'lucky_draw') {
            $newLuckydrawsCount = LuckyDraw::active()
                ->where('mall_id', $retailer->merchant_id)
                ->whereRaw("? between start_date and grace_period_date", [$now])
                ->lists('lucky_draw_id');

            foreach ($newLuckydrawsCount as $counter) {
                $viewedItems[] = array(
                        'item_id' => $counter,
                        'user_id' => $user->user_id,
                        'mall_id' => $retailer->merchant_id,
                        'item_type' => 'lucky_draw',
                        'created_at' => Carbon::now($retailer->timezone->timezone_name),
                        'updated_at' => NULL
                    );
            }
        }

        foreach ($viewedItems as $item) {
            $insertViewedItems = new \ViewItemUser();
            $insertViewedItems->item_id = $item['item_id'];
            $insertViewedItems->user_id = $item['user_id'];
            $insertViewedItems->mall_id = $item['mall_id'];
            $insertViewedItems->item_type = $item['item_type'];
            $insertViewedItems->save();
        }
    }

    public function getRetailerInfo($with = null)
    {
        try {
            $retailer_id = App::make('orbitSetting')->getSetting('current_retailer'); //<pre>EXs5F-TKS-------

            $retailer = Mall::with('parent')->where('merchant_id', $retailer_id);
            if (! is_null($with)) {
                $with = (array) $with;
                foreach ($with as $rel) {
                    $retailer->with($rel);
                }
            }
            $retailer = $retailer->first();

            $membership_card = Setting::where('setting_name','enable_membership_card')->where('object_id',$retailer_id)->first();

            if (! empty($membership_card)){
                $retailer->enable_membership=$membership_card->setting_value;
            } else {
                $retailer->enable_membership='false';
            }

            return $retailer;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
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