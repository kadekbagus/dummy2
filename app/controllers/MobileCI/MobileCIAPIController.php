<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Log;
use Net\MacAddr;
use Orbit\Helper\Email\MXEmailChecker;
use Orbit\Helper\Net\Domain;
use Orbit\Helper\Net\UrlChecker as UrlBlock;
use Orbit\Helper\Net\GuestUserGenerator;
use Orbit\CloudMAC;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Orbit\Helper\Exception\UrlException;
use \View;
use \User;
use \Token;
use \UserDetail;
use \Role;
use \Lang;
use \Language;
use \MerchantLanguage;
use \Apikey;
use \Validator;
use \Config;
use \Retailer;
use \Product;
use \Widget;
use \EventModel;
use \Promotion;
use \Coupon;
use \CartCoupon;
use \IssuedCoupon;
use Carbon\Carbon as Carbon;
use \stdclass;
use \Category;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use Orbit\Helper\Session\AppOriginProcessor;
use \Cart;
use \CartDetail;
use \Exception;
use \DB;
use \Activity;
use \Transaction;
use \TransactionDetail;
use \TransactionDetailPromotion;
use \TransactionDetailCoupon;
use \TransactionDetailTax;
use \LuckyDraw;
use \LuckyDrawNumber;
use \LuckyDrawNumberReceipt;
use \LuckyDrawReceipt;
use \LuckyDrawWinner;
use \Setting;
use URL;
use PDO;
use Response;
use LuckyDrawAPIController;
use OrbitShop\API\v1\Helper\Generator;
use Event;
use \Mall;
use \Tenant;
use Orbit\Helper\Security\Encrypter;
use Redirect;
use Cookie;
use \Inbox;
use \News;
use \Object;
use \App;
use \Media;
use Artdarek\OAuth\Facade\OAuth;
use UserSignin;
use \WidgetClick;
use \WidgetGroupName;
use \Hash;
use \UserGuest;
use ListConnectedUser;
use Helper\EloquentRecordCounter as RecordCounter;
use MobileCI\ExCaptivePortalController as CaptivePortalController;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Database\ObjectID;
use Str;
use Orbit\Helper\Net\SignInRecorder;
use Orbit\Controller\API\v1\Pub\ActivationAPIController;

class MobileCIAPIController extends BaseCIController
{
    const APPLICATION_ID = 1;
    const PAYLOAD_KEY = '--orbit-mall--';
    protected $session = null;

    /**
     * POST - Login customer in shop
     *
     * @param string    `email`          (required) - Email address of the user
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postLoginInShop()
    {
        try {
            $email = trim(OrbitInput::post('email'));
            $password = OrbitInput::post('password');
            $password_confirmation = OrbitInput::post('password_confirmation');
            $payload = OrbitInput::post('payload');
            $mode = OrbitInput::post('mode');
            $socmed_redirect_to = OrbitInput::post('socmed_redirect_to');

            if (Config::get('orbit.shop.guest_mode')) {
                $guest = User::whereHas('role', function ($q) {
                    $q->where('role_name', 'Guest');
                })->excludeDeleted()->first();

                if(! is_object($guest)) {
                    throw new Exception('Guest user not configured properly.');
                }

                $email = $guest->user_email;
            }

            if (trim($email) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (! static::isValidMX($email)) {
                $errorMessage = \Lang::get('validation.email', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $payloadFromReg = $this->validateRegistrationData($email, $password, $password_confirmation);

            $retailer = $this->getRetailerInfo();

            $this->beginTransaction();
            $user = User::with('apikey', 'userdetail', 'role')
                        ->excludeDeleted()
                        ->where('user_email', $email)
                        ->whereHas(
                            'role',
                            function ($query) {
                                $query->where(function ($q) {
                                    $q->where('role_name', 'Consumer');
                                    $q->orWhere('role_name', 'Guest');
                                });
                            }
                        )->sharedLock()
                        ->first();

            // attempt to force cloud login scenario when using single-db
            // if there is no association between the user and this mall
            //
            // for guests do not do this, guest users do not have UserAcquisition
            // and are synced to every box even if acquisition not present.
            if (is_object($user) && strtolower($user->role->role_name) != 'guest') {
                // the login form should send the mode for login
                // if login check the password
                if($mode === 'login') {
                    if (! Hash::check($password, $user->user_password)) {
                        $message = Lang::get('validation.orbit.access.loginfailed');
                        OrbitShopAPI::throwInvalidArgument($message);
                    }
                }
            }

            // if not from cloud callback we redirect to cloud if pending so cloud
            // can resend activation email.
            // if from cloud callback we do not redirect to cloud again
            // cloud callback sends apikey_id (and other ids) in GET
            $from_cloud_callback = OrbitInput::get('apikey_id', null) !== null;
            if (! is_object($user) || ($user->status === 'pending' && !$from_cloud_callback) ) {
                if($mode === 'login') {
                    $this->linkGuestToUser($user);
                    return $this->loginStage2($user, $retailer);
                }
                if (empty($payload)) {
                    $payload = $payloadFromReg;
                }
                return $this->redirectToCloud($email, $password, $retailer, $payload, '', OrbitInput::post('mac_address', ''));
            } else {
                $this->linkGuestToUser($user);
                return $this->loginStage2($user, $retailer);
            }
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getMessage(), $e->getFile(), $e->getLine()];

            $this->rollback();
        }

        return $this->render();
    }

    /**
     * GET - Logout customer in shop
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Redirect
     */
    public function getLogoutInShop()
    {
        try {
            $this->prepareSession();
            $this->session->start(array(), 'no-session-creation');
            $this->session->destroy();
        } catch (Exception $e) {
        }

        $after_logout_url = Config::get('orbit.shop.after_logout_url', '/customer');
        return \Redirect::to($after_logout_url);
    }

    private function prepareWidgetTenantData($widget, $user, $retailer, $mallid, $now)
    {
        // get all tenant count
        $tenantsCount = Tenant::active()
            ->where('parent_id', $retailer->merchant_id)
            ->count();

        // get all new tenant after new_date
        $newTenantsCount = Tenant::active()
            ->where('parent_id', $retailer->merchant_id)
            ->whereNotIn('merchants.merchant_id', function($q) use ($user, $retailer) {
                $q->select('item_id')
                    ->from('viewed_item_user')
                    ->where('user_id', '=', $user->user_id)
                    ->where('mall_id', '=', $retailer->merchant_id)
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
        $widget->url = UrlBlock::blockedRoute('ci-tenant-list', [], $this->session);
        $widget->redirect_url = URL::route('ci-tenant-list');

        return $widget;
    }

    private function prepareWidgetServiceData($widget, $user, $retailer, $mallid, $now)
    {
        // get all tenant count
        $serviceCount = \TenantStoreAndService::where('status', 'active')
            ->where('object_type', 'service')
            ->where('parent_id', $retailer->merchant_id)
            ->count();

        // get all new tenant after new_date
        $newServiceCount = \TenantStoreAndService::where('status', 'active')
            ->where('object_type', 'service')
            ->where('parent_id', $retailer->merchant_id)
            ->whereNotIn('merchants.merchant_id', function($q) use ($user, $retailer) {
                $q->select('item_id')
                    ->from('viewed_item_user')
                    ->where('user_id', '=', $user->user_id)
                    ->where('mall_id', '=', $retailer->merchant_id)
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
        $widget->url = UrlBlock::blockedRoute('ci-service-list', [], $this->session);
        $widget->redirect_url = URL::route('ci-service-list');

        return $widget;
    }

    private function prepareWidgetPromotionData($widget, $user, $retailer, $mallid, $now)
    {
        $userAge = 0;
        if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
            $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
        }

        $userGender = 'U'; // default is Unknown
        if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
            $userGender =  $user->userDetail->gender;
        }

        // get all news count filter by age range and gender
        $promotionsCount = \News::select('news.news_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
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
        $newPromotionsCount = \News::select('news.news_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                        ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                        ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->where(function ($q) use ($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                              ->orWhere('merchants.merchant_id', '=', $mallid);
                        })
                        ->whereNotIn('news.news_id', function($q) use ($user, $retailer) {
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
        $widget->url = UrlBlock::blockedRoute('ci-promotion-list', [], $this->session);
        $widget->redirect_url = URL::route('ci-promotion-list');

        return $widget;
    }

    private function prepareWidgetNewsData($widget, $user, $retailer, $mallid, $now)
    {
        $userAge = 0;
        if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
            $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
        }

        $userGender = 'U'; // default is Unknown
        if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
            $userGender =  $user->userDetail->gender;
        }

        // get all news count filter by age range and gender
        $newsCount = \News::select('news.news_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
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
        $newNewsCount = \News::select('news.news_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                        ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                        ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->where(function ($q) use ($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                              ->orWhere('merchants.merchant_id', '=', $mallid);
                        })
                        ->whereNotIn('news.news_id', function($q) use ($user, $retailer) {
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
        $widget->url = UrlBlock::blockedRoute('ci-news-list', [], $this->session);
        $widget->redirect_url = URL::route('ci-news-list');

        return $widget;
    }

    private function prepareWidgetCouponData($widget, $user, $retailer, $mallid, $now)
    {
        $userAge = 0;
        if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
            $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
        }

        $userGender = 'U'; // default is Unknown
        if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
            $userGender =  $user->userDetail->gender;
        }

        $couponsCount = Coupon::select('promotions.promotion_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
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

        $couponsCount->join('promotion_rules', function($join) {
                $join->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')
                    ->where('promotions.is_coupon', '=', 'Y');
                })
            ->where('promotions.status', '=', 'active')
            ->where('promotions.begin_date', '<=', $now)
            ->where('promotions.end_date', '>=', $now)
            ->where('promotions.coupon_validity_in_date', '>=', $now)
            ->groupBy('promotions.promotion_id');
        $couponsCount = RecordCounter::create($couponsCount)->count();

        $newCouponsCount = Coupon::select('promotions.promotion_id')->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id');

        $prefix = DB::getTablePrefix();
        $merchant_id = $retailer->merchant_id;
        $user_id = $user->user_id;
        $quote = function ($arg) {
            return DB::connection()->getPdo()->quote($arg);
        };
        $newCouponsCount->join('promotion_rules', function($join) {
                $join->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')
                    ->where('promotions.is_coupon', '=', 'Y');
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
            ->where('promotions.begin_date', '<=', $now)
            ->where('promotions.end_date', '>=', $now)
            ->where('promotions.coupon_validity_in_date', '>=', $now)
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
        $widget->url = UrlBlock::blockedRoute('ci-coupon-list', [], $this->session);
        $widget->redirect_url = URL::route('ci-coupon-list');

        return $widget;
    }

    private function prepareWidgetLuckyDrawData($widget, $user, $retailer, $mallid, $now)
    {
        $luckydrawsCount = LuckyDraw::active()
            ->where('mall_id', $retailer->merchant_id)
            ->whereRaw("? between start_date and grace_period_date", [$now])
            ->count();

        $newLuckydrawsCount = LuckyDraw::active()
            ->where('mall_id', $retailer->merchant_id)
            ->whereRaw("? between start_date and grace_period_date", [$now])
            ->whereNotIn('lucky_draws.lucky_draw_id', function($q) use ($user, $retailer) {
                $q->select('item_id')
                    ->from('viewed_item_user')
                    ->where('user_id', '=', $user->user_id)
                    ->where('mall_id', '=', $retailer->merchant_id)
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
        $widget->url = UrlBlock::blockedRoute('ci-luckydraw-list', [], $this->session);
        $widget->redirect_url = URL::route('ci-luckydraw-list');

        return $widget;
    }

    private function prepareWidgetFreeWifiData($widget, $user, $retailer, $mallid, $now)
    {
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

        //$widget->always_show_subtitle = true;
        $widget->display_title = Lang::get('mobileci.widgets.free_wifi');
        //$widget->display_sub_title = Lang::get('mobileci.widgets.free_wifi');
        $widget->url = UrlBlock::blockedRoute('captive-request-internet', [], $this->session);
        $widget->redirect_url = URL::route('captive-request-internet');

        return $widget;
    }

    private function prepareWidgetData($widget, $user, $retailer, $mallid, $now)
    {
        switch ($widget->widget_type) {
            case 'tenant':
                return $this->prepareWidgetTenantData($widget, $user, $retailer, $mallid, $now);
                break;
            case 'service':
                return $this->prepareWidgetServiceData($widget, $user, $retailer, $mallid, $now);
                break;
            case 'promotion':
                return $this->prepareWidgetPromotionData($widget, $user, $retailer, $mallid, $now);
                break;
            case 'news':
                return $this->prepareWidgetNewsData($widget, $user, $retailer, $mallid, $now);
                break;
            case 'coupon':
                return $this->prepareWidgetCouponData($widget, $user, $retailer, $mallid, $now);
                break;
            case 'lucky_draw':
                return $this->prepareWidgetLuckyDrawData($widget, $user, $retailer, $mallid, $now);
                break;
            case 'free_wifi':
                return $this->prepareWidgetFreeWifiData($widget, $user, $retailer, $mallid, $now);
                break;
        }
        return $widget;
    }

    /**
     * GET - Home page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * @return Illuminate\View\View
     */
    public function getHomeView()
    {
        $user = null;
        $retailer = null;
        $activityPage = Activity::mobileci()
                            ->setActivityType('view');
        try {
            if (CaptivePortalController::isFromCaptive() && CaptivePortalController::isSessionQueryExists()) {
                $sessionQueryName = Config::get('orbit.session.session_origin.query_string.name', 'orbit_session');
                CaptivePortalController::forceOverrideCookie( OrbitInput::get($sessionQueryName, NULL) );
            }

            $retailer = $this->getRetailerInfo('merchantSocialMedia');
            $from_main = OrbitInput::get('from_main', 'false');
            if ($from_main === 'true') {
                $landing_url = $this->getLandingUrl($retailer);

                return Redirect::to($landing_url);
            }

            /*Log::info(sprintf('-- CAPTIVE PORTAL -> SID: %s, Cookie: %s',
                    $this->session->getSessionId(), print_r($_COOKIE, TRUE)));*/

            $user = $this->getLoggedInUser();
            // check url, is it blocked or not
            UrlBlock::checkBlockedUrl($user);
            $this->acquireUser($retailer, $user);


            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $widget_flags = new stdclass();
            $widget_flags->enable_coupon = $this->getObjFromArray($retailer->settings, 'enable_coupon');
            $widget_flags->enable_coupon_widget = $this->getObjFromArray($retailer->settings, 'enable_coupon_widget');
            $widget_flags->enable_lucky_draw = $this->getObjFromArray($retailer->settings, 'enable_lucky_draw');
            $widget_flags->enable_lucky_draw_widget = $this->getObjFromArray($retailer->settings, 'enable_lucky_draw_widget');

            $widget_template_id = ! $this->getObjFromArray($retailer->settings, 'widget_template') ? NULL : $this->getObjFromArray($retailer->settings, 'widget_template')->setting_value;
            if (! empty($widget_template_id)) {
                $template = \WidgetTemplate::active()->where('widget_template_id', $widget_template_id)->first();
                if (! is_object($template)) {
                    $widget_template = 'default';
                } else {
                    $widget_template = $template->template_file_name;
                }
            } else {
                $widget_template = 'default';
            }

            $merchantId = $retailer->merchant_id;
            $prefix = DB::getTablePrefix();

            $widgets = Widget::select('widgets.*')
                            ->with('media')
                            ->leftJoin(DB::raw("(SELECT setting_id, setting_name, setting_value, object_id
                                        FROM {$prefix}settings
                                        WHERE setting_name like '%widget%'
                                            AND object_id = '{$merchantId}') AS os"),
                                // On
                                DB::raw('os.setting_name'), '=', DB::raw("CONCAT('enable_', {$prefix}widgets.widget_type, '_widget')"))
                            ->join('widget_retailer', 'widget_retailer.widget_id', '=', 'widgets.widget_id')
                            ->where('widgets.status', '=', 'active')
                            ->where('widgets.merchant_id', '=', $merchantId)
                            ->whereRaw("(CASE WHEN os.setting_id IS NULL THEN 'true' ELSE os.setting_value END) = 'true'")
                            ->groupBy('widgets.widget_type')
                            ->orderBy('widgets.widget_order', 'ASC')
                            ->get();

            if (CaptivePortalController::isFromCaptive()) {
                //not needed because free wifi internet widget is configurable in
                //mall setting
                // Inject number of widget on-the-fly
                //$captiveWidget = CaptivePortalController::generateDummyWidget($retailer, $session);
                //$widgets->push($captiveWidget);

                // Push the from_captive cookie
                CaptivePortalController::setCookieForCaptive();
            }

            $now = Carbon::now($retailer->timezone->timezone_name);

            $mallid = $retailer->merchant_id;

            foreach ($widgets as $widget) {
                $this->prepareWidgetData($widget, $user, $retailer, $mallid, $now);
            }

            $languages = $this->getListLanguages($retailer);

            // set mall facebook page url
            $retailer->facebook_like_url = '';
            if (count($retailer->merchantSocialMedia) > 0) {
                foreach ($retailer->merchantSocialMedia as $merchantSocialMedia) {
                    if ($merchantSocialMedia->socialMedia->social_media_code === 'facebook') {
                        if (! empty($merchantSocialMedia->social_media_uri)) {
                            $retailer->facebook_like_url = '//' . $merchantSocialMedia->socialMedia->social_media_main_url . '/' . $merchantSocialMedia->social_media_uri;
                        }
                    }
                }
            }
            // set mall facebook share url
            $retailer->facebook_share_url = $this->getFBShareDummyPage('home', NULL);

            $activityPageNotes = sprintf('Page viewed: %s', 'Home');
            $activityPage->setUser($user)
                ->setLocation($retailer)
                ->setActivityName('view_page_home')
                ->setActivityNameLong('View (Home Page)')
                ->setObject(null)
                ->setNotes($activityPageNotes)
                ->setModuleName('Widget')
                ->responseOK()
                ->save();

            $pokestopData = News::excludeDeleted()
                        ->where('object_type','pokestop')
                        ->where('mall_id',$retailer->merchant_id)
                        ->get();

            $data = array(
                'page_title' => null,
                'retailer' => $retailer,
                'widgets' => $widgets,
                'widget_flags' => $widget_flags,
                'languages' => $languages,
                'active_user' => ($user->status === 'active'),
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'user' => $user,
                'facebookInfo' => Config::get('orbit.social_login.facebook'),
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                'pokestops' => $pokestopData,
            );

            // check view file existance, if not fallback to default
            if (View::exists('mobile-ci.templates.widget.' . $widget_template)) {
                return View::make('mobile-ci.templates.widget.' . $widget_template, $data);
            } else {
                return View::make('mobile-ci.templates.widget.default', $data);
            }
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Home');
            $activityPage->setUser($user)
                ->setLocation($retailer)
                ->setActivityName('view_page_home')
                ->setActivityNameLong('View (Home Page) Failed')
                ->setObject(null)
                ->setModuleName('Widget')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Sign in page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
    public function getSignInView()
    {
        $bg = null;
        $start_button_label = Config::get('shop.start_button_label');

        $googlePlusUrl = URL::route('mobile-ci.social_google_callback');

        if (\Input::get('payload', false) !== false) {
            // has payload, clear out prev cookies
            $_COOKIE['orbit_firstname'] = '';
            $_COOKIE['orbit_email'] = '';
        }

        $mac = \Input::get('mac_address', '');
        /** @var \MacAddress $mac_model */
        $mac_model = null;
        if ($mac !== '') {
            $mac_model = \MacAddress::excludeDeleted()->with('user')->where('mac_address', $mac)->orderBy('mac_addresses.created_at', 'desc')->first();
            if (! empty($mac_model)) {
                $loginFrom = UserSignin::where('user_id', $mac_model->user->user_id)->orderBy('created_at', 'desc')->first();
            }
        }

        $user = $this->getLoggedInUser();
        $landing_url = UrlBlock::blockedRoute('ci-customer-home', [], $this->session);
        $socmed_redirect_to = \Input::get('socmed_redirect_to', '');
        if (! empty($socmed_redirect_to)) {
            $landing_url = $socmed_redirect_to;
        }

        $cookie_fname = isset($_COOKIE['orbit_firstname']) ? $_COOKIE['orbit_firstname'] : (! empty($mac_model) ? $mac_model->user->user_firstname : '');
        $cookie_email = isset($_COOKIE['orbit_email']) ? $_COOKIE['orbit_email'] : (! empty($mac_model) ? $mac_model->user->user_email : '');
        $cookie_lang = isset($_COOKIE['orbit_preferred_language']) ? $_COOKIE['orbit_preferred_language'] : '';
        $cookie_login_from = isset($_COOKIE['login_from']) ? $_COOKIE['login_from'] : (! empty($loginFrom) ? ucfirst($loginFrom->signin_via) : 'Form');
        $display_name = '';
        $error = \Input::get('error') !== '' ? \Input::get('error') : 'No Error';
        $isInProgress = \Input::get('isInProgress') !== '' ? \Input::get('isInProgress') : false;

        if (! empty($cookie_email)) {
            $display_name = $cookie_email;
        }

        if (! empty($cookie_fname)) {
            $display_name = $cookie_fname;
        }

        $languages = [];

        $internet_info = 'no';
        $viewData = [
            'orbitTime' => time(),
            'googlePlusUrl' => $googlePlusUrl,
            'orbitOriginName' => 'orbit_origin',
            'orbitOriginValue' => 'default',
            'orbitToFacebookOriginValue' => 'redirect_to_facebook',
            'agreeToTermsLabel' => sprintf(
                Lang::get('mobileci.signin.agree_to_terms'),
                htmlspecialchars(Config::get('orbit.contact_information.privacy_policy_url')),
                htmlspecialchars(Config::get('orbit.contact_information.terms_of_service_url')))
        ];

        try {
            $retailer = $this->getRetailerInfo();

            $merchant_language_id = null;

            $languages = $this->getListLanguages($retailer);
            try {
                if (! empty($cookie_lang)) {
                    foreach ($languages as $idx => $language) {
                        if ($cookie_lang === $language->language->name) {
                            $merchant_language_id = $language->merchant_language_id;
                        }
                    }
                }
            } catch (Exception $e) {
            }

            $mall = Mall::with(array('settings.translations' => function($q) use ($merchant_language_id) {
                    $q->where('setting_translations.merchant_language_id', $merchant_language_id);
                }))->where('merchant_id', $retailer->merchant_id)
                ->first();

            //get internet_info from setting
            $internet_info_obj = $this->getObjFromArray($retailer->settings, 'internet_info');

            if (is_object($internet_info_obj)) {
                $internet_info = $internet_info_obj->setting_value;
            }

            $landing_url = $this->getLandingUrl($mall);

            $socmed_redirect_to = \Input::get('socmed_redirect_to', '');
            if (! empty($socmed_redirect_to)) {
                $landing_url = $socmed_redirect_to;
            }

            try {
                $bg = Media::where('object_id', $retailer->merchant_id)
                    ->where('media_name_id', 'retailer_background')
                    ->where('media_name_long', 'retailer_background_orig')
                    ->where('object_name', 'mall')
                    ->first();
            } catch (Exception $e) {
            }

            // Captive Portal Apple CNA Window
            // -------------------------------
            // Payload login is set and the user is logged in, no need to ask user log in again
            // assuming they was already asked on CNA captive
            if (isset($_GET['payload_login'])) {
                $payloadData = $this->proceedPayloadData();
                Cookie::forever('orbit_email', $payloadData['email'], '/', NULL, FALSE, FALSE);
                Cookie::forever('orbit_firstname', $payloadData['fname'], '/', NULL, FALSE, FALSE);

                return Redirect::to($this->addParamsToUrl($landing_url, $internet_info));
            }

            // Get email from query string
            $loggedUser = $this->getLoggedInUser();
            $user_email = $loggedUser->user_email;

            $viewData = array_merge($viewData, array(
                'retailer' => $retailer,
                'user_email' => htmlentities($user_email),
                'bg' => $bg,
                'landing_url' => $this->addParamsToUrl($landing_url, $internet_info),
                'display_name' => $display_name,
                'login_from' => $cookie_login_from,
                'languages' => $languages,
                'start_button_login' => $start_button_label,
                'mac' => $mac,
                'error' => $error,
                'isInProgress' => $isInProgress,
            ));
        } catch (Exception $e) {
            $retailer = $this->getRetailerInfo();

            $user_email = OrbitInput::get('email', $cookie_email);

            $viewData = array_merge($viewData, array(
                'retailer' => $retailer,
                'user_email' => htmlentities($user_email),
                'bg' => $bg,
                'landing_url' => $this->addParamsToUrl($landing_url, $internet_info),
                'display_name' => $display_name,
                'login_from' => $cookie_login_from,
                'languages' => $languages,
                'start_button_login' => $start_button_label,
                'mac' => $mac,
                'error' => $error,
                'isInProgress' => $isInProgress,
            ));
        }

        return View::make('mobile-ci.signin', $viewData);
    }

    /**
     * Handles social login POST
     */
    public function postSocialLoginView()
    {
        $agree_to_terms = \Input::get('agree_to_terms', 'no');
        $caller_url = OrbitInput::post('from_url', 'ci-home');
        $redirect_to_url = OrbitInput::post('to_url', URL::route('ci-customer-home'));

        if ($agree_to_terms !== 'yes') {
            return Redirect::route('mobile-ci.signin', ['error' => Lang::get('captive-portal.signin.must_accept_terms')]);
        }

        $this->prepareSession();

        $fb = new \Facebook\Facebook([
            'persistent_data_handler' => new \Orbit\FacebookSessionAdapter($this->session),
            'app_id' => Config::get('orbit.social_login.facebook.app_id'),
            'app_secret' => Config::get('orbit.social_login.facebook.app_secret'),
            'default_graph_version' => Config::get('orbit.social_login.facebook.version')
        ]);

        $helper = $fb->getRedirectLoginHelper();
        $permissions = Config::get('orbit.social.facebook.scope', ['email', 'public_profile']);
        $facebookCallbackUrl = URL::route('mobile-ci.social_login_callback',
            [
                'caller_url' => $caller_url ,
                'orbit_origin' => 'facebook',
                'from_captive' => OrbitInput::post('from_captive'),
                'mac_address' => \Input::get('mac_address', ''),
                'redirect_to_url' => $redirect_to_url
            ]);

        // This is to re-popup the permission on login in case some of the permissions revoked by user
        $rerequest = '&auth_type=rerequest';

        $url = $helper->getLoginUrl($facebookCallbackUrl, $permissions) . $rerequest;

        // No need to grant temporary https access anymore, we are using dnsmasq --ipset features for walled garden
        // $this->grantInternetAcces('social');

        return Redirect::to($url);
    }

    protected function getFacebookError()
    {
        // error=access_denied&
        // error_code=200&
        // error_description=Permissions+error
        // &error_reason=user_denied
        // &state=28d0463ac4dc53131ae19826476bff74#_=_
        $fbError = 'Unknown Error';

        $errorDesc = \Input::get('error_description', NULL);
        if (! is_null($errorDesc)) {
            $fbError = $errorDesc;
        }

        $errorMessage = 'Facebook Error: ' . $fbError;
        return Redirect::route('ci-home', ['error' => $errorMessage, 'isInProgress' => 'true']);
    }

    /**
     * Handle google account POST and callback
     *
     * @author shelgi <shelgi@dominopos.com>
     * @deprecated
     * @return void
     *
     */
    public function getGoogleCallbackView()
    {
        $oldRouteSessionConfigValue = Config::get('orbit.session.availability.query_string');
        Config::set('orbit.session.availability.query_string', false);

        $recognized = \Input::get('recognized', 'none');
        $code = \Input::get('code', NULL);
        $state = \Input::get('state', NULL);
        $caller_url = OrbitInput::get('from_url', 'ci-customer-home');
        $mall_id = OrbitInput::get('mall_id', NULL);
        $redirect_to_url = OrbitInput::get('to_url', URL::route('ci-customer-home'));
        $userEmail = OrbitInput::get('email', '');
        $firstName = OrbitInput::get('first_name', '');
        $lastName = OrbitInput::get('last_name', '');
        $gender = OrbitInput::get('gender', '');
        $socialid = OrbitInput::get('socialid', '');

        // todo handle google error
        if ( !empty( $code ) ) {
            try {
                $data = [
                    'email' => $userEmail,
                    'fname' => $firstName,
                    'lname' => $lastName,
                    'gender' => $gender,
                    'login_from'  => 'google',
                    'social_id'  => $socialid,
                    'mac' => \Input::get('mac_address', ''),
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'is_captive' => 'yes',
                    'recognized' => $recognized
                ];

                $orbit_origin = \Input::get('orbit_origin', 'google');
                $this->session = SessionPreparer::prepareSession();

                // There is a chance that user not 'grant' his email while approving our app
                // so we double check it here
                if (empty($userEmail)) {
                    return Redirect::route('ci-customer-home', ['error' => 'Email is required.']);
                }

                $key = $this->getPayloadEncryptionKey();
                $payload = (new Encrypter($key))->encrypt(http_build_query($data));
                $query = ['payload' => $payload, 'email' => $userEmail, 'auto_login' => 'yes', 'isInProgress' => 'true'];
                if (\Input::get('from_captive') === 'yes') {
                    $query['from_captive'] = 'yes';
                }

                $retailer = Mall::excludeDeleted()->where('merchant_id', $mall_id)->first();
                if (empty($retailer)) {
                    return Redirect::route('ci-customer-home', ['error' => 'Mall not found.']);
                }
                $loggedInUser = $this->doAutoLogin($userEmail);
                if (is_object($loggedInUser)) {
                    $this->loginStage2($loggedInUser, $retailer);
                    $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

                    setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                    setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                    setcookie('login_from', 'Google', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

                    SignInRecorder::setSignInActivity($loggedInUser, 'google', $retailer, NULL, TRUE);

                    // special session value for visited malls within single session
                    $session = $this->session;
                    $session->write('visited_location', [$retailer->merchant_id]);

                    $redirect_to_url_from_state = $this->remove_querystring_var(json_decode($this->base64UrlDecode($state))->redirect_to_url, $this->getOrbitSessionQueryStringName());
                    // $redirect_to_url_from_state = $this->add_querystring_var($redirect_to_url_from_state, $this->getOrbitSessionQueryStringName(), $this->session->getSessionId());

                    return Redirect::to($redirect_to_url_from_state);
                } else {
                    // register user without password and birthdate
                    $_POST['email'] = $userEmail;
                    $_POST['firstname'] = $firstName;
                    $_POST['lastname'] = $lastName;
                    $_POST['gender'] = $gender;
                    $_POST['status'] = 'active';
                    $_POST['mall_id'] = $mall_id;
                    $_POST['sign_up_origin'] = 'google';
                    $response = \LoginAPIController::create('raw')->setUseTransaction(false)->postRegisterUserInShop();
                    if ($response->code !== 0) {
                        throw new Exception($response->message, $response->code);
                    }

                    $loggedInUser = $this->doAutoLogin($response->data->user_email);
                    $this->linkGuestToUser($loggedInUser);
                    $this->loginStage2($loggedInUser, $retailer);

                    $expireTime = Config::get('orbit.session.session_origin.cookie.expire');
                    setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                    setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                    setcookie('login_from', 'Google', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

                    $this->acquireUser($retailer, $loggedInUser, 'google');
                    // create activation_ok activity without using token
                    $activation_ok = ActivationAPIController::create('raw')
                        ->setSaveAsAutoActivation($response->data, 'google')
                        ->postActivateAccount();

                    $redirect_to_url_from_state = $this->remove_querystring_var(json_decode($this->base64UrlDecode($state))->redirect_to_url, $this->getOrbitSessionQueryStringName());

                    return Redirect::to($redirect_to_url_from_state);
                }

            } catch (Exception $e) {
                $errorMessage = 'Error: ' . $e->getMessage();
                return Redirect::route($caller_url, ['error' => $errorMessage]);
            }

        } else {
            $errorMessage = 'Error: Google sign in failed, empty code.';
            return Redirect::route($caller_url, ['error' => $errorMessage]);
        }
    }

    public function getSocialLoginCallbackView()
    {
        $recognized = \Input::get('recognized', 'none');
        $caller_url = \Input::get('caller_url', NULL);
        $caller_url = ! is_null($caller_url) ? $caller_url : 'ci-home';
        $redirect_to_url = \Input::get('redirect_to_url', URL::route('ci-customer-home'));
        // error=access_denied&
        // error_code=200&
        // error_description=Permissions+error
        // &error_reason=user_denied
        // &state=28d0463ac4dc53131ae19826476bff74#_=_
        $error = \Input::get('error', NULL);

        $city = '';
        $country = '';

        if (! is_null($error)) {
            return $this->getFacebookError();
        }

        $orbit_origin = \Input::get('orbit_origin', 'facebook');
        $this->prepareSession();

        $fb = new \Facebook\Facebook([
            'persistent_data_handler' => new \Orbit\FacebookSessionAdapter($this->session),
            'app_id' => Config::get('orbit.social_login.facebook.app_id'),
            'app_secret' => Config::get('orbit.social_login.facebook.app_secret'),
            'default_graph_version' => Config::get('orbit.social_login.facebook.version')
        ]);

        $helper = $fb->getRedirectLoginHelper();
        $accessToken = $helper->getAccessToken();
        $useExtended = Config::get('orbit.social.facebook.use_extended_perms');

        $query = '/me?fields=id,email,name,first_name,last_name,gender';
        if ($useExtended) {
            $query .= ',location,relationship_status,photos,work,education';
        }
        $response = $fb->get($query, $accessToken->getValue());
        $user = $response->getGraphUser();

        $userEmail = isset($user['email']) ? $user['email'] : '';
        $firstName = isset($user['first_name']) ? $user['first_name'] : '';
        $lastName = isset($user['last_name']) ? $user['last_name'] : '';
        $gender = isset($user['gender']) ? $user['gender'] : '';
        $socialid = isset($user['id']) ? $user['id'] : '';

        $data = [
            'email' => $userEmail,
            'fname' => $firstName,
            'lname' => $lastName,
            'gender' => $gender,
            'login_from'  => 'facebook',
            'social_id'  => $socialid,
            'mac' => \Input::get('mac_address', ''),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'is_captive' => 'yes',
            'recognized' => $recognized,
        ];
        $extendedData = [];

        if ($useExtended === TRUE) {
            $relationship = isset($user['relationship_status']) ? $user['relationship_status'] : '';
            $work = isset($user['work']) ? $user['work'][0]['employer']['name'] : '';
            $education = isset($user['education']) ? $user['education'][0]['type'] : '';

            if (isset($user['location']['name'])) {
                $location = explode(',', $user['location']['name']);
                $city = isset($location[0]) ? $location[0] : '';
                $country = isset($location[1]) ? $location[1] : '';
            }

            $extendedData = [
                'relationship'  => $relationship,
                'work'  => $work,
                'education'  => $education,
                'city'  => $city,
                'country'  => $country,
            ];
        }

        // Merge the standard and extended permission (if any)
        $data = $extendedData + $data;

        // There is a chance that user not 'grant' his email while approving our app
        // so we double check it here
        if (empty($userEmail)) {
            return Redirect::route($caller_url, ['error' => 'Email is required.', 'isInProgress' => 'true']);
        }

        $key = $this->getPayloadEncryptionKey();
        $payload = (new Encrypter($key))->encrypt(http_build_query($data));
        $query = ['payload' => $payload, 'email' => $userEmail, 'auto_login' => 'yes', 'isInProgress' => 'true'];
        if (\Input::get('from_captive') === 'yes') {
            $query['from_captive'] = 'yes';
        }

        $retailer = $this->getRetailerInfo();
        $loggedInUser = $this->doAutoLogin($userEmail);
        if (is_object($loggedInUser)) {
            $this->loginStage2($loggedInUser, $retailer);
            $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

            setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('login_from', 'Facebook', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

            SignInRecorder::setSignInActivity($loggedInUser, 'facebook', $retailer, NULL, TRUE);

            // special session value for visited malls within single session
            $session = $this->session;
            $session->write('visited_location', [$retailer->merchant_id]);

            $redirect_to_url = $this->remove_querystring_var($redirect_to_url, $this->getOrbitSessionQueryStringName());

            return Redirect::to($redirect_to_url);
        } else {
            // register user without password and birthdate
            $_POST['email'] = $userEmail;
            $_POST['firstname'] = $firstName;
            $_POST['lastname'] = $lastName;
            $_POST['gender'] = $gender;
            $_POST['sign_up_origin'] = 'facebook';
            $_POST['status'] = 'active';
            $response = \LoginAPIController::create('raw')->setUseTransaction(false)->postRegisterUserInShop();
            if ($response->code !== 0) {
                throw new Exception($response->message, $response->code);
            }

            $loggedInUser = $this->doAutoLogin($response->data->user_email);
            $this->loginStage2($loggedInUser, $retailer);

            $expireTime = Config::get('orbit.session.session_origin.cookie.expire');
            setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('login_from', 'Facebook', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

            $this->acquireUser($retailer, $loggedInUser, 'facebook');

            // create activation_ok activity without using token
            $activation_ok = ActivationAPIController::create('raw')
                ->setSaveAsAutoActivation($response->data, 'facebook')
                ->postActivateAccount();

            $redirect_to_url = $this->remove_querystring_var($redirect_to_url, $this->getOrbitSessionQueryStringName());

            return Redirect::to($redirect_to_url);
        }
    }

    /**
     * GET - Welcome page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return Illuminate\View\View
     */
    public function getWelcomeView()
    {
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();
            $cartdata = $this->getCartForToolbar();
            $user_email = $user->user_email;

            return View::make('mobile-ci.welcome', array('retailer'=>$retailer, 'user'=>$user, 'cartdata' => $cartdata, 'user_email' => $user_email));
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Event pop up click activity
     *
     * @param integer    `eventdata`        (optional) - The event ID
     *
     * @return void
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postEventPopUpActivity()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = null;
        $event_id = null;
        $event = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $event_id = OrbitInput::post('eventdata');
            $event = EventModel::active()->where('event_id', $event_id)->first();

            $activityNotes = sprintf('Event Click. Event Id : %s', $event_id);
            $activity->setUser($user)
                ->setActivityName('event_click')
                ->setActivityNameLong('Event Click')
                ->setObject($event)
                ->setModuleName('Event')
                ->setEvent($event)
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (Exception $e) {
            $this->rollback();
            $activityNotes = sprintf('Event Click Failed. Event Id : %s', $event_id);
            $activity->setUser($user)
                ->setActivityName('event_click')
                ->setActivityNameLong('Event Click Failed')
                ->setObject(null)
                ->setModuleName('Event')
                ->setEvent($event)
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Event pop up display activity
     *
     * @param integer    `eventdata`        (optional) - The event ID
     *
     * @return void
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postDisplayEventPopUpActivity()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('view');
        $user = null;
        $event_id = null;
        $event = null;
        $retailer = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $event_id = OrbitInput::post('eventdata');
            $event = EventModel::active()->where('event_id', $event_id)->first();

            $activityNotes = sprintf('Event View. Event Id : %s', $event_id);
            $activity->setUser($user)
                ->setActivityName('event_view')
                ->setActivityNameLong('Event View (Pop Up)')
                ->setObject($event)
                ->setLocation($retailer)
                ->setModuleName('Event')
                ->setEvent($event)
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (Exception $e) {
            $this->rollback();
            $activityNotes = sprintf('Event View Failed. Event Id : %s', $event_id);
            $activity->setUser($user)
                ->setActivityName('event_view')
                ->setActivityNameLong('Event View Failed')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('Event')
                ->setEvent($event)
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Widget click activity
     *
     * @param integer    `widgetdata`        (optional) - The widget ID
     *
     * @return void
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Irianto Pratama <irianto@dominopos.com>
     */
    public function postClickWidgetActivity()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = null;
        $widget_id = null;
        $widget = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);

            $retailer = $this->getRetailerInfo();

            $widget_id = OrbitInput::post('widgetdata');

            $validator = Validator::make(
                array(
                    'widgetdata'             => $widget_id,
                ),
                array(
                    'widgetdata'             => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $widget = Widget::active()->where('widget_id', $widget_id)->first();
            $widget_type = ucwords(str_replace('_', ' ', $widget->widget_type));

            $activityNotes = sprintf('Widget Click. Widget Id : %s', $widget_id);
            $activity->setUser($user)
                ->setActivityName('widget_click')
                ->setActivityNameLong('Widget Click ' . $widget_type)
                ->setObject($widget)
                ->setModuleName('Widget')
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();

            $this->rollback();
        }

        return $this->render();
    }

    /**
     * POST - add to wallet
     *
     * @param string coupon_id
     *
     * @return string
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postAddToWallet()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = NULL;
        $coupon = NULL;
        $issuedCoupon = NULL;
        $retailer = NULL;
        $coupon_id = OrbitInput::post('coupon_id', NULL);

        try {
            $user = $this->getLoggedInUser();

            // guest cannot add to wallet
            if (! $user->isConsumer()) {
                $message = 'You must login to access this.';
                ACL::throwAccessForbidden($message);
            }

            $retailer = $this->getRetailerInfo();
            $this->registerCustomValidation();

            // check if coupon already add to wallet
            $wallet = IssuedCoupon::where('promotion_id', '=', $coupon_id)
                                  ->where('user_id', '=', $user->user_id)
                                  ->where('status', '=', 'active')
                                  ->first();

            if (is_object($wallet)) {
               $message = 'coupon already added to wallet';
               OrbitShopAPI::throwInvalidArgument($message);
            }

            $validator = Validator::make(
                array(
                    'coupon_id' => $coupon_id,
                ),
                array(
                    'coupon_id' => 'required|orbit.exists.coupon',
                ),
                array('orbit.exists.coupon' => Lang::get('validation.orbit.empty.coupon'))
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->beginTransaction();
            $coupon = Coupon::where('promotion_id', '=', $coupon_id)->first();
            $newIssuedCoupon = new IssuedCoupon();
            $issuedCoupon = $newIssuedCoupon->issue($coupon, $user->user_id, $user, $retailer);
            $this->commit();

            if ($issuedCoupon) {
                $this->response->message = 'Request Ok';
                $this->response->data = NULL;
                $activityNotes = sprintf('Added to wallet Coupon Id: %s. Issued Coupon Id: %s', $coupon->promotion_id, $issuedCoupon->issued_coupon_id);
                $activity->setUser($user)
                    ->setActivityName('click_add_to_wallet')
                    ->setActivityNameLong('Click Add To Wallet')
                    ->setLocation($retailer)
                    ->setObject($issuedCoupon)
                    ->setModuleName('Coupon')
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $this->response->message = 'Fail to issue coupon';
                $this->response->data = NULL;
            }

        } catch (ACLForbiddenException $e) {
            $coupon = Coupon::where('promotion_id', '=', $coupon_id)->first();

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('click_add_to_wallet')
                ->setActivityNameLong('Click Add To Wallet')
                ->setObject($coupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('click_add_to_wallet')
                ->setActivityNameLong('Failed to Click Add To Wallet')
                ->setObject($issuedCoupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('click_add_to_wallet')
                ->setActivityNameLong('Failed to Click Add To Wallet')
                ->setObject($issuedCoupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        }

        return $this->render();
    }

    /**
     * GET - My Account detail page
     *
     * @return Illuminate\View\View
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     */
    public function getMyAccountView()
    {
        $user = null;
        $media = null;
        $user_full_name = null;
        $retailer = null;

        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);

            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);

            $languages = $this->getListLanguages($retailer);
            $pageTitle = Lang::get('mobileci.page_title.my_account');

            $user_full_name = $user->getFullName();
            if (empty(trim($user_full_name))) {
                $user_full_name = $user->email;
            }

            $media = $user->profilePicture()
                        ->where('media_name_long', 'user_profile_picture_orig')
                        ->get();

            $activityPageNotes = sprintf('Page viewed: My Account, user Id: %s', $user->user_id);
            $activityPage->setUser($user)
                ->setActivityName('view_my_account')
                ->setActivityNameLong('View My Account')
                ->setLocation($retailer)
                ->setModuleName('My Account')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.mall-my-account',
                array(
                    'active_user' => ($user->status === 'active'),
                    'page_title' => $pageTitle,
                    'user_full_name' => $user_full_name,
                    'media' => $media,
                    'user' => $user,
                    'retailer' => $retailer,
                    'languages' => $languages,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                    'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                ));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: My Account');
            $activityPage->setUser($user)
                ->setActivityName('view_my_account')
                ->setActivityNameLong('View My Account Failed')
                ->setModuleName('My Account')
                ->setLocation($retailer)
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Campaign popup activity
     *
     * @param string    `campaign_type`    (optional) - Campaign type: news, promotion, coupon
     * @param char      `campaign_id`      (optional) - Campaign ID
     * @param string    `activity_type`    (optional) - Activity type: view, click
     *
     * @return void
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     */
    public function postCampaignPopUpActivities()
    {
        $activity = null;
        $user = null;

        $campaign_type = null;
        $campaign_id = null;
        $activity_type = null;
        $retailer = null;

        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);

            $retailer = $this->getRetailerInfo();

            $campaign_type = OrbitInput::post('campaign_type');
            $campaign_id   = OrbitInput::post('campaign_id');
            $activity_type = OrbitInput::post('activity_type');

            $validator = Validator::make(
                array(
                    'campaign_type' => $campaign_type,
                    'campaign_id'   => $campaign_id,
                    'activity_type' => $activity_type,
                ),
                array(
                    'campaign_type' => 'required|in:news,promotion,coupon',
                    'campaign_id'   => 'required',
                    'activity_type' => 'required|in:view,click',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $activity = Activity::mobileci()
                            ->setActivityType($activity_type);

            $campaign = null;
            if ($campaign_type === 'news' || $campaign_type === 'promotion') {
                $campaign = News::active()->where('news_id', $campaign_id)
                                          ->where('object_type', $campaign_type)
                                          ->first();
                $activity->setNews($campaign);
            }
            if ($campaign_type === 'coupon') {
                $campaign = Coupon::active()->where('promotion_id', $campaign_id)
                                            ->where('is_coupon', 'Y')
                                            ->first();
                $activity->setCoupon($campaign);
            }

            $activityNotes = sprintf('Campaign ' . ucfirst($activity_type) . '. Campaign Id : %s, Campaign Type : %s', $campaign_id, $campaign_type);
            $activity->setUser($user)
                ->setActivityName($activity_type . '_' . $campaign_type . '_popup')
                ->setActivityNameLong(ucfirst($activity_type) . ' ' . ucwords(str_replace('_', ' ', $campaign_type)) . ' Pop Up')
                ->setObject($campaign)
                ->setModuleName(ucfirst($campaign_type))
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();

            $this->rollback();
        }

        return $this->render();
    }

    /**
     * Custom validations block
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return void
     */
    protected function registerCustomValidation()
    {
        // Check user email address, it should not exists
        Validator::extend(
            'orbit.email.exists',
            function ($attribute, $value, $parameters) {
                $user = User::active()
                        ->where('user_email', $value)
                        ->first();

                if (! empty($user)) {
                    return false;
                }

                \App::instance('orbit.validation.user', $user);

                return true;
            }
        );

        // Check category, it should exists
        Validator::extend(
            'orbit.exists.category',
            function ($attribute, $value, $parameters) {
                $category = Category::active()
                        ->where('category_id', $value)
                        ->first();

                if (empty($category)) {
                    return false;
                }

                \App::instance('orbit.validation.category', $category);

                return true;
            }
        );

        // Check product, it should exists
        Validator::extend(
            'orbit.exists.product',
            function ($attribute, $value, $parameters) {
                $product = Product::active()
                        ->where('product_id', $value)
                        ->first();

                if (empty($product)) {
                    return false;
                }

                \App::instance('orbit.validation.product', $product);

                return true;
            }
        );

        // Check promotion, it should exists
        Validator::extend(
            'orbit.exists.promotion',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();

                $promotion = Promotion::with(
                    array('retailers' => function ($q) use ($retailer) {
                        $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                    })
                )->active()
                ->where('promotion_id', $value)
                ->first();

                if (empty($promotion)) {
                    return false;
                }

                \App::instance('orbit.validation.promotion', $promotion);

                return true;
            }
        );

        // Check coupon, it should exists
        Validator::extend(
            'orbit.exists.coupon',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();
                $mallTime = Carbon::now($retailer->timezone->timezone_name);
                $coupon = Coupon::active()
                                ->where('promotion_id', $value)
                                ->where('begin_date', "<=", $mallTime)
                                ->where('end_date', '>=', $mallTime)
                                ->where('coupon_validity_in_date', '>=', $mallTime)
                                ->first();

                if (empty($coupon)) {
                    return false;
                }

                \App::instance('orbit.validation.coupon', $coupon);

                return true;
            }
        );

        // Check product variant, it should exists
        Validator::extend(
            'orbit.exists.productvariant',
            function ($attribute, $value, $parameters) {
                $product = \ProductVariant::active()
                        ->where('product_variant_id', $value)
                        ->first();

                if (empty($product)) {
                    return false;
                }

                \App::instance('orbit.validation.productvariant', $product);

                return true;
            }
        );

        // Check coupons, it should exists
        Validator::extend(
            'orbit.exists.issuedcoupons',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();

                $user = $this->getLoggedInUser();

                $coupon = Coupon::whereHas(
                    'issuedcoupons',
                    function ($q) use ($user, $value, $retailer) {
                        $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.issued_coupon_id', $value)->where('expired_date', '>=', Carbon::now($retailer->timezone->timezone_name));
                    }
                )
                ->whereHas(
                    'redeemretailers',
                    function ($q) use ($retailer) {
                        $q->where('promotion_retailer_redeem.retailer_id', $retailer->merchant_id);
                    }
                )
                ->active()
                ->first();

                if (empty($coupon)) {
                    return false;
                }

                \App::instance('orbit.validation.issuedcoupons', $coupon);

                return true;
            }
        );

        // Check cart, it should exists
        Validator::extend(
            'orbit.exists.cartdetailid',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();

                $user = $this->getLoggedInUser();

                $cartdetail = CartDetail::whereHas(
                    'cart',
                    function ($q) use ($user, $retailer) {
                        $q->where('carts.customer_id', $user->user_id)->where('carts.retailer_id', $retailer->merchant_id);
                    }
                )->active()
                        ->where('cart_detail_id', $value)
                        ->first();

                if (empty($cartdetail)) {
                    return false;
                }

                \App::instance('orbit.validation.cartdetailid', $cartdetail);

                return true;
            }
        );

        // Check cart, it should exists
        Validator::extend(
            'orbit.exists.cartid',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();

                $user = $this->getLoggedInUser();

                $cart = Cart::where('cart_id', $value)->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->active()->first();

                if (empty($cart)) {
                    return false;
                }

                \App::instance('orbit.validation.cartid', $cart);

                return true;
            }
        );

        // Check the existance of widget id
        Validator::extend('orbit.empty.widget', function ($attribute, $value, $parameters) {
            $widget = Widget::excludeDeleted()
                        ->where('widget_id', $value)
                        ->first();

            if (empty($widget)) {
                return FALSE;
            }

            \App::instance('orbit.empty.widget', $widget);

            return TRUE;
        });
    }

    /**
     * @param $mall
     * @return string
     * @throws Exception
     */
    public function getLandingUrl($mall)
    {
        $user = $this->getLoggedInUser();
        $landing = Setting::getFromList($mall->settings, 'landing_page');

        // Get the landing page URL based on settings
        $landing_url = '';

        switch ($landing[0]) {
            case 'tenant':
                $landing_url = UrlBlock::blockedRoute('ci-tenant-list', [], $this->session);
                break;
            case 'service':
                $landing_url = UrlBlock::blockedRoute('ci-service-list', [], $this->session);
                break;

            case 'promotion':
                $landing_url = UrlBlock::blockedRoute('ci-promotion-list', [], $this->session);
                break;

            case 'news':
                $landing_url = UrlBlock::blockedRoute('ci-news-list', [], $this->session);
                break;

            case 'my-coupon':
                $landing_url = UrlBlock::blockedRoute('ci-coupon-list', [], $this->session);
                break;

            case 'lucky-draw':
                $landing_url = UrlBlock::blockedRoute('ci-luckydraw-list', [], $this->session);
                break;

            case 'widget':
            default:
                $landing_url = UrlBlock::blockedRoute('ci-customer-home', [], $this->session);
                break;
        }
        return $landing_url;
    }

    /**
     * @param string $landing_url
     * @param string $internet_info
     * @return string
     */
    protected function addParamsToUrl($landing_url, $internet_info = 'no')
    {
        $req = \Symfony\Component\HttpFoundation\Request::create($landing_url, 'GET', ['from_login' => 'yes', 'internet_info' => $internet_info]);
        return $req->getUri();
    }

    /**
     * @return string
     */
    protected function getPayloadEncryptionKey()
    {
        return md5('--orbit-mall--');
    }

    /**
     * String manipulation blocks
     * @param string $str - string value
     * @return string
     */
    private function just40CharMid($str)
    {
        $nnn = strlen($str);
        if ($nnn>40) {
            $all = explode('::break-here::', wordwrap($str, 38, '::break-here::'));
            $tmp = '';
            foreach ($all as $str) {
                $space = round((40 - strlen($str)) / 2);
                $spc = '';
                for ($i = 0; $i < $space; $i++) {
                    $spc .= ' ';
                }
                $tmp .= $spc . $str . " \n";
            }
        } else {
            $space = round((40 - strlen($str)) / 2);
            $spc = '';
            for ($i = 0; $i < $space; $i++) {
                $spc .= ' ';
            }
            $tmp = $spc . $str . " \n";
        }

        return $tmp;
    }

    /**
     * String manipulation blocks
     * @param string  $name  - name value
     * @param decimal $price - price value
     * @param integer $qty   - qty value
     * @param string  $sku   - sku value
     * @return string
     */
    private function productListFormat($name, $price, $qty, $sku)
    {
        $all  = '';
        $sbT = number_format($price * $qty, 2);
        $space = 40 - strlen($name) - strlen($sbT);
        $spc = '';
        for ($i = 0; $i < $space; $i++) {
            $spc .= ' ';
        }
        $all .= $name . $spc . $sbT . " \n";
        $all .= '   ' . $qty . ' x ' . number_format($price, 2) . ' (' . $sku . ')' . " \n";

        return $all;
    }

    /**
     * String manipulation blocks
     * @param string  $discount_name  - discount name value
     * @param decimal $discount_value - discount value
     * @return string
     */
    private function discountListFormat($discount_name, $discount_value)
    {
        $all  = '';
        $sbT = number_format($discount_value, 2);
        $space = 36 - strlen($discount_name) - strlen($sbT);
        $spc = '';
        for ($i = 0; $i < $space; $i++) {
            $spc .= ' ';
        }
        $all .= '   ' . $discount_name . $spc . "-" . $sbT . " \n";

        return $all;
    }

    /**
     * String manipulation blocks
     * @param string $left  - Left value
     * @param string $right - Right value
     * @return string
     */
    private function leftAndRight($left, $right)
    {
        $all  = '';
        $space = 40 - strlen($left) - strlen($right);
        $spc = '';
        for ($i = 0; $i < $space; $i++) {
            $spc .= ' ';
        }
        $all .= $left . $spc . $right . " \n";

        return $all;
    }

    /**
     * GET - catalogue tenant page
     *
     * @param string    `keyword`        (optional) - The keyword, could be: upc code, product name, short or long description
     * @param string    `sort_by`        (optional)
     * @param string    `new`            (optional) - Fill with 1 to filter for new product only (new product page)
     * @param string    `take`           (optional)
     * @param string    `skip`           (optional)
     * @param string    `sort_mode`      (optional)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getTenantsView()
    {
        $user = null;
        $keyword = null;
        $retailer = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $sort_by = OrbitInput::get('sort_by');
            $keyword = trim(OrbitInput::get('keyword'));
            $category_id = trim(OrbitInput::get('cid'));
            $floor_id = trim(OrbitInput::get('fid'));
            $floor = trim(OrbitInput::get('floor'));

            $pagetitle = Lang::get('mobileci.page_title.tenant_directory');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:name',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );
            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
            }

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $categories = Category::active('categories');

            $categories->select('categories.*');
            $this->maybeJoinWithCategoryTranslationsTable($categories, $alternateLanguage);
            $categories->whereHas('tenants', function($q) use($retailer) {
                $q->where('merchants.parent_id', $retailer->merchant_id);
                $q->where('merchants.object_type', 'tenant');
                $q->where('merchants.status', 'active');
            });
            $categories = $categories->get();

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record', 50);
            if ($maxRecord <= 0) {
                $maxRecord = Config::get('orbit.pagination.max_record');
            }

            $floorList = Object::whereHas('mall', function ($q) use ($retailer) {
                    $q->where('merchants.merchant_id', $retailer->merchant_id);
                })
                ->active()
                ->where('object_type', 'floor')
                ->orderBy('object_order', 'asc')
                ->groupBy('object_name')
                ->get();

            $tenants = Tenant::with('mediaLogo', 'merchantSocialMedia.socialMedia');
            if (!empty($alternateLanguage)) {
                $tenants = $tenants->with(['categories' => function ($q) use ($alternateLanguage) {
                    $prefix = DB::getTablePrefix();
                    $q->leftJoin('category_translations', function ($join) use ($alternateLanguage) {
                        $join->on('categories.category_id', '=', 'category_translations.category_id');
                        $join->where('category_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                        $join->where('category_translations.category_name', '!=', '');
                    });
                    $q->select('categories.*');
                    $q->addSelect([
                        DB::raw("COALESCE(${prefix}category_translations.category_name, ${prefix}categories.category_name) AS category_name"),
                        DB::raw("COALESCE(${prefix}category_translations.description, ${prefix}categories.description) AS description"),
                    ]);
                }]);
            }
            else {
                $tenants = $tenants->with('categories');
            }

            $tenants = $tenants->active('merchants')
                ->where('parent_id', $retailer->merchant_id);

            $tenants->select('merchants.merchant_id', 'floor', 'unit');

            $mallid = $retailer->merchant_id;

            $this->maybeJoinWithTranslationsTable($tenants, $alternateLanguage);

            $notfound = FALSE;
            // Filter product by name pattern
            OrbitInput::get(
                'keyword',
                function ($name) use ($tenants, $alternateLanguage) {
                    $name_like = "%$name%";
                    $tenants->where(
                        function ($q) use ($name_like, $alternateLanguage) {
                            $q->where('merchants.name', 'like', $name_like)
                                ->orWhere('merchants.description', 'like', $name_like)
                                ->orWhere('merchants.floor', 'like', $name_like);
                            $q->orWhereHas('categories', function($q2) use ($name_like) {
                                $q2->where('category_name', 'like', $name_like);
                            });
                            if (!empty($alternateLanguage)) {
                                $q->orWhereHas('categories', function($q2) use ($name_like) {
                                    $q2->whereHas('translations', function($q3) use ($name_like) {
                                        $q3->where('category_translations.category_name', 'like', $name_like);
                                    });
                                });
                                $q->orWhere('merchant_translations.name', 'like', $name_like)
                                    ->orWhere('merchant_translations.description', 'like', $name_like);
                            }
                        }
                    );
                }
            );

            OrbitInput::get(
                'cid',
                function ($cid) use ($tenants, $retailer, &$notfound) {
                    if (! empty($cid)) {
                        $category = \Category::active()
                            ->whereHas('tenants', function($q) use($retailer) {
                                $q->where('merchants.parent_id', $retailer->merchant_id);
                                $q->where('merchants.object_type', 'tenant');
                                $q->where('merchants.status', 'active');
                            })
                            ->where('category_id', $cid)
                            ->first();
                        if (!is_object($category)) {
                            $notfound = TRUE;
                        }
                        $tenants->where(
                            function ($q) use ($cid) {
                                $q->whereHas('categories', function ($q2) use ($cid) {
                                    $q2->where('category_merchant.category_id', $cid);
                                });
                            }
                        );
                    }
                }
            );

            OrbitInput::get(
                'promotion_id',
                function ($pid) use ($tenants, $retailer, &$notfound, $mallid) {
                    if (! empty($pid)) {
                        $news = \News::leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                            ->where(function ($q) use ($mallid) {
                                $q->where('merchants.parent_id', '=', $mallid)
                                  ->orWhere('merchants.merchant_id', '=', $mallid);
                            })
                            ->where('news.status', 'active')
                            ->where('news.object_type', 'promotion')
                            ->where('news.news_id', $pid)->first();

                        if (!is_object($news)) {
                            $notfound = TRUE;
                        }
                        $retailers = \NewsMerchant::whereHas('news', function($q2) use ($pid) {
                            $q2->where('news.news_id', $pid);
                            $q2->where('news.object_type', 'promotion');
                        })
                        ->where('news_merchant.object_type', 'retailer')
                        ->get()
                        ->lists('merchant_id');

                        // <-- should add exception if retailers not found
                        if (! empty($retailers)) {
                            $tenants->whereIn('merchants.merchant_id', $retailers);
                        } else {
                            $notfound = TRUE;
                        }
                    }
                }
            );

            $couponTenantRedeem = new stdclass();
            $couponTenantRedeem->linkedToTenant = FALSE;
            $couponTenantRedeem->linkedToMall = FALSE;
            $couponTenantRedeem->linkedToCS = FALSE;

            // this is came fron my coupon (or issued coupon) page
            OrbitInput::get(
                'coupon_redeem_id',
                function ($pid) use ($tenants, $retailer, &$notfound, &$couponTenantRedeem, $mallid) {
                    if (! empty($pid)) {
                        $coupon = \Coupon::with('employee')
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
                            })
                            ->where('promotions.status', 'active')
                            ->where('promotions.promotion_id', $pid)->first();
                        if (!is_object($coupon)) {
                            $notfound = TRUE;
                        }

                        if ($coupon->is_all_retailer === 'Y') {
                            $couponTenantRedeem->linkedToTenant = TRUE;
                        } else {
                            //get link tenant redeem
                            $retailers = \CouponRetailerRedeem::whereHas('tenant', function($q) use($pid) {
                                $q->where('promotion_id', $pid);
                            })->has('coupon')
                            ->get()
                            ->lists('retailer_id');

                            if (empty($retailers)) {
                                $tenants->whereNull('merchants.merchant_id');
                            } else {
                                $couponTenantRedeem->linkedToTenant = TRUE;
                                $tenants->whereIn('merchants.merchant_id', $retailers);
                            }
                        }
                        if ($coupon->is_all_employee === 'Y') {
                            $couponTenantRedeem->linkedToCS = TRUE;
                        } else {
                            $employee = \Employee::byCouponId($pid)
                                ->whereHas('retailers', function ($q) use($retailer) {
                                    $q->where('merchants.merchant_id', $retailer->merchant_id);
                                })
                                // ->has('userVerificationNumber')
                                // ->where('employees.status', 'active')
                                ->get();

                            if (count($employee) > 0) {
                                $couponTenantRedeem->linkedToCS = TRUE;
                            }
                        }
                    }
                }
            );

            // this is came fron coupon campaign page
            OrbitInput::get(
                'coupon_id',
                function ($pid) use ($tenants, $retailer, &$notfound, &$couponTenantRedeem, $mallid) {
                    if (! empty($pid)) {
                        $coupon = \Coupon::with(['linkToMalls' => function($q) use($mallid) {
                                $q->where('merchants.merchant_id', $mallid);
                            }])
                            ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                            ->where(function ($q) use ($mallid) {
                                    $q->where('merchants.parent_id', '=', $mallid)
                                      ->orWhere('merchants.merchant_id', '=', $mallid);
                                })
                            ->where('promotions.status', 'active')
                            ->where('promotions.promotion_id', $pid)->first();
                        if (!is_object($coupon)) {
                            $notfound = TRUE;
                        }

                        //get link tenant redeem
                        $retailers = \CouponRetailer::whereHas('tenant', function($q) use($pid) {
                            $q->where('promotion_id', $pid);
                        })->has('coupon')
                        ->get()
                        ->lists('retailer_id');

                        if (empty($retailers)) {
                            if (empty($coupon->linkToMalls)) {
                                $notfound = TRUE;
                            } else {
                                $couponTenantRedeem->linkedToMall = TRUE;
                            }
                        } else {
                            $couponTenantRedeem->linkedToTenant = TRUE;
                            $tenants->whereIn('merchants.merchant_id', $retailers);
                        }
                    }
                }
            );

            OrbitInput::get(
                'news_id',
                function ($pid) use ($tenants, $retailer, &$notfound, $mallid) {
                    if (! empty($pid)) {
                        $news = \News::leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                            ->where(function ($q) use ($mallid) {
                                $q->where('merchants.parent_id', '=', $mallid)
                                  ->orWhere('merchants.merchant_id', '=', $mallid);
                            })
                            ->where('news.status', 'active')
                            ->where('news.object_type', 'news')
                            ->where('news.news_id', $pid)->first();
                        if (!is_object($news)) {
                            $notfound = TRUE;
                        }
                        $retailers = \NewsMerchant::whereHas('tenant', function($q) use($pid) {
                            $q->where('news_id', $pid);
                        })->whereHas('news', function($q2) {
                            $q2->where('object_type', 'news');
                        })->get()->lists('merchant_id');
                        if (! empty($retailers)) {
                            $tenants->whereIn('merchants.merchant_id', $retailers);
                        } else {
                            $notfound = TRUE;
                        }
                    }
                }
            );

            OrbitInput::get(
                'event_id',
                function ($pid) use ($tenants, $retailer, &$notfound) {
                    if (! empty($pid)) {
                        $event = \EventModel::active()
                            ->where('merchant_id', $retailer->merchant_id)
                            ->where('event_id', $pid)
                            ->first();
                        if (!is_object($event)) {
                            $notfound = TRUE;
                        }
                        $retailers = \EventRetailer::whereHas('retailer', function($q) use($pid) {
                            $q->where('event_id', $pid);
                        })->get()->lists('retailer_id');
                        $tenants->whereIn('merchants.merchant_id', $retailers);
                    }
                }
            );

            $languages = $this->getListLanguages($retailer);

            if ($notfound) {
                return View::make('mobile-ci.404', array(
                    'page_title'=>Lang::get('mobileci.page_title.not_found'),
                    'retailer'=>$retailer,
                    'urlblock' => null,
                    'user' => $user,
                    'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                    'languages' => $languages,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                ));
            }

            if ($couponTenantRedeem->linkedToMall) {
                return Redirect::route('ci-coupon-list');
            }

            OrbitInput::get(
                'fid',
                function ($fid) use ($tenants) {
                    if (! empty($fid)) {
                        $tenants->where('merchants.floor', $fid);
                    }
                }
            );

            $this->viewItemUserUpdate('tenant', $user, $retailer);

            $tenants->groupBy('merchants.merchant_id');

            $_tenants = clone $tenants;

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');

            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $tenants->take($take);

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $tenants) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $tenants->skip($skip);

            // Default sort by
            $sortBy = 'merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get(
                'sort_by',
                function ($_sortBy) use (&$sortBy) {
                    // Map the sortby request to the real column name
                    $sortByMapping = array(
                        'name'      => 'merchants.name',
                    );
                    if (array_key_exists($_sortBy, $sortByMapping)) {
                        $sortBy = $sortByMapping[$_sortBy];
                    }
                }
            );

            OrbitInput::get(
                'sort_mode',
                function ($_sortMode) use (&$sortMode) {
                    if (strtolower($_sortMode) !== 'desc') {
                        $sortMode = 'asc';
                    } else {
                        $sortMode = 'desc';
                    }
                }
            );

            if (!empty($alternateLanguage) && $sortBy === 'merchants.name') {
                $prefix = DB::getTablePrefix();
                $tenants->orderByRaw('COALESCE(' . $prefix . 'merchant_translations.name, ' . $prefix . 'merchants.name) ' . $sortMode);
            }
            else {
                $tenants->orderBy($sortBy, $sortMode);
            }

            $prefix = DB::getTablePrefix();

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);

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

            $news_flag = $news_flag->where('merchants.parent_id', '=', $retailer->merchant_id)
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

            $promotion_flag = $promotion_flag->where('merchants.parent_id', '=', $retailer->merchant_id)
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
                        ->where('promotions.coupon_validity_in_date', '>=', Carbon::now($retailer->timezone->timezone_name));


            $coupon_flag = $coupon_flag->where('merchants.parent_id', '=', $retailer->merchant_id)
                        ->where('promotions.is_coupon', '=', 'Y')
                        ->where('promotions.status', '=', 'active')
                        ->whereRaw("? between {$prefix}promotions.begin_date and {$prefix}promotions.end_date", [$mallTime])
                        ->groupBy('merchants.name')->get();

            $totalRec = RecordCounter::create($_tenants)->count();
            $listOfRec = $tenants->get();

            foreach ($listOfRec as $tenant) {
                if (empty($tenant->logo)) {
                    $tenant->logo = 'mobile-ci/images/default_tenants_directory.png';
                }
                if (!empty($tenant->phone)) {
                    $phone = explode('|#|', $tenant->phone);
                    $tenant->phone = implode(' ', $phone);
                }
                $category_string = '';
                foreach ($tenant->categories as $i => $category) {
                    if ($i == (count($tenant->categories) - 1)) {
                        $category_string .= $category->category_name;
                    } else {
                        $category_string .= $category->category_name . ', ';
                    }
                }
                $tenant->category_string = $category_string;
                $tenant->promotion_flag = FALSE;
                $tenant->news_flag = FALSE;
                $tenant->coupon_flag = FALSE;

                foreach ($news_flag as $value1) {

                    if ($tenant->name === $value1->name) {
                        $tenant->news_flag = TRUE;
                    }
                }

                foreach ($promotion_flag as $value2) {

                    if ($tenant->name === $value2->name) {
                        $tenant->promotion_flag = TRUE;
                    }
                }

                foreach ($coupon_flag as $value3) {

                    if ($tenant->name === $value3->name) {
                        $tenant->coupon_flag = TRUE;
                    }
                }

                // set tenant facebook page url
                $tenant->facebook_like_url = '';
                if (count($tenant->merchantSocialMedia) > 0) {
                    foreach ($tenant->merchantSocialMedia as $merchantSocialMedia) {
                        if ($merchantSocialMedia->socialMedia->social_media_code === 'facebook') {
                            if (! empty($merchantSocialMedia->social_media_uri)) {
                                $tenant->facebook_like_url = '//' . $merchantSocialMedia->socialMedia->social_media_main_url . '/' . $merchantSocialMedia->social_media_uri;
                            }
                        }
                    }
                }
            }

            // Check searchmode from searching by category and floor
            $searchMode = false;
            if ($category_id != '' || $floor_id != '') {
                $searchMode = true;
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;
            $data->search_mode = $searchMode;

            if (! empty(OrbitInput::get('promotion_id'))) {
                $pagetitle = Lang::get('mobileci.page_title.promotions_tenants');

                $activityPageNotes = sprintf('Page viewed: Promotion Tenants List Page, promotion ID: %s', OrbitInput::get('promotion_id'));
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Promotion Tenant List')
                    ->setObject(null)
                    ->setLocation($retailer)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if (! empty(OrbitInput::get('coupon_id'))) {
                $pagetitle = Lang::get('mobileci.page_title.coupons_tenants');

                $activityPageNotes = sprintf('Page viewed: Coupon Tenants List Page, promotion ID: %s', OrbitInput::get('coupon_id'));
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Coupon Tenant List')
                    ->setObject(null)
                    ->setLocation($retailer)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if (! empty(OrbitInput::get('coupon_redeem_id'))) {
                $pagetitle = Lang::get('mobileci.page_title.redemption_places');

                $activityPageNotes = sprintf('Page viewed: Coupon Redemption Tenants List Page, promotion ID: %s', OrbitInput::get('coupon_redeem_id'));
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Coupon Redemption Places')
                    ->setObject(null)
                    ->setLocation($retailer)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if (! empty(OrbitInput::get('news_id'))) {
                $pagetitle = Lang::get('mobileci.page_title.news_tenants');

                $activityPageNotes = sprintf('Page viewed: News Tenants List Page, news ID: %s', OrbitInput::get('news_id'));
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View News Tenant List')
                    ->setObject(null)
                    ->setLocation($retailer)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if (! empty(OrbitInput::get('event_id'))) {
                $pagetitle = Lang::get('mobileci.page_title.events_tenants');

                $activityPageNotes = sprintf('Page viewed: Events Tenants List Page, event ID: %s', OrbitInput::get('event_id'));
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Events Tenant List')
                    ->setObject(null)
                    ->setLocation($retailer)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if (empty(OrbitInput::get('event_id')) && empty(OrbitInput::get('promotion_id')) && empty(OrbitInput::get('news_id')) && empty(OrbitInput::get('coupon_id')) && empty(OrbitInput::get('coupon_redeem_id'))) {
                $activityPageNotes = sprintf('Page viewed: Tenant Listing Page');
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Tenant List')
                    ->setObject(null)
                    ->setLocation($retailer)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            return View::make('mobile-ci.catalogue-tenant', array(
                'page_title'=>$pagetitle,
                'user' => $user,
                'retailer' => $retailer,
                'data' => $data,
                'categories' => $categories,
                'active_user' => ($user->status === 'active'),
                'floorList' => $floorList,
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'languages' => $languages,
                'link_to_coupon_data' => $couponTenantRedeem,
                'facebookInfo' => Config::get('orbit.social_login.facebook'),
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
            ));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: Tenant Listing Page');
            $activityPage->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant List Failed')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('Tenant')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Tenant detail page
     *
     * @param integer    `id`        (required) - The tenant ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getTenantDetailView()
    {
        $user = null;
        $product_id = 0;
        $retailer = null;
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        $tenant = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $product_id = trim(OrbitInput::get('id'));
            $promo_id = trim(OrbitInput::get('pid'));
            $news_id = trim(OrbitInput::get('nid'));

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $categories = Category::active('categories')
                ->where('category_level', 1)
                ->where('merchant_id', $retailer->merchant_id);

            $categories->select('categories.*');
            $this->maybeJoinWithCategoryTranslationsTable($categories, $alternateLanguage);

            $categories = $categories->get();

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);

            $tenant = Tenant::with( // translated
                array(
                    'media',
                    'mediaLogoOrig',
                    'mediaMapOrig',
                    'mediaImageOrig',
                    'merchantSocialMedia.socialMedia',
                    'newsProfiling' => function($q) use ($userGender, $userAge, $mallTime) {
                        if ($userGender !== null) {
                            $q->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                        }
                        if ($userAge !== null) {
                            if ($userAge === 0){
                                $q->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                            } else {
                                if ($userAge >= 55) {
                                    $q->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                                } else {
                                    $q->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                                }
                            }
                        }
                        $q->whereRaw("? between begin_date and end_date", [$mallTime]);

                    },
                    'newsPromotionsProfiling' => function($q) use ($userGender, $userAge, $mallTime){
                        if ($userGender !== null) {
                            $q->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                        }
                        if ($userAge !== null) {
                            if ($userAge === 0){
                                $q->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                            } else {
                                if ($userAge >= 55) {
                                    $q->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                                } else {
                                    $q->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                                }
                            }
                        }
                        $q->whereRaw("? between begin_date and end_date", [$mallTime]);
                    },
                    'couponsProfiling' => function($q) use ($userGender, $userAge, $mallTime, $user, $retailer) {
                        $prefix = DB::getTablePrefix();
                        $mallid = $retailer->merchant_id;
                        $q->select("*", DB::raw('count(' . DB::getTablePrefix() . 'promotions.promotion_id) as quantity'))
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
                            })
                            ->where('promotions.coupon_validity_in_date', '>=', $mallTime);

                        // if ($userGender !== null) {
                        //     $q->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                        // }
                        // if ($userAge !== null) {
                        //     if ($userAge === 0) {
                        //         $q->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        //     } else {
                        //         if ($userAge >= 55) {
                        //             $q->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                        //         } else {
                        //             $q->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        //         }
                        //     }
                        // }
                        $q->whereRaw("? between begin_date and end_date", [$mallTime])
                            ->groupBy('promotions.promotion_id');
                    }
                ))
                ->active('merchants')
                ->where('parent_id', $retailer->merchant_id)
                ->where('merchants.merchant_id', $product_id);

            if (!empty($alternateLanguage)) {
                $tenant = $tenant->with(['categories' => function ($q) use ($alternateLanguage) {
                    $prefix = DB::getTablePrefix();
                    $q->leftJoin('category_translations', function ($join) use ($alternateLanguage) {
                        $join->on('categories.category_id', '=', 'category_translations.category_id');
                        $join->where('category_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                        $join->where('category_translations.category_name', '!=', '');
                    });
                    $q->select('categories.*');
                    $q->addSelect([
                        DB::raw("COALESCE(${prefix}category_translations.category_name, ${prefix}categories.category_name) AS category_name"),
                        DB::raw("COALESCE(${prefix}category_translations.description, ${prefix}categories.description) AS description"),
                    ]);
                    $q->orderBy(DB::Raw('category_name'), 'asc');
                }]);
            }
            else {
                $tenant = $tenant->with('categories');
            }

            $prefix = DB::getTablePrefix();
            $tenant->select(
                    'merchants.*',
                    DB::raw("(CASE WHEN unit = '' THEN {$prefix}objects.object_name ELSE CONCAT({$prefix}objects.object_name, \" - \", unit) END) AS location")
                )
                ->leftJoin('objects', 'objects.object_id', '=', 'merchants.floor_id');
            // $this->maybeJoinWithTranslationsTable($tenant, $alternateLanguage);
            $tenant = $tenant->first();

            // Check translation for Merchant Translation
            if (!empty($alternateLanguage) && count($tenant) > 0) {
                $merchantTranslation = \MerchantTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('merchant_id', $tenant->merchant_id)->first();

                if (count($merchantTranslation) > 0) {
                    foreach (['description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($merchantTranslation->{$field}) && $merchantTranslation->{$field} !== '') {
                            $tenant->{$field} = $merchantTranslation->{$field};
                        }
                    }
                }
            }

            // News per tenant
            if (!empty($alternateLanguage) && !empty($tenant->newsProfiling)) {
                foreach ($tenant->newsProfiling as $keyNews => $news) {

                    $newsTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('news_id', $news->news_id)->first();

                    if (!empty($newsTranslation)) {
                        foreach (['news_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($newsTranslation->{$field}) && $newsTranslation->{$field} !== '') {
                                $tenant->newsProfiling[$keyNews]->{$field} = $newsTranslation->{$field};
                            }
                        }

                        $media = $newsTranslation->find($newsTranslation->news_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $news->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('news_id', $news->news_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $news->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            // Promotions per tenant
            if (!empty($alternateLanguage) && !empty($tenant->newsPromotionsProfiling)) {
                foreach ($tenant->newsPromotionsProfiling as $keyNewsPromotions => $newsPromotions) {

                    $newsPromotionsTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('news_id', $newsPromotions->news_id)->first();

                    if (!empty($newsPromotionsTranslation)) {
                        foreach (['news_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($newsPromotionsTranslation->{$field}) && $newsPromotionsTranslation->{$field} !== '') {
                                $tenant->newsPromotionsProfiling[$keyNewsPromotions]->{$field} = $newsPromotionsTranslation->{$field};
                            }
                        }

                        $media = $newsPromotionsTranslation->find($newsPromotionsTranslation->news_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $newsPromotions->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('news_id', $newsPromotions->news_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $newsPromotions->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }


            // Coupons per tenant
            if (!empty($alternateLanguage) && !empty($tenant->couponsProfiling)) {
                foreach ($tenant->couponsProfiling as $keycoupons => $coupons) {

                    $couponsTranslation = \CouponTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('promotion_id', $coupons->promotion_id)->first();

                    if (!empty($couponsTranslation)) {
                        foreach (['promotion_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($couponsTranslation->{$field}) && $couponsTranslation->{$field} !== '') {
                                $tenant->couponsProfiling[$keycoupons]->{$field} = $couponsTranslation->{$field};
                            }
                        }

                        $media = $couponsTranslation->find($couponsTranslation->coupon_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $coupons->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \CouponTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('promotion_id', $coupons->promotion_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->coupon_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $coupons->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            $languages = $this->getListLanguages($retailer);

            if (empty($tenant)) {
                // throw new Exception('Product id ' . $product_id . ' not found');
                return View::make('mobile-ci.404', array(
                    'page_title'=>Lang::get('mobileci.page_title.not_found'),
                    'retailer'=>$retailer,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                    'languages' => $languages,
                ));
            }

            if (empty($tenant->logo)) {
                $tenant->logo = 'mobile-ci/images/default_tenants_directory.png';
            }

            // set tenant facebook page url
            $tenant->facebook_like_url = '';
            if (count($tenant->merchantSocialMedia) > 0) {
                foreach ($tenant->merchantSocialMedia as $merchantSocialMedia) {
                    if ($merchantSocialMedia->socialMedia->social_media_code === 'facebook') {
                        if (! empty($merchantSocialMedia->social_media_uri)) {
                            $tenant->facebook_like_url = '//' . $merchantSocialMedia->socialMedia->social_media_main_url . '/' . $merchantSocialMedia->social_media_uri;
                        }
                    }
                }
            }
            // set tenant facebook share url
            $tenant->facebook_share_url = $this->getFBShareDummyPage('tenant', $tenant->merchant_id, $alternateLanguage->language_id);

            // cek if any language active
            if (!empty($alternateLanguage) && !empty($tenant)) {
                    $merchant_translation = \MerchantTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('merchant_id', $tenant->merchant_id)->first();

                if (!empty($merchant_translation)) {
                    foreach (['merchant_name', 'description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($merchant_translation->{$field}) && $merchant_translation->{$field} !== '') {
                            $tenant->{$field} = $merchant_translation->{$field};
                        }
                    }

                }
            }

            if (! empty($promo_id)) {
                $activityPageNotes = sprintf('Page viewed: Tenant Detail Page from Promotion, tenant ID: ' . $tenant->merchant_id . ', promotion ID: '. $promo_id);
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Tenant Detail')
                    ->setObject($tenant)
                    ->setLocation($retailer)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if (! empty($news_id)) {
                $activityPageNotes = sprintf('Page viewed: Tenant Detail Page from News, tenant ID: ' . $tenant->merchant_id . ', news ID: '. $news_id);
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Tenant Detail')
                    ->setObject($tenant)
                    ->setLocation($retailer)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if (empty($promo_id) && empty($news_id)) {
                $activityPageNotes = sprintf('Page viewed: Tenant Detail Page, tenant ID: ' . $tenant->merchant_id);
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Tenant Detail')
                    ->setObject($tenant)
                    ->setLocation($retailer)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            $box_url = "";
            if (! empty($tenant->box_url)) {
                $box_url = $tenant->box_url;

                $my_url = url('/customer?email=' . $user->user_email);

                $box_url = $box_url . '?email=' . urlencode($user->user_email) . '&logout_to=' . urlencode($my_url);
            }

            return View::make('mobile-ci.tenant', array(
                'page_title' => strtoupper($tenant->name),
                'user' => $user,
                'retailer' => $retailer,
                'tenant' => $tenant,
                'languages' => $languages,
                'facebookInfo' => Config::get('orbit.social_login.facebook'),
                'box_url' => $box_url,
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
            ));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: Tenant Detail Page, tenant ID: ' . $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant Detail Failed')
                ->setObject($tenant)
                ->setLocation($retailer)
                ->setModuleName('Tenant')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }


    /**
     * GET - Service detail page
     *
     * @param integer    `id`        (required) - The tenant ID
     *
     * @return Illuminate\View\View
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getServiceDetailView()
    {
        $user = null;
        $product_id = 0;
        $retailer = null;
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        $service = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $product_id = trim(OrbitInput::get('id'));

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $categories = Category::active('categories')
                ->where('category_level', 1)
                ->where('merchant_id', $retailer->merchant_id);

            $categories->select('categories.*');
            $this->maybeJoinWithCategoryTranslationsTable($categories, $alternateLanguage);

            $categories = $categories->get();

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);

            $service = \TenantStoreAndService::with( // translated
                array(
                    'media',
                    'mediaLogoOrig',
                    'mediaMapOrig',
                    'mediaImageOrig',
                ))
                ->active('merchants')
                ->where('parent_id', $retailer->merchant_id)
                ->where('merchants.merchant_id', $product_id);

            if (!empty($alternateLanguage)) {
                $service = $service->with(['categories' => function ($q) use ($alternateLanguage) {
                    $prefix = DB::getTablePrefix();
                    $q->leftJoin('category_translations', function ($join) use ($alternateLanguage) {
                        $join->on('categories.category_id', '=', 'category_translations.category_id');
                        $join->where('category_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                        $join->where('category_translations.category_name', '!=', '');
                    });
                    $q->select('categories.*');
                    $q->addSelect([
                        DB::raw("COALESCE(${prefix}category_translations.category_name, ${prefix}categories.category_name) AS category_name"),
                        DB::raw("COALESCE(${prefix}category_translations.description, ${prefix}categories.description) AS description"),
                    ]);
                }]);
            }
            else {
                $service = $service->with('categories');
            }

            $prefix = DB::getTablePrefix();
            $service->select(
                    'merchants.*',
                    DB::raw("(CASE WHEN unit = '' THEN {$prefix}objects.object_name ELSE CONCAT({$prefix}objects.object_name, \" - \", unit) END) AS location")
                )
                ->leftJoin('objects', 'objects.object_id', '=', 'merchants.floor_id');
            $service = $service->first();

            // Check translation for Merchant Translation
            if (!empty($alternateLanguage) && count($service) > 0) {
                $merchantTranslation = \MerchantTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('merchant_id', $service->merchant_id)->first();

                if (count($merchantTranslation) > 0) {
                    foreach (['description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($merchantTranslation->{$field}) && $merchantTranslation->{$field} !== '') {
                            $service->{$field} = $merchantTranslation->{$field};
                        }
                    }
                }
            }

            $languages = $this->getListLanguages($retailer);

            if (empty($service)) {
                return View::make('mobile-ci.404', array(
                    'page_title' => Lang::get('mobileci.page_title.not_found'),
                    'retailer'=> $retailer,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                    'languages' => $languages,
                ));
            }

            if (empty($service->logo)) {
                $service->logo = 'mobile-ci/images/default_tenants_directory.png';
            }

            // cek if any language active
            if (!empty($alternateLanguage) && !empty($service)) {
                    $merchant_translation = \MerchantTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('merchant_id', $service->merchant_id)->first();

                if (!empty($merchant_translation)) {
                    foreach (['merchant_name', 'description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($merchant_translation->{$field}) && $merchant_translation->{$field} !== '') {
                            $service->{$field} = $merchant_translation->{$field};
                        }
                    }
                }
            }

            $activityPageNotes = sprintf('Page viewed: Service Detail Page, service ID: ' . $service->merchant_id);
            $activityPage->setUser($user)
                ->setActivityName('view_service')
                ->setActivityNameLong('View Service Detail')
                ->setObject($service)
                ->setLocation($retailer)
                ->setModuleName('Service')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            $box_url = "";
            if (! empty($service->box_url)) {
                $box_url = $service->box_url;

                $my_url = url('/customer?email=' . $user->user_email);

                $box_url = $box_url . '?email=' . urlencode($user->user_email) . '&logout_to=' . urlencode($my_url);
            }

            return View::make('mobile-ci.service', array(
                'page_title' => strtoupper($service->name),
                'user' => $user,
                'retailer' => $retailer,
                'service' => $service,
                'languages' => $languages,
                // 'facebookInfo' => Config::get('orbit.social_login.facebook'),
                'box_url' => $box_url,
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
            ));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: Service Detail Page, service ID: ' . $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_service')
                ->setActivityNameLong('View Service')
                ->setObject($service)
                ->setLocation($retailer)
                ->setModuleName('Service')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
                // return $e;
        }
    }


    /**
     * GET - Tenant load more
     *
     * @param integer    `take`
     * @param integer    `skip`
     * @param string    `cid` - category ID
     * @param string    `fid` - floor name
     * @param string    `sort_mode` - asc, desc
     * @param string    `sort_by`
     * @param string    `keyword`
     * @param string    `promotion_id`
     * @param string    `news_id`
     * @param string    `event_id`
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getSearchTenant()
    {
        $user = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);

            $sort_by = OrbitInput::get('sort_by');
            $keyword = trim(OrbitInput::get('keyword'));
            $category_id = trim(OrbitInput::get('cid'));
            $floor = trim(OrbitInput::get('floor'));

            $pagetitle = Lang::get('mobileci.page_title.tenant_directory');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:name',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );
            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                $data = new stdclass();
                $data->status = 0;
                $data->total_records = 0;
                $data->returned_records = 0;
                $data->records = null;

                return Response::json($data);
            }

            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $tenants = Tenant::with('mediaLogo');
            if (!empty($alternateLanguage)) {
                $tenants = $tenants->with(['categories' => function ($q) use ($alternateLanguage) {
                    $prefix = DB::getTablePrefix();
                    $q->leftJoin('category_translations', function ($join) use ($alternateLanguage) {
                        $join->on('categories.category_id', '=', 'category_translations.category_id');
                        $join->where('category_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                    });
                    $q->select('categories.*');
                    $q->addSelect([
                        DB::raw("COALESCE(${prefix}category_translations.category_name, ${prefix}categories.category_name) AS category_name"),
                        DB::raw("COALESCE(${prefix}category_translations.description, ${prefix}categories.description) AS description"),
                    ]);
                }]);
            }
            else {
                $tenants = $tenants->with('categories');
            }

            $tenants = $tenants->active('merchants')
                ->where('parent_id', $retailer->merchant_id);

            $prefix = DB::getTablePrefix();
            $tenants->select(
                        'merchants.merchant_id',
                        'objects.object_name as floor',
                        'unit',
                        DB::raw("(CASE WHEN unit = '' THEN {$prefix}objects.object_name ELSE CONCAT({$prefix}objects.object_name, \" - \", unit) END) AS location")
                      )
                    ->leftJoin('objects', 'objects.object_id', '=', 'merchants.floor_id');

            $this->maybeJoinWithTranslationsTable($tenants, $alternateLanguage);

            $notfound = FALSE;
            // Filter product by name pattern
            OrbitInput::get(
                'keyword',
                function ($name) use ($tenants, $alternateLanguage) {
                    $name_like = "%$name%";
                    $tenants->where(
                        function ($q) use ($name_like, $alternateLanguage) {
                            $q->where('merchants.name', 'like', $name_like)
                                ->orWhere('merchants.description', 'like', $name_like)
                                ->orWhere('merchants.floor', 'like', $name_like);
                            $q->orWhereHas('categories', function($q2) use ($name_like) {
                                $q2->where('category_name', 'like', $name_like);
                            });
                            if (!empty($alternateLanguage)) {
                                $q->orWhereHas('categories', function($q2) use ($name_like) {
                                    $q2->whereHas('translations', function($q3) use ($name_like) {
                                        $q3->where('category_translations.category_name', 'like', $name_like);
                                    });
                                });
                                $q->orWhere('merchant_translations.name', 'like', $name_like)
                                    ->orWhere('merchant_translations.description', 'like', $name_like);
                            }
                        }
                    );
                }
            );

            OrbitInput::get(
                'cid',
                function ($cid) use ($tenants, $retailer, &$notfound) {
                    if (! empty($cid)) {
                        $category = \Category::active()
                            ->where('merchant_id', $retailer->merchant_id)
                            ->where('category_id', $cid)
                            ->first();
                        if (!is_object($category)) {
                            $notfound = TRUE;
                        }
                        $tenants->where(
                            function ($q) use ($cid) {
                                $q->whereHas('categories', function ($q2) use ($cid) {
                                    $q2->where('category_merchant.category_id', $cid);
                                });
                            }
                        );
                    }
                }
            );

            OrbitInput::get(
                'promotion_id',
                function ($pid) use ($tenants, $retailer, &$notfound) {
                    if (! empty($pid)) {
                        $news = \News::active()
                            ->where('mall_id', $retailer->merchant_id)
                            ->where('object_type', 'promotion')
                            ->where('news_id', $pid)->first();
                        if (!is_object($news)) {
                            $notfound = TRUE;
                        }
                        $retailers = \NewsMerchant::whereHas('tenant', function($q) use($pid) {
                            $q->where('news_id', $pid);
                        })->whereHas('news', function($q2) {
                            $q2->where('object_type', 'promotion');
                        })->get()->lists('merchant_id');
                        // <-- should add exception if retailers not found
                        $tenants->whereIn('merchants.merchant_id', $retailers);
                    }
                }
            );

            $couponTenantRedeem = new stdclass();
            $couponTenantRedeem->linkedToTenant = FALSE;
            $couponTenantRedeem->linkedToCS = FALSE;

            //this is came fron my coupon (or issued coupon) page
            OrbitInput::get(
                'coupon_redeem_id',
                function ($pid) use ($tenants, $retailer, &$notfound, &$couponTenantRedeem) {
                    if (! empty($pid)) {
                        $coupon = \Coupon::with('employee')
                            ->leftJoin('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
                            ->where(function ($q) use ($retailer) {
                                $q->where(function ($q2) use ($retailer) {
                                    $q2->where('merchants.parent_id', '=', $retailer->merchant_id)
                                        ->orWhere('merchants.merchant_id', '=', $retailer->merchant_id);
                                });
                                $q->orWhere(function ($q2) use ($retailer) {
                                    $q2->whereHas('employee', function ($q3) use ($retailer) {
                                        $q3->whereHas('employee', function ($q4) use ($retailer) {
                                            $q4->whereHas('retailers', function ($q5) use ($retailer) {
                                                $q5->where('merchants.merchant_id', $retailer->merchant_id);
                                            });
                                        });
                                    });
                                });
                            })
                            ->where('promotions.status', 'active')
                            ->where('promotions.promotion_id', $pid)->first();
                        if (!is_object($coupon)) {
                            $notfound = TRUE;
                        }

                        if ($coupon->is_all_retailer === 'Y') {
                            $couponTenantRedeem->linkedToTenant = TRUE;
                        } else {
                            //get link tenant redeem
                            $retailers = \CouponRetailerRedeem::whereHas('tenant', function($q) use($pid) {
                                $q->where('promotion_id', $pid)
                                    ->where('merchants.masterbox_number', '<>', '')
                                    ->whereNotNull('merchants.masterbox_number');
                            })
                            ->has('coupon')
                            ->get()
                            ->lists('retailer_id');

                            if (empty($retailers)) {
                                $tenants->whereNull('merchants.merchant_id');
                            } else {
                                $couponTenantRedeem->linkedToTenant = TRUE;
                                $tenants->whereIn('merchants.merchant_id', $retailers);
                            }
                        }
                        if ($coupon->is_all_employee === 'Y') {
                            $couponTenantRedeem->linkedToCS = TRUE;
                        } else {
                            $employee = \Employee::byCouponId($pid)
                                ->whereHas('retailers', function ($q) use($retailer) {
                                    $q->where('merchants.merchant_id', $retailer->merchant_id);
                                })
                                // ->has('userVerificationNumber')
                                // ->where('employees.status', 'active')
                                ->get();

                            if (count($employee) > 0) {
                                $couponTenantRedeem->linkedToCS = TRUE;
                            }
                        }
                    }
                }
            );

            // this is came fron coupon campaign page
            OrbitInput::get(
                'coupon_id',
                function ($pid) use ($tenants, $retailer, &$notfound, &$couponTenantRedeem) {
                    if (! empty($pid)) {
                        $coupon = \Coupon::leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                            ->where(function ($q) use ($retailer) {
                                    $q->where('merchants.parent_id', '=', $retailer->merchant_id)
                                      ->orWhere('merchants.merchant_id', '=', $retailer->merchant_id);
                                })
                            ->where('promotions.status', 'active')
                            ->where('promotions.promotion_id', $pid)->first();
                        if (!is_object($coupon)) {
                            $notfound = TRUE;
                        }

                        //get link tenant redeem
                        $retailers = \CouponRetailer::whereHas('tenant', function($q) use($pid) {
                            $q->where('promotion_id', $pid);
                        })->has('coupon')
                        ->get()
                        ->lists('retailer_id');

                        if (empty($retailers)) {
                            $notfound = TRUE;
                        } else {
                            $couponTenantRedeem->linkedToTenant = TRUE;
                            $tenants->whereIn('merchants.merchant_id', $retailers);
                        }
                    }
                }
            );

            OrbitInput::get(
                'news_id',
                function ($pid) use ($tenants, $retailer, &$notfound) {
                    if (! empty($pid)) {
                        $news = \News::active()
                            ->where('mall_id', $retailer->merchant_id)
                            ->where('object_type', 'news')
                            ->where('news_id', $pid)->first();
                        if (!is_object($news)) {
                            $notfound = TRUE;
                        }
                        $retailers = \NewsMerchant::whereHas('tenant', function($q) use($pid) {
                            $q->where('news_id', $pid);
                        })->whereHas('news', function($q2) {
                            $q2->where('object_type', 'news');
                        })->get()->lists('merchant_id');
                        $tenants->whereIn('merchants.merchant_id', $retailers);
                    }
                }
            );

            OrbitInput::get(
                'event_id',
                function ($pid) use ($tenants, $retailer, &$notfound) {
                    if (! empty($pid)) {
                        $event = \EventModel::active()
                            ->where('merchant_id', $retailer->merchant_id)
                            ->where('event_id', $pid)
                            ->first();
                        if (!is_object($event)) {
                            $notfound = TRUE;
                        }
                        $retailers = \EventRetailer::whereHas('retailer', function($q) use($pid) {
                            $q->where('event_id', $pid);
                        })->get()->lists('retailer_id');
                        $tenants->whereIn('merchants.merchant_id', $retailers);
                    }
                }
            );

            OrbitInput::get(
                'fid',
                function ($fid) use ($tenants) {
                    if (! empty($fid)) {
                        $tenants->where('merchants.floor', $fid);
                    }
                }
            );

            $tenants->groupBy('merchants.merchant_id');

            $_tenants = clone $tenants;

            $maxRecord = (int) Config::get('orbit.pagination.max_record', 50);
            if ($maxRecord <= 0) {
                $maxRecord = Config::get('orbit.pagination.max_record');
            }

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $tenants->take($take);

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $tenants) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $tenants->skip($skip);

            // Default sort by
            $sortBy = 'merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get(
                'sort_by',
                function ($_sortBy) use (&$sortBy) {
                    // Map the sortby request to the real column name
                    $sortByMapping = array(
                        'name'      => 'merchants.name',
                    );
                    if (array_key_exists($_sortBy, $sortByMapping)) {
                        $sortBy = $sortByMapping[$_sortBy];
                    }
                }
            );

            OrbitInput::get(
                'sort_mode',
                function ($_sortMode) use (&$sortMode) {
                    if (strtolower($_sortMode) !== 'desc') {
                        $sortMode = 'asc';
                    } else {
                        $sortMode = 'desc';
                    }
                }
            );

            if (!empty($alternateLanguage) && $sortBy === 'merchants.name') {
                $prefix = DB::getTablePrefix();
                $tenants->orderByRaw('COALESCE(' . $prefix . 'merchant_translations.name, ' . $prefix . 'merchants.name) ' . $sortMode);
            }
            else {
                $tenants->orderBy($sortBy, $sortMode);
            }

            $prefix = DB::getTablePrefix();

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);

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

            $news_flag = $news_flag->where('merchants.parent_id', '=', $retailer->merchant_id)
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

            $promotion_flag = $promotion_flag->where('merchants.parent_id', '=', $retailer->merchant_id)
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
                        ->where('promotions.coupon_validity_in_date', '>=', Carbon::now($retailer->timezone->timezone_name));

            $coupon_flag = $coupon_flag->where('merchants.parent_id', '=', $retailer->merchant_id)
                        ->where('promotions.is_coupon', '=', 'Y')
                        ->where('promotions.status', '=', 'active')
                        ->whereRaw("? between {$prefix}promotions.begin_date and {$prefix}promotions.end_date", [$mallTime])
                        ->groupBy('merchants.name')->get();

            $totalRec = RecordCounter::create($_tenants)->count();
            $listOfRec = $tenants->get();

            foreach ($listOfRec as $tenant) {
                if (empty($tenant->logo)) {
                    $tenant->logo = 'mobile-ci/images/default_tenants_directory.png';
                }
                if (!empty($tenant->phone)) {
                    $phone = explode('|#|', $tenant->phone);
                    $tenant->phone = implode(' ', $phone);
                }
                $category_string = '';
                foreach ($tenant->categories as $i => $category) {
                    if ($i == (count($tenant->categories) - 1)) {
                        $category_string .= $category->category_name;
                    } else {
                        $category_string .= $category->category_name . ', ';
                    }
                }
                $tenant->category_string = mb_strlen($category_string) > 30 ? mb_substr($category_string, 0, 30, 'UTF-8') . '...' : $category_string;
                $tenant->url = UrlBlock::blockedRoute('ci-tenant-detail' , ['id' => $tenant->merchant_id, 'name' => Str::slug($tenant->name)], $this->session);
                $tenant->redirect_url = URL::route('ci-tenant-detail' , ['id' => $tenant->merchant_id, 'name' => Str::slug($tenant->name)]);
                if (count($tenant->mediaLogo) > 0) {
                    foreach ($tenant->mediaLogo as $media) {
                        if ($media->media_name_long == 'retailer_logo_orig') {
                            $tenant->logo_orig = URL::asset($media->path);
                        }
                    }
                } else {
                    $tenant->logo_orig = URL::asset('mobile-ci/images/default_tenants_directory.png');
                }
                $tenant->name = mb_strlen($tenant->name) > 64 ? mb_substr($tenant->name, 0, 64) . '...' : $tenant->name;

                $tenant->promotion_flag = FALSE;
                $tenant->news_flag = FALSE;
                $tenant->coupon_flag = FALSE;

                foreach ($news_flag as $value1) {

                    if ($tenant->name === $value1->name) {
                        $tenant->news_flag = TRUE;
                    }
                }

                foreach ($promotion_flag as $value2) {

                    if ($tenant->name === $value2->name) {
                        $tenant->promotion_flag = TRUE;
                    }
                }

                foreach ($coupon_flag as $value3) {

                    if ($tenant->name === $value3->name) {
                        $tenant->coupon_flag = TRUE;
                    }
                }

                // set tenant facebook page url
                $tenant->facebook_like_url = '';
                if (count($tenant->merchantSocialMedia) > 0) {
                    foreach ($tenant->merchantSocialMedia as $merchantSocialMedia) {
                        if ($merchantSocialMedia->socialMedia->social_media_code === 'facebook') {
                            if (! empty($merchantSocialMedia->social_media_uri)) {
                                $tenant->facebook_like_url = '//' . $merchantSocialMedia->socialMedia->social_media_main_url . '/' . $merchantSocialMedia->social_media_uri;
                            }
                        }
                    }
                }
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            return Response::json($data);

        } catch (Exception $e) {
            switch ($e->getCode()) {
                case Session::ERR_UNKNOWN;
                case Session::ERR_IP_MISS_MATCH;
                case Session::ERR_UA_MISS_MATCH;
                case Session::ERR_SESS_NOT_FOUND;
                case Session::ERR_SESS_EXPIRE;
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = 'session_expired';
                    $data->status = 0;

                    $this->response->data = $data;

                    break;

                default:
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = $e->getMessage();
                    $data->status = 0;

                    $this->response->data = $data;
            }

            return Response::json($data);
        }
    }

    /**
     * GET - Get service list in mall
     *
     * @return Illuminate\View\View
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     */
    public function getServiceView() {
        $user = null;
        $keyword = null;
        $retailer = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $sort_by = OrbitInput::get('sort_by');
            $keyword = trim(OrbitInput::get('keyword'));
            $category_id = trim(OrbitInput::get('cid'));
            $floor_id = trim(OrbitInput::get('fid'));
            $floor = trim(OrbitInput::get('floor'));

            $pagetitle = Lang::get('mobileci.page_title.service_directory');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:name',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );
            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
            }

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $categories = Category::active('categories');

            $categories->select('categories.*');
            $this->maybeJoinWithCategoryTranslationsTable($categories, $alternateLanguage);
            $categories->whereHas('services', function($q) use($retailer) {
                $q->where('merchants.parent_id', $retailer->merchant_id);
                $q->where('merchants.object_type', 'service');
                $q->where('merchants.status', 'active');
            });
            $categories = $categories->get();

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record', 50);
            if ($maxRecord <= 0) {
                $maxRecord = Config::get('orbit.pagination.max_record');
            }

            $floorList = Object::whereHas('mall', function ($q) use ($retailer) {
                    $q->where('merchants.merchant_id', $retailer->merchant_id);
                })
                ->active()
                ->where('object_type', 'floor')
                ->orderBy('object_order', 'asc')
                ->groupBy('object_name')
                ->get();

            $mallTime = Carbon::now($retailer->timezone->timezone_name);

            $prefix = DB::getTablePrefix();
            $mallid = $retailer->merchant_id;

            $service = \TenantStoreAndService::with('mediaLogo', 'merchantSocialMedia.socialMedia');
            if (!empty($alternateLanguage)) {
                $service = $service->with(['categories' => function ($q) use ($alternateLanguage) {
                    $prefix = DB::getTablePrefix();
                    $q->leftJoin('category_translations', function ($join) use ($alternateLanguage) {
                        $join->on('categories.category_id', '=', 'category_translations.category_id');
                        $join->where('category_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                    });
                    $q->select('categories.*');
                    $q->addSelect([
                        DB::raw("COALESCE(${prefix}category_translations.category_name, ${prefix}categories.category_name) AS category_name"),
                        DB::raw("COALESCE(${prefix}category_translations.description, ${prefix}categories.description) AS description"),
                    ]);
                }]);
            }
            else {
                $service = $service->with('categories');
            }

            $service = $service->active('merchants')
                ->where('object_type', 'service')
                ->where('parent_id', $retailer->merchant_id);

            $service->select('merchants.merchant_id', 'floor', 'unit');

            $mallid = $retailer->merchant_id;

            $this->maybeJoinWithTranslationsTable($service, $alternateLanguage);

            $notfound = FALSE;
            // Filter product by name pattern
            OrbitInput::get(
                'keyword',
                function ($name) use ($service, $alternateLanguage) {
                    $name_like = "%$name%";
                    $service->where(
                        function ($q) use ($name_like, $alternateLanguage) {
                            $q->where('merchants.name', 'like', $name_like)
                                ->orWhere('merchants.description', 'like', $name_like)
                                ->orWhere('merchants.floor', 'like', $name_like);
                            $q->orWhereHas('categories', function($q2) use ($name_like) {
                                $q2->where('category_name', 'like', $name_like);
                            });
                            if (!empty($alternateLanguage)) {
                                $q->orWhereHas('categories', function($q2) use ($name_like) {
                                    $q2->whereHas('translations', function($q3) use ($name_like) {
                                        $q3->where('category_translations.category_name', 'like', $name_like);
                                    });
                                });
                                $q->orWhere('merchant_translations.name', 'like', $name_like)
                                    ->orWhere('merchant_translations.description', 'like', $name_like);
                            }
                        }
                    );
                }
            );

            OrbitInput::get(
                'cid',
                function ($cid) use ($service, $retailer, &$notfound) {
                    if (! empty($cid)) {
                        $category = \Category::active()
                            ->whereHas('services', function($q) use($retailer) {
                                $q->where('merchants.parent_id', $retailer->merchant_id);
                                $q->where('merchants.object_type', 'service');
                                $q->where('merchants.status', 'active');
                            })
                            ->where('category_id', $cid)
                            ->first();
                        if (!is_object($category)) {
                            $notfound = TRUE;
                        }
                        $service->where(
                            function ($q) use ($cid) {
                                $q->whereHas('categories', function ($q2) use ($cid) {
                                    $q2->where('category_merchant.category_id', $cid);
                                });
                            }
                        );
                    }
                }
            );

            $languages = $this->getListLanguages($retailer);

            if ($notfound) {
                return View::make('mobile-ci.404', array(
                    'page_title'=>Lang::get('mobileci.page_title.not_found'),
                    'retailer'=>$retailer,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                    'user' => $user,
                    'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                    'languages' => $languages
                ));
            }

            OrbitInput::get(
                'fid',
                function ($fid) use ($service) {
                    if (! empty($fid)) {
                        $service->where('merchants.floor', $fid);
                    }
                }
            );

            $this->viewItemUserUpdate('service', $user, $retailer);

            $service->groupBy('merchants.merchant_id');

            $_service = clone $service;

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $service->take($take);

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $service) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $service->skip($skip);

            // Default sort by
            $sortBy = 'merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get(
                'sort_by',
                function ($_sortBy) use (&$sortBy) {
                    // Map the sortby request to the real column name
                    $sortByMapping = array(
                        'name'      => 'merchants.name',
                    );
                    if (array_key_exists($_sortBy, $sortByMapping)) {
                        $sortBy = $sortByMapping[$_sortBy];
                    }
                }
            );

            OrbitInput::get(
                'sort_mode',
                function ($_sortMode) use (&$sortMode) {
                    if (strtolower($_sortMode) !== 'desc') {
                        $sortMode = 'asc';
                    } else {
                        $sortMode = 'desc';
                    }
                }
            );

            if (!empty($alternateLanguage) && $sortBy === 'merchants.name') {
                $prefix = DB::getTablePrefix();
                $service->orderByRaw('COALESCE(' . $prefix . 'merchant_translations.name, ' . $prefix . 'merchants.name) ' . $sortMode);
            }
            else {
                $service->orderBy($sortBy, $sortMode);
            }

            $totalRec = count($_service->get());
            $listOfRec = $service->get();

            foreach ($listOfRec as $service) {
                if (empty($service->logo)) {
                    $service->logo = 'mobile-ci/images/default_services_directory.png';
                }
                if (!empty($service->phone)) {
                    $phone = explode('|#|', $service->phone);
                    $service->phone = implode(' ', $phone);
                }
                $category_string = '';
                foreach ($service->categories as $i => $category) {
                    if ($i == (count($service->categories) - 1)) {
                        $category_string .= $category->category_name;
                    } else {
                        $category_string .= $category->category_name . ', ';
                    }
                }
                $service->category_string = $category_string;

                // set service facebook page url
                $service->facebook_like_url = '';
                if (count($service->merchantSocialMedia) > 0) {
                    foreach ($service->merchantSocialMedia as $merchantSocialMedia) {
                        if ($merchantSocialMedia->socialMedia->social_media_code === 'facebook') {
                            if (! empty($merchantSocialMedia->social_media_uri)) {
                                $service->facebook_like_url = '//' . $merchantSocialMedia->socialMedia->social_media_main_url . '/' . $merchantSocialMedia->social_media_uri;
                            }
                        }
                    }
                }
            }

            // Check searchmode from searching by category and floor
            $searchMode = false;
            if ($category_id != '' || $floor_id != '') {
                $searchMode = true;
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;
            $data->search_mode = $searchMode;

            $activityPageNotes = sprintf('Page viewed: %s', 'Service List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_service_list')
                ->setActivityNameLong('View Service List')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('service')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.catalogue-service', array(
                'page_title'=>$pagetitle,
                'user' => $user,
                'retailer' => $retailer,
                'data' => $data,
                'categories' => $categories,
                'active_user' => ($user->status === 'active'),
                'floorList' => $floorList,
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'languages' => $languages,
                'facebookInfo' => Config::get('orbit.social_login.facebook'),
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
            ));


        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Service List');
            $activityPage->setUser($user)
                ->setActivityName('view_service_list')
                ->setActivityNameLong('View service List Failed')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('Service')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Get service list in mall
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     */
    public function getSearchService() {
        $user = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);

            $sort_by = OrbitInput::get('sort_by');
            $keyword = trim(OrbitInput::get('keyword'));
            $category_id = trim(OrbitInput::get('cid'));
            $floor = trim(OrbitInput::get('floor'));

            $pagetitle = Lang::get('mobileci.page_title.tenant_directory');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:name',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );
            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                $data = new stdclass();
                $data->status = 0;
                $data->total_records = 0;
                $data->returned_records = 0;
                $data->records = null;

                return Response::json($data);
            }

            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $prefix = DB::getTablePrefix();
            $mallid = $retailer->merchant_id;

            $service = \TenantStoreAndService::with('mediaLogo', 'merchantSocialMedia.socialMedia');
            if (!empty($alternateLanguage)) {
                $service = $service->with(['categories' => function ($q) use ($alternateLanguage) {
                    $prefix = DB::getTablePrefix();
                    $q->leftJoin('category_translations', function ($join) use ($alternateLanguage) {
                        $join->on('categories.category_id', '=', 'category_translations.category_id');
                        $join->where('category_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                    });
                    $q->select('categories.*');
                    $q->addSelect([
                        DB::raw("COALESCE(${prefix}category_translations.category_name, ${prefix}categories.category_name) AS category_name"),
                        DB::raw("COALESCE(${prefix}category_translations.description, ${prefix}categories.description) AS description"),
                    ]);
                }]);
            }
            else {
                $service = $service->with('categories');
            }

            $service = $service->active('merchants')
                ->where('merchants.object_type', 'service')
                ->where('parent_id', $retailer->merchant_id);

            $prefix = DB::getTablePrefix();
            $service->select(
                        'merchants.merchant_id',
                        'objects.object_name as floor',
                        'unit',
                        DB::raw("(CASE WHEN unit = '' THEN {$prefix}objects.object_name ELSE CONCAT({$prefix}objects.object_name, \" - \", unit) END) AS location")
                      )
                    ->leftJoin('objects', 'objects.object_id', '=', 'merchants.floor_id');

            $mallid = $retailer->merchant_id;

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record', 50);
            if ($maxRecord <= 0) {
                $maxRecord = Config::get('orbit.pagination.max_record');
            }

            $this->maybeJoinWithTranslationsTable($service, $alternateLanguage);

            $notfound = FALSE;
            // Filter product by name pattern
            OrbitInput::get(
                'keyword',
                function ($name) use ($service, $alternateLanguage) {
                    $name_like = "%$name%";
                    $service->where(
                        function ($q) use ($name_like, $alternateLanguage) {
                            $q->where('merchants.name', 'like', $name_like)
                                ->orWhere('merchants.description', 'like', $name_like)
                                ->orWhere('merchants.floor', 'like', $name_like);
                            $q->orWhereHas('categories', function($q2) use ($name_like) {
                                $q2->where('category_name', 'like', $name_like);
                            });
                            if (!empty($alternateLanguage)) {
                                $q->orWhereHas('categories', function($q2) use ($name_like) {
                                    $q2->whereHas('translations', function($q3) use ($name_like) {
                                        $q3->where('category_translations.category_name', 'like', $name_like);
                                    });
                                });
                                $q->orWhere('merchant_translations.name', 'like', $name_like)
                                    ->orWhere('merchant_translations.description', 'like', $name_like);
                            }
                        }
                    );
                }
            );

            OrbitInput::get(
                'cid',
                function ($cid) use ($service, $retailer, &$notfound) {
                    if (! empty($cid)) {
                        $category = \Category::active()
                            ->where('category_id', $cid)
                            ->first();
                        if (!is_object($category)) {
                            $notfound = TRUE;
                        }
                        $service->where(
                            function ($q) use ($cid) {
                                $q->whereHas('categories', function ($q2) use ($cid) {
                                    $q2->where('category_merchant.category_id', $cid);
                                });
                            }
                        );
                    }
                }
            );

            OrbitInput::get(
                'fid',
                function ($fid) use ($service) {
                    if (! empty($fid)) {
                        $service->where('merchants.floor', $fid);
                    }
                }
            );

            $this->viewItemUserUpdate('service', $user, $retailer);

            $service->groupBy('merchants.merchant_id');

            $_service = clone $service;

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $service->take($take);

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $service) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $service->skip($skip);

            // Default sort by
            $sortBy = 'merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get(
                'sort_by',
                function ($_sortBy) use (&$sortBy) {
                    // Map the sortby request to the real column name
                    $sortByMapping = array(
                        'name'      => 'merchants.name',
                    );
                    if (array_key_exists($_sortBy, $sortByMapping)) {
                        $sortBy = $sortByMapping[$_sortBy];
                    }
                }
            );

            OrbitInput::get(
                'sort_mode',
                function ($_sortMode) use (&$sortMode) {
                    if (strtolower($_sortMode) !== 'desc') {
                        $sortMode = 'asc';
                    } else {
                        $sortMode = 'desc';
                    }
                }
            );

            if (!empty($alternateLanguage) && $sortBy === 'merchants.name') {
                $prefix = DB::getTablePrefix();
                $service->orderByRaw('COALESCE(' . $prefix . 'merchant_translations.name, ' . $prefix . 'merchants.name) ' . $sortMode);
            }
            else {
                $service->orderBy($sortBy, $sortMode);
            }

            $totalRec = count($_service->get());
            $listOfRec = $service->get();

            foreach ($listOfRec as $service) {
                if (empty($service->logo)) {
                    $service->logo = 'mobile-ci/images/default_services_directory.png';
                }
                if (!empty($service->phone)) {
                    $phone = explode('|#|', $service->phone);
                    $service->phone = implode(' ', $phone);
                }
                $category_string = '';
                foreach ($service->categories as $i => $category) {
                    if ($i == (count($service->categories) - 1)) {
                        $category_string .= $category->category_name;
                    } else {
                        $category_string .= $category->category_name . ', ';
                    }
                }
                $service->category_string = mb_strlen($category_string) > 30 ? mb_substr($category_string, 0, 30, 'UTF-8') . '...' : $category_string;
                $service->url = UrlBlock::blockedRoute('ci-service-detail' , ['id' => $service->merchant_id, 'name' => Str::slug($service->name)], $this->session);
                $service->redirect_url = URL::route('ci-service-detail' , ['id' => $service->merchant_id, 'name' => Str::slug($service->name)]);
                if (count($service->mediaLogo) > 0) {
                    foreach ($service->mediaLogo as $media) {
                        if ($media->media_name_long == 'service_logo_orig') {
                            $service->logo_orig = URL::asset($media->path);
                        }
                    }
                } else {
                    $service->logo_orig = URL::asset('mobile-ci/images/default_services_directory.png');
                }
                $service->name = mb_strlen($service->name) > 64 ? mb_substr($service->name, 0, 64) . '...' : $service->name;

                // set service facebook page url
                $service->facebook_like_url = '';
                if (count($service->merchantSocialMedia) > 0) {
                    foreach ($service->merchantSocialMedia as $merchantSocialMedia) {
                        if ($merchantSocialMedia->socialMedia->social_media_code === 'facebook') {
                            if (! empty($merchantSocialMedia->social_media_uri)) {
                                $service->facebook_like_url = '//' . $merchantSocialMedia->socialMedia->social_media_main_url . '/' . $merchantSocialMedia->social_media_uri;
                            }
                        }
                    }
                }
            }
            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            return Response::json($data);

        } catch (Exception $e) {
            switch ($e->getCode()) {
                case Session::ERR_UNKNOWN;
                case Session::ERR_IP_MISS_MATCH;
                case Session::ERR_UA_MISS_MATCH;
                case Session::ERR_SESS_NOT_FOUND;
                case Session::ERR_SESS_EXPIRE;
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = 'session_expired';
                    $data->status = 0;

                    $this->response->data = $data;

                    break;

                default:
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = $e->getMessage();
                    $data->status = 0;

                    $this->response->data = $data;
            }

            return Response::json($data);
        }
    }

    /**
     * GET - Lucky draw list page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getLuckyDrawListView()
    {
        $user = null;
        $retailer = null;
        $activityPage = Activity::mobileci()
            ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $languages = $this->getListLanguages($retailer);
            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $luckydraws = LuckyDraw::with('translations')
                ->active('lucky_draws')
                ->where('mall_id', $retailer->merchant_id)
                ->whereRaw("? between start_date and grace_period_date", [$mallTime]);

            OrbitInput::get(
                'keyword',
                function ($keyword) use ($luckydraws, $retailer, $alternateLanguage) {
                    $luckydraws->leftJoin('lucky_draw_translations', function($join) use ($alternateLanguage){
                            $join->on('lucky_draws.lucky_draw_id', '=', 'lucky_draw_translations.lucky_draw_id');
                            $join->where('lucky_draw_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                        })
                        ->where(function($q) use ($keyword) {
                            $q->where('lucky_draw_translations.lucky_draw_name', 'like', "%$keyword%")
                                ->orWhere('lucky_draw_translations.description', 'like', "%$keyword%");
                        });
                }
            );

            $luckydraws->groupBy('lucky_draws.lucky_draw_id');

            $this->viewItemUserUpdate('lucky_draw', $user, $retailer);

            $_luckydraws = clone $luckydraws;

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $luckydraws->take($take);

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $luckydraws) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $luckydraws->skip($skip);

            $luckydraws->orderBy(DB::raw('RAND()'));

            $totalRec = count($_luckydraws->get());
            $listOfRec = $luckydraws->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $luckyDrawTranslation = \LuckyDrawTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('lucky_draw_id', $val->lucky_draw_id)->first();

                    if (!empty($luckyDrawTranslation)) {
                        foreach (['lucky_draw_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($luckyDrawTranslation->{$field}) && $luckyDrawTranslation->{$field} !== '') {
                                $val->{$field} = $luckyDrawTranslation->{$field};
                            }
                        }

                        $media = $luckyDrawTranslation->find($luckyDrawTranslation->lucky_draw_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \LuckyDrawTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('lucky_draw_id', $val->lucky_draw_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->lucky_draw_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $val->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            if ($listOfRec->isEmpty()) {
                $data = new stdclass();
                $data->status = 0;
                $data->custom_message = Config::get('orbit.lucky_draw.custom_message', '');
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = sizeof($listOfRec);
                $data->records = $listOfRec;
            }

            $languages = $this->getListLanguages($retailer);

            $activityPageNotes = sprintf('Page viewed: %s', 'Lucky Draw List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_lucky_draw_list')
                ->setActivityNameLong('View Lucky Draw List')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('LuckyDraw')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            $view_data = array(
                'page_title'=> Lang::get('mobileci.page_title.lucky_draws'),
                'retailer' => $retailer,
                'data' => $data,
                'active_user' => ($user->status === 'active'),
                'languages' => $languages,
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'user' => $user,
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
            );

            return View::make('mobile-ci.luckydraws', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: Lucky Draw List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_lucky_draw_list')
                ->setActivityNameLong('View Lucky Draw List')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('LuckyDraw')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Get lucky draw list in mall
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getSearchLuckyDraw() {
        $user = null;
        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $maxRecord = (int) Config::get('orbit.pagination.max_record', 50);
            if ($maxRecord <= 0) {
                $maxRecord = Config::get('orbit.pagination.max_record');
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $luckydraws = LuckyDraw::with('translations')
                ->where('lucky_draws.status', 'active')
                ->where('mall_id', $retailer->merchant_id)
                ->whereRaw("? between start_date and grace_period_date", [$mallTime]);

            OrbitInput::get(
                'keyword',
                function ($keyword) use ($luckydraws, $retailer, $alternateLanguage) {
                    $luckydraws->leftJoin('lucky_draw_translations', function($join) use ($alternateLanguage){
                            $join->on('lucky_draws.lucky_draw_id', '=', 'lucky_draw_translations.lucky_draw_id');
                            $join->where('lucky_draw_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                        })
                        ->where(function($q) use ($keyword) {
                            $q->where('lucky_draw_translations.lucky_draw_name', 'like', "%$keyword%")
                                ->orWhere('lucky_draw_translations.description', 'like', "%$keyword%");
                        });
                }
            );

            OrbitInput::get('ids', function($ids) use ($luckydraws)
            {
                $luckydraws->whereNotIn('lucky_draws.lucky_draw_id', $ids);
            });

            $luckydraws->groupBy('lucky_draws.lucky_draw_id');

            $_luckydraws = clone $luckydraws;

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $luckydraws->take($take);

            // $skip = 0;
            // OrbitInput::get(
            //     'skip',
            //     function ($_skip) use (&$skip, $luckydraws) {
            //         if ($_skip < 0) {
            //             $_skip = 0;
            //         }

            //         $skip = $_skip;
            //     }
            // );
            // $luckydraws->skip($skip);

            $luckydraws->orderBy(DB::raw('RAND()'));

            $totalRec = count($_luckydraws->get());
            $listOfRec = $luckydraws->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $luckyDrawTranslation = \LuckyDrawTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('lucky_draw_id', $val->lucky_draw_id)->first();

                    if (!empty($luckyDrawTranslation)) {
                        foreach (['lucky_draw_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($luckyDrawTranslation->{$field}) && $luckyDrawTranslation->{$field} !== '') {
                                $val->{$field} = $luckyDrawTranslation->{$field};
                            }
                        }

                        $media = $luckyDrawTranslation->find($luckyDrawTranslation->lucky_draw_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \LuckyDrawTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('lucky_draw_id', $val->lucky_draw_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->lucky_draw_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $val->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            foreach ($listOfRec as $item) {
                $item->image = empty($item->image) ? URL::asset('mobile-ci/images/default_lucky_number.png') : URL::asset($item->image);
                $item->url = UrlBlock::blockedRoute('ci-luckydraw-detail', ['id' => $item->lucky_draw_id, 'name' => Str::slug($item->lucky_draw_name)], $this->session);
                $item->redirect_url = URL::route('ci-luckydraw-detail', ['id' => $item->lucky_draw_id, 'name' => Str::slug($item->lucky_draw_name)]);
                $item->name = mb_strlen($item->lucky_draw_name) > 64 ? mb_substr($item->lucky_draw_name, 0, 64) . '...' : $item->lucky_draw_name;
                $item->item_id = $item->lucky_draw_id;
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            return Response::json($data);

        } catch (Exception $e) {
            switch ($e->getCode()) {
                case Session::ERR_UNKNOWN;
                case Session::ERR_IP_MISS_MATCH;
                case Session::ERR_UA_MISS_MATCH;
                case Session::ERR_SESS_NOT_FOUND;
                case Session::ERR_SESS_EXPIRE;
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = 'session_expired';
                    $data->status = 0;

                    $this->response->data = $data;

                    break;

                default:
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = $e->getMessage();
                    $data->status = 0;

                    $this->response->data = $data;
            }

            return Response::json($data);
        }
    }

    /**
     * GET - Lucky draw page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getLuckyDrawView()
    {
        $user = null;
        $product_id = 0;
        $retailer = null;
        $activityProduct = Activity::mobileci()
                                   ->setActivityType('view');
        $luckydraw = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $lucky_draw_id = OrbitInput::get('id');

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);
            $mallTime = Carbon::now($retailer->timezone->timezone_name);

            $luckydraw = LuckyDraw::with('translations', 'prizes', 'announcements')
                ->excludeDeleted()
                ->where('mall_id', $retailer->merchant_id)
                ->where('lucky_draw_id', $lucky_draw_id)
                ->whereRaw("? between start_date and grace_period_date", [$mallTime])
                ->first();

            $languages = $this->getListLanguages($retailer);

            if (empty($luckydraw)) {
                return View::make('mobile-ci.404', [
                                'page_title'    => Lang::get('mobileci.page_title.not_found'),
                                'user'          => $user,
                                'languages'     => $languages,
                                'retailer'      => $retailer,
                                'luckydraw'     => null,
                                'numbers'       => [],
                                'total_number'  => null,
                                'prev_url'      => null,
                                'next_url'      => null,
                                'total_pages'   => null,
                                'current_page'  => null,
                                'per_page'      => null,
                                'servertime'    => null,
                                'session' => $this->session,
                                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : ''
                ]);
            }

            // set facebook share url
            $luckydraw->facebook_share_url = $this->getFBShareDummyPage('lucky-draw', $luckydraw->lucky_draw_id, $alternateLanguage->language_id);

            if (!empty($alternateLanguage) && !empty($luckydraw)) {
                $luckyDrawTranslation = \LuckyDrawTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('lucky_draw_id', $luckydraw->lucky_draw_id)->first();

                $luckydraw->lucky_draw_name_display = $luckydraw->lucky_draw_name;

                if (!empty($luckyDrawTranslation)) {
                    foreach (['lucky_draw_name', 'description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($luckyDrawTranslation->{$field}) && $luckyDrawTranslation->{$field} !== '') {
                            $luckydraw->{$field} = $luckyDrawTranslation->{$field};
                        }
                    }

                    $media = $luckyDrawTranslation->find($luckyDrawTranslation->lucky_draw_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $luckydraw->image = $media->path;
                    } else {
                        // back to default image if in the content multilanguage not have image
                        // check the system language
                        $defaultLanguage = $this->getDefaultLanguage($retailer);
                        if ($defaultLanguage !== NULL) {
                            $contentDefaultLanguage = \LuckyDrawTranslation::excludeDeleted()
                                ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                ->where('lucky_draw_id', $luckydraw->lucky_draw_id)->first();

                            // get default image
                            $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->lucky_draw_translation_id)
                                ->media_orig()
                                ->first();

                            if (isset($mediaDefaultLanguage->path)) {
                                $luckydraw->image = $mediaDefaultLanguage->path;
                            }
                        }
                    }
                }
            }

            if (! UrlBlock::isGuest($user)) {
                // Pass information to the API
                $_GET['user_id'] = $user->user_id;
                $_GET['apikey'] = $user->apikey->api_key;
                $_GET['apitimestamp'] = time();
                $_GET['lucky_draw_id'] = (array) $luckydraw->lucky_draw_id;

                $currentPage = (int)OrbitInput::get('page', 1);
                $take = 10;
                $start = ($currentPage - 1)  * $take;

                $_GET['take'] = (int)OrbitInput::get('take', $take);
                $_GET['skip'] = (int)OrbitInput::get('skip', $start);
                $_GET['sortby'] = OrbitInput::get('sortby', 'lucky_draw_number');
                $_GET['sortmode'] = OrbitInput::get('sortmode', 'desc');

                $prevUrl = '';
                $nextUrl = '';

                $secretKey = $user->apikey->api_secret_key;
                $_SERVER['REQUEST_METHOD'] = 'GET';
                $_SERVER['REQUEST_URI'] = '/api/v1/lucky-draw-number/list';
                $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

                $apiResponse = LuckyDrawAPIController::create('raw')->getSearchLuckyDrawNumber();
                if ($apiResponse->code !== 0) {
                    throw new Exception ($apiResponse->message, $apiResponse->code);
                }

                $totalPages = ceil($apiResponse->data->total_records / $take);
                $paginationPage = array();
                if ($totalPages > 1) {
                    // $prevUrl = URL::route('ci-luckydraw-detail') . '?id='. $luckydraw->lucky_draw_id . '&page=' . ($currentPage - 1);
                    // $nextUrl = URL::route('ci-luckydraw-detail') . '?id='. $luckydraw->lucky_draw_id . '&page=' . ($currentPage + 1);

                    if ($currentPage >= $totalPages) {
                        $nextUrl = '#1';
                    }

                    if ($currentPage === 1) {
                        $prevUrl = '#1';
                    }

                    $pageNumber = 4;
                    if ($totalPages > $pageNumber) {
                        if ($currentPage >= $totalPages - $pageNumber + 1) {
                            for ($x = $totalPages - $pageNumber + 1; $x <= $totalPages; $x++) {
                                $paginationPage[] = $x;
                            }
                        } else {
                            for ($x = $currentPage; $x <= $currentPage + $pageNumber - 1; $x++) {
                                $paginationPage[] = $x;
                            }
                        }
                    } else {
                        for ($x = 1; $x <= $totalPages; $x++) {
                            $paginationPage[] = $x;
                        }
                    }
                }

                $numbers = empty($apiResponse->data->records) ? array() : $apiResponse->data->records;
                $total_number = $apiResponse->data->total_records;
            } else {
                $numbers = [];
                $total_number = 0;
                $prevUrl = '';
                $nextUrl = '';
                $totalPages = 0;
                $currentPage = 1;
                $take = 10;
                $paginationPage = [];
            }

            $activityProductNotes = sprintf('Page viewed: Lucky Draw Page');
            $activityProduct->setUser($user)
                ->setActivityName('view_lucky_draw')
                ->setActivityNameLong('View Lucky Draw Detail')
                ->setObject($luckydraw)
                ->setLocation($retailer)
                ->setModuleName('LuckyDraw')
                ->setNotes($activityProductNotes)
                ->responseOK()
                ->save();

            $servertime = Carbon::now($retailer->timezone->timezone_name);
            $csrf_token = csrf_token();

            $this->session->write('orbit_csrf_token', $csrf_token);

            return View::make('mobile-ci.luckydraw', [
                                'page_title'    => $luckydraw->lucky_draw_name,
                                'user'          => $user,
                                'retailer'      => $retailer,
                                'luckydraw'     => $luckydraw,
                                'numbers'       => $numbers,
                                'total_number'  => $total_number,
                                'prev_url'      => $prevUrl,
                                'next_url'      => $nextUrl,
                                'total_pages'   => $totalPages,
                                'current_page'  => $currentPage,
                                'per_page'      => $take,
                                'servertime'    => $servertime,
                                'languages'     => $languages,
                                'paginationPage'=> $paginationPage,
                                'facebookInfo' => Config::get('orbit.social_login.facebook'),
                                'session' => $this->session,
                                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                                'csrf_token' => $csrf_token,
            ]);
        } catch (Exception $e) {
            $activityProductNotes = sprintf('Failed to view: Lucky Draw Page');
            $activityProduct->setUser($user)
                ->setActivityName('view_lucky_draw')
                ->setActivityNameLong('View Lucky Draw Detail')
                ->setObject($luckydraw)
                ->setLocation($retailer)
                ->setModuleName('Lucky Draw')
                ->setNotes($activityProductNotes)
                ->responseOK()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Lucky draw announcement page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getLuckyDrawAnnouncementView()
    {
        $user = null;
        $product_id = 0;
        $retailer = null;
        $activityProduct = Activity::mobileci()
                                   ->setActivityType('view');
        $luckydraw = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $lucky_draw_id = OrbitInput::get('id');

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $luckydraw = LuckyDraw::with(array('translations', 'prizes' => function ($q) {
                    $q->with('winners.number.user')->orderBy('lucky_draw_prizes.order', 'asc');
                }, 'announcements'))
                ->excludeDeleted()
                ->whereRaw("? between start_date and grace_period_date", [$mallTime])
                ->where('mall_id', $retailer->merchant_id)
                ->where('lucky_draw_id', $lucky_draw_id)
                ->first();

            $languages = $this->getListLanguages($retailer);

            if (empty($luckydraw)) {
                return View::make('mobile-ci.404', [
                    'page_title'    => Lang::get('mobileci.page_title.not_found'),
                    'user'          => $user,
                    'languages'     => $languages,
                    'retailer'      => $retailer,
                    'luckydraw'     => null,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                ]);
            }

            if (!empty($alternateLanguage) && !empty($luckydraw)) {
                $luckyDrawTranslation = \LuckyDrawTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('lucky_draw_id', $luckydraw->lucky_draw_id)->first();

                $luckydraw->lucky_draw_name_display = $luckydraw->lucky_draw_name;

                if (!empty($luckyDrawTranslation)) {
                    foreach (['lucky_draw_name', 'description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($luckyDrawTranslation->{$field}) && $luckyDrawTranslation->{$field} !== '') {
                            $luckydraw->{$field} = $luckyDrawTranslation->{$field};
                        }
                    }

                    $media = $luckyDrawTranslation->find($luckyDrawTranslation->lucky_draw_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $luckydraw->image = $media->path;
                    } else {
                        // back to default image if in the content multilanguage not have image
                        // check the system language
                        $defaultLanguage = $this->getDefaultLanguage($retailer);
                        if ($defaultLanguage !== NULL) {
                            $contentDefaultLanguage = \LuckyDrawTranslation::excludeDeleted()
                                ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                ->where('lucky_draw_id', $luckydraw->lucky_draw_id)->first();

                            // get default image
                            $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->lucky_draw_translation_id)
                                ->media_orig()
                                ->first();

                            if (isset($mediaDefaultLanguage->path)) {
                                $luckydraw->image = $mediaDefaultLanguage->path;
                            }
                        }
                    }
                }
            }

            if (! empty($alternateLanguage) && isset($luckydraw->announcements[0])) {
                $luckyDrawAnnouncementTranslation = \LuckyDrawAnnouncementTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('lucky_draw_announcement_id', $luckydraw->announcements[0]->lucky_draw_announcement_id)
                    ->first();

                if (!empty($luckyDrawAnnouncementTranslation)) {
                    foreach (['title', 'description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($luckyDrawAnnouncementTranslation->{$field}) && $luckyDrawAnnouncementTranslation->{$field} !== '') {
                            $luckydraw->announcements[0]->{$field} = $luckyDrawAnnouncementTranslation->{$field};
                        }
                    }

                    $media = $luckyDrawAnnouncementTranslation->find($luckyDrawAnnouncementTranslation->lucky_draw_announcement_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $luckydraw->announcements[0]->image = $media->path;
                    } else {
                        // back to default image if in the content multilanguage not have image
                        // check the system language
                        $defaultLanguage = $this->getDefaultLanguage($retailer);
                        if ($defaultLanguage !== NULL) {
                            $contentDefaultLanguage = \LuckyDrawAnnouncementTranslation::excludeDeleted()
                                ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                ->where('lucky_draw_announcement_id', $luckydraw->announcements[0]->lucky_draw_announcement_id)
                                ->first();

                            // get default image
                            $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->lucky_draw_announcement_translation_id)
                                ->media_orig()
                                ->first();

                            if (isset($mediaDefaultLanguage->path)) {
                                $luckydraw->announcements[0]->image = $mediaDefaultLanguage->path;
                            }
                        }
                    }
                }
            }

            if ($mallTime > $luckydraw->start_date && $mallTime < $luckydraw->draw_date) {
                $ongoing = TRUE;
                $pagetitle = $luckydraw->lucky_draw_name . ' ' . Lang::get('mobileci.lucky_draw.prizes');
            } elseif ($mallTime >= $luckydraw->draw_date && $mallTime < $luckydraw->grace_period_date) {
                $ongoing = FALSE;
                $pagetitle = Lang::get('mobileci.lucky_draw.prizes_and_winners');
            }

            $luckydraw->have_winner = false;
            foreach ($luckydraw->prizes as $prize) {
                if(count($prize->winners) > 0){
                    $prize->available = false;
                    foreach ($prize->winners as $winner) {
                        if (! empty($winner->lucky_draw_winner_code)) {
                            $luckydraw->have_winner = true;
                            $prize->available = true;
                        }
                    }
                }
            }

            $activityProductNotes = sprintf('Page viewed: Lucky Draw Winning Numbers & Prizes');
            $activityProduct->setUser($user)
                ->setActivityName('view_lucky_draw_announcement')
                ->setActivityNameLong('View Winning Numbers & Prizes')
                ->setObject($luckydraw)
                ->setLocation($retailer)
                ->setModuleName('Lucky Draw')
                ->setNotes($activityProductNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.luckydraw-announcement', [
                                'page_title'    => $pagetitle,
                                'user'          => $user,
                                'retailer'      => $retailer,
                                'luckydraw'     => $luckydraw,
                                'languages'     => $languages,
                                'ongoing'       => $ongoing,
                                'session' => $this->session,
                                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
            ]);
        } catch (Exception $e) {
            $activityProductNotes = sprintf('Failed to view: Lucky Draw Winning Numbers & Prizes');
            $activityProduct->setUser($user)
                ->setActivityName('view_lucky_draw')
                ->setActivityNameLong('View Winning Numbers & Prizes')
                ->setObject($luckydraw)
                ->setLocation($retailer)
                ->setModuleName('Lucky Draw')
                ->setNotes($activityProductNotes)
                ->responseOK()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Pop up for product on cart page
     *
     * @param integer    `detail`        (required) - THe product ID
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postLuckyNumberPopup()
    {
        $user = null;
        try {
            $this->registerCustomValidation();
            $luckyid = OrbitInput::post('lid');

            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $receipts = LuckyDrawNumber::with('receipts.receiptRetailer')->where('lucky_draw_number_id', $luckyid)->first();

            foreach ($receipts->receipts as $receipt) {
                if(!empty($receipt->receipt_date)) {
                    $receipt->receipt_date = date('d M Y', strtotime($receipt->receipt_date));
                }
            }

            $this->response->message = 'success';
            $this->response->data = $receipts;

            return $this->render();
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - post auto issue lucky draw
     *
     * @param string lucky_draw_id
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postLuckyDrawAutoIssue()
    {
        $user = null;
        $mall = null;
        $activity = Activity::mobileci()
                        ->setActivityType('create');
        $luckyDraw = null;
        try {
            $httpCode = 200;
            $user = $this->getLoggedInUser();
            $mall = $this->getRetailerInfo();

            $lucky_draw_id = OrbitInput::post('lucky_draw_id');

            $this->beginTransaction();
            $luckyDraw = LuckyDraw::active()->where('lucky_draw_id', $lucky_draw_id)->first();

            if (! is_object($luckyDraw)) {
                $errorMessage = sprintf('Lucky draw ID is not found.', $lucky_draw_id);
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            $now = strtotime(Carbon::now($mall->timezone->timezone_name));
            $luckyDrawDate = strtotime($luckyDraw->end_date);

            if ($now > $luckyDrawDate) {
                $errorMessage = sprintf('The lucky draw already expired on %s.', date('d/m/Y', strtotime($luckyDraw->end_date)));
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            $checkMaxIssuance = DB::table('lucky_draws')
                ->where('status', 'active')
                ->where('start_date', '<=', Carbon::now($mall->timezone->timezone_name))
                ->where('end_date', '>=', Carbon::now($mall->timezone->timezone_name))
                ->where('lucky_draw_id', $lucky_draw_id)
                ->lockForUpdate()
                ->first();

            if (! is_object($checkMaxIssuance)) {
                $this->rollBack();
                $errorMessage = Lang::get('validation.orbit.empty.lucky_draw');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ((((int) $checkMaxIssuance->max_number - (int) $checkMaxIssuance->min_number + 1) <= (int) $checkMaxIssuance->generated_numbers) && ((int) $checkMaxIssuance->free_number_batch === 0)) {
                $this->rollBack();
                $errorMessage = Lang::get('validation.orbit.exceed.lucky_draw.max_issuance', ['max_number' => $checkMaxIssuance->max_number]);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $activeluckydraw = DB::table('lucky_draws')
                ->where('status', 'active')
                ->where('start_date', '<=', Carbon::now($mall->timezone->timezone_name))
                ->where('end_date', '>=', Carbon::now($mall->timezone->timezone_name))
                ->where('lucky_draw_id', $lucky_draw_id)
                ->lockForUpdate()
                ->first();

            if (! is_object($activeluckydraw)) {
                $this->rollBack();
                $errorMessage = Lang::get('validation.orbit.empty.lucky_draw');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // determine the starting number
            $starting_number_code = DB::table('lucky_draw_numbers')
                ->where('lucky_draw_id', $lucky_draw_id)
                ->max(DB::raw('CAST(lucky_draw_number_code AS UNSIGNED)'));

            if (empty ($starting_number_code)) {
                $starting_number_code = $activeluckydraw->min_number;
            } else {
                $starting_number_code = $starting_number_code + 1;
            }

            // do the issuance
            $lucky_draw_number = new LuckyDrawNumber;
            $lucky_draw_number->lucky_draw_id = $lucky_draw_id;
            $lucky_draw_number->lucky_draw_number_code = $starting_number_code;
            $lucky_draw_number->user_id = $user->user_id;
            $lucky_draw_number->issued_date = Carbon::now();
            $lucky_draw_number->status = 'active';
            $lucky_draw_number->created_by = $user->user_id;
            $lucky_draw_number->modified_by = $user->user_id;
            $lucky_draw_number->save();

            // update free_number_batch and generated_numbers
            $updated_luckydraw = LuckyDraw::where('lucky_draw_id', $lucky_draw_id)->first();
            $updated_luckydraw->free_number_batch = 0;
            $updated_luckydraw->generated_numbers = $updated_luckydraw->generated_numbers + 1;
            $updated_luckydraw->save();

            // refresh csrf_token
            $csrf_token = csrf_token();
            $this->session->write('orbit_csrf_token', $csrf_token);

            $data = new stdclass();
            $data->total_records = 1;
            $data->returned_records = 1;
            $data->expected_issued_numbers = 1;
            $data->records = null;
            $data->lucky_draw_number_code = (array) $lucky_draw_number->lucky_draw_number_code;
            $data->lucky_draw_name = $luckyDraw->lucky_draw_name;

            // Insert to alert system
            $inbox = new Inbox();
            $inbox->addToInbox($user->user_id, $data, $mall->merchant_id, 'lucky_draw_issuance');

            $this->commit();

            // Successfull Creation
            $activity->setUser($user)
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance')
                ->setLocation($mall)
                ->setNotes('Generated number: ' . $lucky_draw_number->lucky_draw_number_code)
                ->setObject($luckyDraw)
                ->responseOK();

            $response = new stdclass();
            $response->lucky_draw_number_code = $lucky_draw_number->lucky_draw_number_code;
            $response->token = $csrf_token;

            $this->response->data = $response;

        } catch (ACLForbiddenException $e) {
            // Rollback the changes
            $this->rollBack();

            // refresh csrf_token
            $csrf_token = csrf_token();
            $this->session->write('orbit_csrf_token', $csrf_token);
            $data = new stdclass();
            $data->token = $csrf_token;

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $data;

            $httpCode = 403;

            // Creation failed Activity log
            $activity->setUser($user)
                ->setModuleName('LuckyDraw')
                ->setLocation($mall)
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance Failed')
                ->setObject($luckyDraw)
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch (InvalidArgsException $e) {
            // Rollback the changes
            $this->rollBack();

            // refresh csrf_token
            $csrf_token = csrf_token();
            $this->session->write('orbit_csrf_token', $csrf_token);
            $data = new stdclass();
            $data->token = $csrf_token;

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $data;
            $httpCode = 400;

            // Creation failed Activity log
            $activity->setUser($user)
                ->setModuleName('LuckyDraw')
                ->setLocation($mall)
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance Failed')
                ->setObject($luckyDraw)
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch (QueryException $e) {
            // Rollback the changes
            $this->rollBack();

            // refresh csrf_token
            $csrf_token = csrf_token();
            $this->session->write('orbit_csrf_token', $csrf_token);
            $data = new stdclass();
            $data->token = $csrf_token;

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = $data;
            $httpCode = 500;

            // Creation failed Activity log
            $activity->setUser($user)
                ->setModuleName('LuckyDraw')
                ->setLocation($mall)
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance Failed')
                ->setObject($luckyDraw)
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch (Exception $e) {
            // Rollback the changes
            $this->rollBack();

            // refresh csrf_token
            $csrf_token = csrf_token();
            $this->session->write('orbit_csrf_token', $csrf_token);
            $data = new stdclass();

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $data->message = $e->__toString();
                $data->token = $csrf_token;

                $this->response->data = $data;
            } else {
                $data->token = $csrf_token;

                $this->response->data = $data;
            }

            // Creation failed Activity log
            $activity->setUser($user)
                ->setModuleName('LuckyDraw')
                ->setLocation($mall)
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance Failed')
                ->setObject($luckyDraw)
                ->setNotes($e->getMessage())
                ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - Get coupon list in mall
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getMallCouponList() {
        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);

            $type = OrbitInput::get('type', 'available');
            $is_coupon_wallet = false;
            if ($type === 'wallet') {
                $is_coupon_wallet = true;
            }
            $pagetitle = Lang::get('mobileci.page_title.coupons');

            /* map pageSubTitle to be like css ellipsis*/
            $pageSubTitle = array_map(function ($arr) {
                if (mb_strlen($arr) >= 30) {
                    return substr($arr, 0, 30) . '...';
                }
                return $arr;
            }, Lang::get('mobileci.page_sub_title.coupons'));

            $languages = $this->getListLanguages($retailer);

            $walletData = array(
                            'is_coupon_wallet' => $is_coupon_wallet, //false = available | true = wallet
                        );

            $view_data = array(
                'page_title' => $pagetitle,
                'page_sub_title' => $pageSubTitle,
                'retailer' => $retailer,
                'active_user' => ($user->status === 'active'),
                'languages' => $languages,
                'user' => $user,
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'wallet' => $walletData,
                'signin_url' => URL::route('ci-coupon-list', ['type' => 'wallet']),
            );
            return View::make('mobile-ci.mall-coupon-list', $view_data);

        } catch (Exception $e) {
            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Get my coupon list in mall
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getSearchCoupon() {
        $retailer = null;
        $user = null;
        $type = OrbitInput::get('coupon_type', 'available');
        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $ids = OrbitInput::get('ids', []);
            $is_coupon_wallet = false;
            $mallid = $retailer->merchant_id;
            $user_id = $user->user_id;
            $prefix = DB::getTablePrefix();

            if ($type === 'wallet') {
                if (! $user->isConsumer()) {
                    $message = 'You must login to access this.';
                    OrbitShopAPI::throwInvalidArgument($message);
                } else {
                    $coupons = Coupon::selectRaw("*, {$prefix}promotions.promotion_id AS promotion_id,
                        {$prefix}promotions.description AS description,
                        {$prefix}promotions.long_description AS long_description,
                        {$prefix}promotions.image AS promo_image,
                        (
                            SELECT (CASE WHEN COUNT({$prefix}issued_coupons.issued_coupon_id) > 0 THEN 'true' ELSE 'false' END)
                            from {$prefix}issued_coupons
                            where user_id = '{$user_id}'
                            AND {$prefix}issued_coupons.status = 'active'
                            AND {$prefix}issued_coupons.promotion_id = {$prefix}promotions.promotion_id
                        ) as added_to_wallet")
                    ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                    ->join('promotion_rules', function ($join) {
                        $join->on('promotion_rules.promotion_id', '=', 'promotions.promotion_id');
                        $join->where('promotions.status', '=', 'active');
                    })
                    ->join('issued_coupons', function ($join) {
                        $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                        $join->where('issued_coupons.status', '=', 'active');
                    })
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
                    })
                    ->where('promotions.coupon_validity_in_date', '>=', Carbon::now($retailer->timezone->timezone_name))
                    ->where('issued_coupons.user_id', $user->user_id);
                    $is_coupon_wallet = true;
                }

            } else {
                $coupons = Coupon::selectRaw("*, {$prefix}promotions.promotion_id AS promotion_id,
                        {$prefix}promotions.description AS description,
                        {$prefix}promotions.long_description AS long_description,
                        {$prefix}promotions.image AS promo_image,
                        (
                            SELECT (CASE WHEN COUNT({$prefix}issued_coupons.issued_coupon_id) > 0 THEN 'true' ELSE 'false' END)
                            from {$prefix}issued_coupons
                            where user_id = '{$user_id}'
                            AND {$prefix}issued_coupons.status = 'active'
                            AND {$prefix}issued_coupons.promotion_id = {$prefix}promotions.promotion_id
                        ) as added_to_wallet")
                    ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                    ->join('promotion_rules', function ($join) {
                        $join->on('promotion_rules.promotion_id', '=', 'promotions.promotion_id');
                        $join->where('promotions.status', '=', 'active');
                    })
                    ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                    ->where(function ($q) use ($mallid) {
                        $q->where(function ($q2) use ($mallid) {
                            $q2->where('merchants.parent_id', '=', $mallid)
                                ->orWhere('merchants.merchant_id', '=', $mallid);
                        });
                    })
                    ->where('promotions.begin_date', '<=', Carbon::now($retailer->timezone->timezone_name))
                    ->where('promotions.end_date', '>=', Carbon::now($retailer->timezone->timezone_name))
                    ->where('promotions.coupon_validity_in_date', '>=', Carbon::now($retailer->timezone->timezone_name));
            }


            OrbitInput::get(
                'keyword',
                function ($keyword) use ($coupons, $retailer, $alternateLanguage) {
                    $coupons->leftJoin('coupon_translations', function($join) use ($alternateLanguage){
                            $join->on('promotions.promotion_id', '=', 'coupon_translations.promotion_id');
                            $join->where('coupon_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                        })
                        ->leftJoin('keyword_object', function($join) {
                            $join->on('promotions.promotion_id', '=', 'keyword_object.object_id');
                            $join->where('keyword_object.object_type', '=', 'coupon');
                        })
                        ->leftJoin('keywords', function($join) use ($retailer) {
                            $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                            $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                        })
                        ->where(function($q) use ($keyword) {
                            $q->where('coupon_translations.promotion_name', 'like', "%$keyword%")
                                ->orWhere('coupon_translations.description', 'like', "%$keyword%")
                                ->orWhere('coupon_translations.long_description', 'like', "%$keyword%")
                                ->orWhere('keyword', '=', $keyword);
                        });
                }
            );

            OrbitInput::get('ids', function($ids) use ($coupons)
            {
                $coupons->whereNotIn('promotions.promotion_id', $ids);
            });

            $coupons->groupBy('promotions.promotion_id');

            $this->viewItemUserUpdate('coupon', $user, $retailer);

            $_coupons = clone $coupons;

            $maxRecord = (int) Config::get('orbit.pagination.max_record', 50);
            if ($maxRecord <= 0) {
                $maxRecord = Config::get('orbit.pagination.max_record');
            }

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $coupons->take($take);

            // $skip = 0;
            // OrbitInput::get(
            //     'skip',
            //     function ($_skip) use (&$skip, $coupons) {
            //         if ($_skip < 0) {
            //             $_skip = 0;
            //         }

            //         $skip = $_skip;
            //     }
            // );
            // $coupons->skip($skip);

            $coupons->orderBy(DB::raw('RAND()'));

            $totalRec = count($_coupons->get());
            $listOfRec = $coupons->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $couponTranslation = \CouponTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('promotion_id', $val->promotion_id)->first();

                    if (!empty($couponTranslation)) {
                        foreach (['promotion_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($couponTranslation->{$field}) && $couponTranslation->{$field} !== '') {
                                $val->{$field} = $couponTranslation->{$field};
                            }
                        }

                        $media = $couponTranslation->find($couponTranslation->coupon_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \CouponTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('promotion_id', $val->promotion_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->coupon_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $val->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            foreach ($listOfRec as $item) {
                $item->image = empty($item->image) ? URL::asset('mobile-ci/images/default_news.png') : URL::asset($item->image);
                if ($is_coupon_wallet) {
                    $item->url = UrlBlock::blockedRoute('ci-coupon-detail-wallet', ['id' => $item->promotion_id, 'name' => Str::slug($item->promotion_name), 'type' => $type], $this->session);
                    $item->redirect_url = URL::route('ci-coupon-detail-wallet', ['id' => $item->promotion_id, 'name' => Str::slug($item->promotion_name), 'type' => $type]);
                } else {
                    $item->url = UrlBlock::blockedRoute('ci-coupon-detail', ['id' => $item->promotion_id, 'name' => Str::slug($item->promotion_name), 'type' => $type], $this->session);
                    $item->redirect_url = URL::route('ci-coupon-detail', ['id' => $item->promotion_id, 'name' => Str::slug($item->promotion_name), 'type' => $type]);
                }
                $item->name = mb_strlen($item->promotion_name) > 64 ? mb_substr($item->promotion_name, 0, 64) . '...' : $item->promotion_name;
                $item->item_id = $item->promotion_id;
                $item->add_to_wallet_hash = ! UrlBlock::isLoggedIn($this->session) ? '#' : '#1';
                $item->add_to_wallet_hash_url = URL::route('ci-coupon-list');
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if (empty($ids)) {
                $activityPage = Activity::mobileci()
                            ->setActivityType('view');

                if ($type === 'wallet') {
                    $activityPageNotes = sprintf('Page viewed: %s', 'Coupon Wallet List Page');
                    $activityPage->setUser($user)
                        ->setActivityName('view_coupon_wallet_list')
                        ->setActivityNameLong('View Coupon Wallet List')
                        ->setObject(null)
                        ->setLocation($retailer)
                        ->setModuleName('Coupon')
                        ->setNotes($activityPageNotes)
                        ->responseOK();
                } else {
                    $activityPageNotes = sprintf('Page viewed: %s', 'Coupon List Page');
                    $activityPage->setUser($user)
                        ->setActivityName('view_coupon_list')
                        ->setActivityNameLong('View Coupon List')
                        ->setObject(null)
                        ->setLocation($retailer)
                        ->setModuleName('Coupon')
                        ->setNotes($activityPageNotes)
                        ->responseOK();
                }

                $activityPage->save();
            }

            return Response::json($data);

        } catch (Exception $e) {
            $activityPage = Activity::mobileci()
                            ->setActivityType('view');
            if ($type === 'wallet') {
                $activityPageNotes = sprintf('Failed to view Page: %s', 'Coupon Wallet List');
                $activityPage->setUser($user)
                    ->setActivityName('view_coupon_wallet_list')
                    ->setActivityNameLong('View Coupon Wallet List')
                    ->setObject(null)
                    ->setLocation($retailer)
                    ->setModuleName('Coupon')
                    ->setNotes($activityPageNotes)
                    ->save();
            } else {
                $activityPageNotes = sprintf('Failed to view Page: %s', 'Coupon List');
                $activityPage->setUser($user)
                    ->setActivityName('view_coupon_list')
                    ->setActivityNameLong('View Coupon List')
                    ->setObject(null)
                    ->setLocation($retailer)
                    ->setModuleName('Coupon')
                    ->setNotes($activityPageNotes)
                    ->save();
            }

            switch ($e->getCode()) {
                case Session::ERR_UNKNOWN;
                case Session::ERR_IP_MISS_MATCH;
                case Session::ERR_UA_MISS_MATCH;
                case Session::ERR_SESS_NOT_FOUND;
                case Session::ERR_SESS_EXPIRE;
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = 'session_expired';
                    $data->status = 0;

                    $this->response->data = $data;

                    break;

                default:
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = $e->getMessage();
                    $data->status = 0;

                    $this->response->data = $data;
            }

            return Response::json($data);
        }
    }

    /**
     * GET - Coupon detail page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getMallCouponDetailView()
    {
        $user = null;
        $promotion_id = 0;
        $type = '';
        $retailer = null;
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        $coupons = null;
        try {
            $user = $this->getLoggedInUser();
            if (\Route::currentRouteName() === 'ci-coupon-detail-wallet') {
                UrlBlock::checkBlockedUrl($user);
            }
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);

            $promotion_id = trim(OrbitInput::get('id'));
            $type = OrbitInput::get('type', 'available');

            $issued_coupons = IssuedCoupon::where('user_id', $user->user_id)
                                ->where('promotion_id', $promotion_id)
                                ->active()
                                ->first();

            $added_to_wallet_detail = false;
            if (!empty($issued_coupons)) {
                //return Redirect::route('ci-tenant-list', array('coupon_id' => $promotion_id));
                $added_to_wallet_detail = true;
            }

            $is_coupon_wallet_detail = false;
            if ($type === 'wallet') {
                $is_coupon_wallet_detail = true;
            }

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $mallid = $retailer->merchant_id;

            $coupons = Coupon::with('couponRule')
                ->select('promotions.*', 'promotions.description as description')
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
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

            $languages = $this->getListLanguages($retailer);

            $coupons = $coupons->where('promotions.status', 'active')
                ->where('promotions.coupon_validity_in_date', '>=', Carbon::now($retailer->timezone->timezone_name))
                ->where('promotions.promotion_id', $promotion_id)
                ->first();

            if (empty($coupons)) {
                // throw new Exception('Product id ' . $issued_coupon_id . ' not found');
                // return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer, 'languages' => $languages));
                return Redirect::route('ci-tenant-list', array('coupon_id' => $promotion_id));
            }

            $issued_coupons = IssuedCoupon::active()
                ->where('promotion_id', $promotion_id)
                ->where('user_id', $user->user_id)
                ->orderBy('expired_date', 'DESC')
                ->get();

            // set facebook share url

            $coupon_id = $coupons->promotion_id;

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $coupons->facebook_share_url = $this->getFBShareDummyPage('coupon', $coupons->promotion_id, $alternateLanguage->language_id);

            if (! empty($alternateLanguage)) {
                $couponTranslation = \CouponTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('promotion_id', $coupons->promotion_id)->first();

                if (! empty($couponTranslation)) {
                    foreach (['promotion_name', 'description', 'long_description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($couponTranslation->{$field}) && $couponTranslation->{$field} !== '') {
                            $coupons->{$field} = $couponTranslation->{$field};
                        }
                    }

                    $media = $couponTranslation->find($couponTranslation->coupon_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $coupons->image = $media->path;
                    } else {
                        // back to default image if in the content multilanguage not have image
                        // check the system language
                        $defaultLanguage = $this->getDefaultLanguage($retailer);
                        if ($defaultLanguage !== NULL) {
                            $contentDefaultLanguage = \CouponTranslation::excludeDeleted()
                                ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                ->where('promotion_id', $coupons->promotion_id)->first();

                            // get default image
                            $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->coupon_translation_id)
                                ->media_orig()
                                ->first();

                            if (isset($mediaDefaultLanguage->path)) {
                                $coupons->image = $mediaDefaultLanguage->path;
                            }
                        }
                    }

                }
            }

            //Check link to tenant is all tenant
            if ($coupons->is_all_retailer === 'Y') {
                $linkToAllTenant = TRUE;
                $cso_exists = FALSE;
                $tenants = \Tenant::where('parent_id','=', $retailer->merchant_id)
                ->where('status', 'active')
                ->get();
            } elseif ($coupons->is_all_retailer === 'N') {
                $linkToAllTenant = FALSE;

                $tenants = \CouponRetailerRedeem::with('tenant', 'tenant.categories')
                    ->wherehas('tenant', function ($q) use ($retailer) {
                        $q->where('merchants.status', 'active');
                        $q->where('merchants.masterbox_number', '<>', '');
                        $q->whereNotNull('merchants.masterbox_number');
                        $q->where('merchants.parent_id', $retailer->merchant_id);
                    })
                    ->where('promotion_id', $coupon_id)->get();

                $coupons->linkedToCS = FALSE;

                if ($coupons->is_all_employee === 'Y') {
                    $coupons->linkedToCS = TRUE;
                } else {
                    $employee = \Employee::byCouponId($coupon_id)
                        ->whereHas('retailers', function ($q) use($retailer) {
                            $q->where('merchants.merchant_id', $retailer->merchant_id);
                        })
                        // ->has('userVerificationNumber')
                        // ->where('employees.status', 'active')
                        ->get();

                    if (count($employee) > 0) {
                        $coupons->linkedToCS = TRUE;
                    }
                }
            }

            $link_to_tenants = \CouponRetailer::where('promotion_retailer.object_type', 'tenant')
                ->whereHas('tenant', function($q) use ($retailer) {
                    $q->where('parent_id', $retailer->merchant_id);
                })
                ->where('promotion_id', $coupon_id)
                ->get();

            if (empty($coupons->image)) {
                $coupons->image = 'mobile-ci/images/default_coupon.png';
            }

            // Check coupon have condition cs reedem
            $cs_reedem = false;

            // Check exist customer verification number per mall
            $employeeVerNumbersActive = \UserVerificationNumber::
                        join('users', 'users.user_id', '=', 'user_verification_numbers.user_id')
                        ->where('users.status', 'active')
                        ->where('merchant_id', $retailer->merchant_id)
                        ->count('users.user_id');

            if ($coupons->is_all_employee === 'Y') {
                if ($employeeVerNumbersActive > 0) {
                    $cs_reedem = true;
                }
            } elseif ($coupons->is_all_employee === 'N') {
                // Check exist link to cs, and cs must have active status
                $promotionEmployee = \CouponEmployee::
                                join('users', 'users.user_id', '=', 'promotion_employee.user_id')
                                ->where('users.status', 'active')
                                ->where('promotion_employee.promotion_id', $coupons->promotion_id)
                                ->count('promotion_employee_id');

                if ($promotionEmployee > 0) {
                    $cs_reedem = true;
                }
            }

            if ($type === 'wallet') {
                $activityPageNotes = sprintf('Page viewed: Coupon Wallet Detail, Coupon Id: %s', $promotion_id);
                $activityPage->setUser($user)
                    ->setActivityName('view_coupon_wallet')
                    ->setActivityNameLong('View Coupon Wallet Detail')
                    ->setLocation($retailer)
                    ->setObject($coupons)
                    ->setCoupon($coupons)
                    ->setModuleName('Coupon')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            } else {
                $activityPageNotes = sprintf('Page viewed: Coupon Detail, Coupon Id: %s', $promotion_id);
                $activityPage->setUser($user)
                    ->setActivityName('view_coupon')
                    ->setActivityNameLong('View Coupon Detail')
                    ->setLocation($retailer)
                    ->setObject($coupons)
                    ->setCoupon($coupons)
                    ->setModuleName('Coupon')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if ($is_coupon_wallet_detail) {
                $redirect_hash_url = URL::route('ci-coupon-detail-wallet', ['id' => $promotion_id, 'name' => Str::slug($coupons->promotion_name), 'type' => $type]);
            } else {
                $redirect_hash_url = URL::route('ci-coupon-detail', ['id' => $promotion_id, 'name' => Str::slug($coupons->promotion_name), 'type' => $type]);
            }

            $walletData = array(
                        'is_coupon_wallet' => $is_coupon_wallet_detail, //false = available | true = wallet
                        'added_to_wallet' => $added_to_wallet_detail, //false = not in wallet | true = in wallet
                        'hash' => (! UrlBlock::isLoggedIn($this->session) ? '#' : '#1'),
                        'hash_url' => $redirect_hash_url . '&successLogin=true',
                        'icon' => ($added_to_wallet_detail ? 'fa-check' : 'fa-plus'),
                        'text' => ($added_to_wallet_detail ?  Lang::get('mobileci.coupon.added_wallet') : Lang::get('mobileci.coupon.add_wallet')),
                        'circle' => ($added_to_wallet_detail ? 'added' : '')
                    );

            /* map pageSubTitle to be like css ellipsis*/
            $pageSubTitle = array_map(function ($arr) {
                if (mb_strlen($arr) >= 30) {
                    return substr($arr, 0, 30) . '...';
                }
                return $arr;
            }, Lang::get('mobileci.page_sub_title.coupons'));

            return View::make('mobile-ci.mall-coupon', array(
                'page_title' => $coupons->promotion_name,
                'page_sub_title' => $pageSubTitle,
                'user' => $user,
                'retailer' => $retailer,
                'coupon' => $coupons,
                'issued_coupons' => $issued_coupons,
                'tenants' => $tenants,
                'link_to_tenants' => $link_to_tenants,
                'languages' => $languages,
                // 'cso_exists' => $cso_exists,
                'cs_reedem' => $cs_reedem,
                'link_to_all_tenant' => $linkToAllTenant,
                'facebookInfo' => Config::get('orbit.social_login.facebook'),
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'wallet' => $walletData,
                'available_url' => URL::route('ci-coupon-list', ['type' => 'available']),
                'wallet_url' => URL::route('ci-coupon-list', ['type' => 'wallet']),
                'layout' => 'detail',
            ));

        } catch (Exception $e) {
            if ($type === 'wallet') {
                $activityPageNotes = sprintf('Failed to view Page: Coupon Wallet Detail, Coupon Id: %s', $promotion_id);
                $activityPage->setUser($user)
                    ->setLocation($retailer)
                    ->setActivityName('view_coupon')
                    ->setActivityNameLong('View Coupon Wallet Detail Failed')
                    ->setObject($coupons)
                    ->setModuleName('Coupon')
                    ->setNotes($activityPageNotes)
                    ->responseFailed()
                    ->save();
            } else {
                $activityPageNotes = sprintf('Failed to view Page: Coupon Detail, Coupon Id: %s', $promotion_id);
                $activityPage->setUser($user)
                    ->setLocation($retailer)
                    ->setActivityName('view_coupon')
                    ->setActivityNameLong('View Coupon Detail Failed')
                    ->setObject($coupons)
                    ->setModuleName('Coupon')
                    ->setNotes($activityPageNotes)
                    ->responseFailed()
                    ->save();
            }

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Coupon detail page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getMallCouponCampaignDetailView()
    {
        $user = null;
        $coupon_id = 0;
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        $coupons = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $coupon_id = trim(OrbitInput::get('id'));
            $languages = $this->getListLanguages($retailer);

            $coupons = Coupon::with(array('couponRule'))
                ->where('merchant_id', $retailer->merchant_id)
                ->where('promotions.status', 'active')
                ->where('promotions.promotion_id', $coupon_id)
                ->first();

            if (empty($coupons)) {
                return View::make('mobile-ci.404', array(
                    'page_title'=>Lang::get('mobileci.page_title.not_found'),
                    'retailer'=>$retailer,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                    'languages' => $languages,
                ));
            }

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            // set facebook share url
            $coupons->facebook_share_url = $this->getFBShareDummyPage('coupon', $coupons->promotion_id, $alternateLanguage->language_id);

            if (! empty($alternateLanguage)) {
                $couponTranslation = \CouponTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('promotion_id', $coupons->promotion_id)->first();

                if (! empty($couponTranslation)) {
                    foreach (['promotion_name', 'description', 'long_description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($couponTranslation->{$field}) && $couponTranslation->{$field} !== '') {
                            $coupons->{$field} = $couponTranslation->{$field};
                        }
                    }

                    $media = $couponTranslation->find($couponTranslation->coupon_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $coupons->image = $media->path;
                    } else {
                        // back to default image if in the content multilanguage not have image
                        // check the system language
                        $defaultLanguage = $this->getDefaultLanguage($retailer);
                        if ($defaultLanguage !== NULL) {
                            $contentDefaultLanguage = \CouponTranslation::excludeDeleted()
                                ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
                                ->where('promotion_id', $coupons->promotion_id)->first();

                            // get default image
                            $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->coupon_translation_id)
                                ->media_orig()
                                ->first();

                            if (isset($mediaDefaultLanguage->path)) {
                                $coupons->image = $mediaDefaultLanguage->path;
                            }
                        }
                    }

                }
            }

            //Check link to tenant is all tenant
            $linkToAllTenant = FALSE;

            $tenants = \CouponRetailer::with('tenant', 'tenant.categories')
                ->wherehas('tenant', function($q){
                    $q->where('merchants.status', 'active');
                })
                ->where('promotion_id', $coupon_id)->get();

            $cso_exists = FALSE;

            if (empty($coupons->image)) {
                $coupons->image = 'mobile-ci/images/default_coupon.png';
            }

            // Check coupon have condition cs reedem
            $cs_reedem = false;

            $activityPageNotes = sprintf('Page viewed: Coupon Detail, Coupon Id: %s', $coupon_id);
            $activityPage->setUser($user)
                ->setActivityName('view_coupon')
                ->setActivityNameLong('View Coupon Campaign Detail')
                ->setObject($coupons)
                ->setCoupon($coupons)
                ->setModuleName('Coupon')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.mall-coupon-campaign', array(
                'page_title' => $coupons->promotion_name,
                'user' => $user,
                'retailer' => $retailer,
                'coupon' => $coupons,
                'tenants' => $tenants,
                'languages' => $languages,
                'cso_exists' => $cso_exists,
                'cs_reedem' => $cs_reedem,
                'link_to_all_tenant' => $linkToAllTenant,
                'facebookInfo' => Config::get('orbit.social_login.facebook'),
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
            ));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: Coupon Detail, Coupon Id: %s', $coupon_id);
            $activityPage->setUser($user)
                ->setActivityName('view_coupon')
                ->setActivityNameLong('View Coupon Detail Failed')
                ->setObject($coupons)
                ->setModuleName('Coupon')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function postCheckUserLocation() {

        $lat = OrbitInput::post('latitude', null);
        $long = OrbitInput::post('longitude', null);

        $malls = Mall::excludeDeleted()->InsideArea($lat, $long)->first();

        if (! is_object($malls)) {
            return 'false';
        }

        return 'true';
    }

    /**
     * GET - Get promotion list in mall
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getMallPromotionList() {
        $user = null;
        $keyword = null;
        $retailer = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $pagetitle = Lang::get('mobileci.page_title.promotions');

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $mallid = $retailer->merchant_id;

            $promotions = \News::with('translations')
                            ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                            ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                            ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                            ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                            ->select('*', 'news.description as description');
            // filter by age and gender
            if ($userGender !== null) {
                $promotions = $promotions->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $promotions = $promotions->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $promotions = $promotions->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $promotions = $promotions->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            $promotions = $promotions->where('news.status', '=', 'active')
                        ->where(function ($q) use ($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                              ->orWhere('merchants.merchant_id', '=', $mallid);
                        })
                        ->where('news.object_type', 'promotion')
                        ->whereRaw("? between begin_date and end_date", [$mallTime]);

            OrbitInput::get(
                'keyword',
                function ($keyword) use ($promotions, $retailer, $alternateLanguage) {
                    $promotions->leftJoin('news_translations', function($join) use ($alternateLanguage){
                            $join->on('news.news_id', '=', 'news_translations.news_id');
                            $join->where('news_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                        })
                        ->leftJoin('keyword_object', function($join) {
                            $join->on('news.news_id', '=', 'keyword_object.object_id');
                            $join->where('keyword_object.object_type', '=', 'promotion');
                        })
                        ->leftJoin('keywords', function($join) use ($retailer) {
                            $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                            $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                        })
                        ->where(function($q) use ($keyword) {
                            $q->where('news_translations.news_name', 'like', "%$keyword%")
                                ->orWhere('news_translations.description', 'like', "%$keyword%")
                                ->orWhere('keyword', '=', $keyword);
                        });
                }
            );

            $promotions = $promotions->groupBy('news.news_id');

            $this->viewItemUserUpdate('promotion', $user, $retailer);

            $_promotions = clone $promotions;

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $promotions->take($take);

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $promotions) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $promotions->skip($skip);

            $promotions->orderBy(DB::raw('RAND()'));

            $totalRec = count($_promotions->get());
            $listOfRec = $promotions->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {

                foreach ($listOfRec as $key => $val) {
                    $promotionTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('news_id', $val->news_id)->first();

                    if (!empty($promotionTranslation)) {
                        foreach (['news_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($promotionTranslation->{$field}) && $promotionTranslation->{$field} !== '') {
                                $val->{$field} = $promotionTranslation->{$field};
                            }
                        }

                        $media = $promotionTranslation->find($promotionTranslation->news_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('news_id', $val->news_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $val->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            if ($listOfRec->isEmpty()) {
                $data = new stdclass();
                $data->status = 0;
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = sizeof($listOfRec);
                $data->records = $listOfRec;
            }

            $languages = $this->getListLanguages($retailer);

            $activityPageNotes = sprintf('Page viewed: %s', 'Promotion List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_promotion_list')
                ->setActivityNameLong('View Promotion List')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('Promotion')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            $view_data = array(
                'page_title'=>$pagetitle,
                'retailer' => $retailer,
                'data' => $data,
                'active_user' => ($user->status === 'active'),
                'languages' => $languages,
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'user' => $user,
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
            );
            return View::make('mobile-ci.mall-promotion-list', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Promotion List');
            $activityPage->setUser($user)
                ->setActivityName('view_promotion_list')
                ->setActivityNameLong('View Promotion List Failed')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('Promotion')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Get promotion list in mall
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getSearchPromotion()
    {
        $user = null;
        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $maxRecord = (int) Config::get('orbit.pagination.max_record', 50);
            if ($maxRecord <= 0) {
                $maxRecord = Config::get('orbit.pagination.max_record');
            }

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $mallid = $retailer->merchant_id;

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $promotions = \News::with('translations')
                            ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                            ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                            ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                            ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                            ->select('*', 'news.description as description');
            // filter by age and gender
            if ($userGender !== null) {
                $promotions = $promotions->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $promotions = $promotions->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $promotions = $promotions->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $promotions = $promotions->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            OrbitInput::get('ids', function($ids) use ($promotions)
            {
                $promotions->whereNotIn('news.news_id', $ids);
            });

            $promotions = $promotions->where('news.status', '=', 'active')
                ->where(function ($q) use ($mallid) {
                    $q->where('merchants.parent_id', '=', $mallid)
                      ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', 'promotion')
                ->whereRaw("? between begin_date and end_date", [$mallTime]);

            OrbitInput::get(
                'keyword',
                function ($keyword) use ($promotions, $retailer, $alternateLanguage) {
                    $promotions->leftJoin('news_translations', function($join) use ($alternateLanguage){
                            $join->on('news.news_id', '=', 'news_translations.news_id');
                            $join->where('news_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                        })
                        ->leftJoin('keyword_object', function($join) {
                            $join->on('news.news_id', '=', 'keyword_object.object_id');
                            $join->where('keyword_object.object_type', '=', 'promotion');
                        })
                        ->leftJoin('keywords', function($join) use ($retailer) {
                            $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                            $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                        })
                        ->where(function($q) use ($keyword) {
                            $q->where('news_translations.news_name', 'like', "%$keyword%")
                                ->orWhere('news_translations.description', 'like', "%$keyword%")
                                ->orWhere('keyword', '=', $keyword);
                        });
                }
            );

            $promotions = $promotions->groupBy('news.news_id');

            $_promotions = clone $promotions;

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $promotions->take($take);

            // $skip = 0;
            // OrbitInput::get(
            //     'skip',
            //     function ($_skip) use (&$skip, $promotions) {
            //         if ($_skip < 0) {
            //             $_skip = 0;
            //         }

            //         $skip = $_skip;
            //     }
            // );
            // $promotions->skip($skip);

            $promotions->orderBy(DB::raw('RAND()'));

            $totalRec = count($_promotions->get());
            $listOfRec = $promotions->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $promotionTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('news_id', $val->news_id)->first();

                    if (!empty($promotionTranslation)) {
                        foreach (['news_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($promotionTranslation->{$field}) && $promotionTranslation->{$field} !== '') {
                                $val->{$field} = $promotionTranslation->{$field};
                            }
                        }

                        $media = $promotionTranslation->find($promotionTranslation->news_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('news_id', $val->news_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $val->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            foreach ($listOfRec as $item) {
                $item->image = empty($item->image) ? URL::asset('mobile-ci/images/default_promotion.png') : URL::asset($item->image);
                $item->url = UrlBlock::blockedRoute('ci-promotion-detail', ['id' => $item->news_id, 'name' => Str::slug($item->news_name)], $this->session);
                $item->redirect_url = URL::route('ci-promotion-detail', ['id' => $item->news_id, 'name' => Str::slug($item->news_name)]);
                $item->name = mb_strlen($item->news_name) > 64 ? mb_substr($item->news_name, 0, 64) . '...' : $item->news_name;
                $item->item_id = $item->news_id;
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            return Response::json($data);

        } catch (Exception $e) {
            switch ($e->getCode()) {
                case Session::ERR_UNKNOWN;
                case Session::ERR_IP_MISS_MATCH;
                case Session::ERR_UA_MISS_MATCH;
                case Session::ERR_SESS_NOT_FOUND;
                case Session::ERR_SESS_EXPIRE;
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = 'session_expired';
                    $data->status = 0;

                    $this->response->data = $data;

                    break;

                default:
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = $e->getMessage();
                    $data->status = 0;

                    $this->response->data = $data;
            }

            return Response::json($data);
        }
    }

    /**
     * GET - Promotion detail page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getMallPromotionDetailView()
    {
        $user = null;
        $promotion_id = 0;
        $retailer = null;
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        $promotion = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $languages = $this->getListLanguages($retailer);

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $promotion_id = trim(OrbitInput::get('id'));

            $mallid = $retailer->merchant_id;
            $mallTime = Carbon::now($retailer->timezone->timezone_name);

            $promotion = \News::with(['tenants' => function($q) use($retailer) {
                    $q->where('merchants.status', 'active');
                    $q->where('merchants.parent_id', $retailer->merchant_id);
                }])
                ->select('*', 'news.description as description')
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mallid) {
                    $q->where('merchants.parent_id', '=', $mallid)
                      ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.news_id', $promotion_id)
                ->where('news.status', 'active')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->where('news.object_type', 'promotion')
                ->first();

            if (empty($promotion)) {
                // throw new Exception('Product id ' . $promotion_id . ' not found');
                return View::make('mobile-ci.404', array(
                    'page_title'=>Lang::get('mobileci.page_title.not_found'),
                    'retailer'=>$retailer,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                    'languages' => $languages,
                    'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : ''
                ));
            }

            if (empty($promotion->image)) {
                $promotion->image = 'mobile-ci/images/default_promotion.png';
            }

            // set facebook share url
            $promotion->facebook_share_url = $this->getFBShareDummyPage('promotion', $promotion->news_id, $alternateLanguage->language_id);

            // checking if all tenant linked to this promotion inactive or not
            // so that if all tenant inactive we can disable the 'see tenant' button on the view
            // for fix bug OM-724
            $_tenants = $promotion->tenants;

            $allTenantInactive = false;

            $inactiveTenant = 0;

            foreach($_tenants as $key => $value)
            {
                if ($value->status === 'inactive') {
                    $inactiveTenant = $inactiveTenant+1;
                }
            }

            if ($inactiveTenant === count($_tenants)) {
                $allTenantInactive = true;
            }

            if (! empty($alternateLanguage)) {
                $promotionTranslation = \NewsTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('news_id', $promotion->news_id)->first();

                if (!empty($promotionTranslation)) {
                    foreach (['news_name', 'description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($promotionTranslation->{$field}) && $promotionTranslation->{$field} !== '') {
                            $promotion->{$field} = $promotionTranslation->{$field};
                        }
                    }

                    $media = $promotionTranslation->find($promotionTranslation->news_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $promotion->image = $media->path;
                    } else {
                        // back to default image if in the content multilanguage not have image
                        // check the system language
                        $defaultLanguage = $this->getDefaultLanguage($retailer);
                        if ($defaultLanguage !== NULL) {
                            $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                ->where('news_id', $promotion->news_id)->first();

                            // get default image
                            $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                ->media_orig()
                                ->first();

                            if (isset($mediaDefaultLanguage->path)) {
                                $promotion->image = $mediaDefaultLanguage->path;
                            }
                        }
                    }
                }
            }

            $activityPageNotes = sprintf('Page viewed: Promotion Detail, promotion Id: %s', $promotion_id);
            $activityPage->setUser($user)
                ->setActivityName('view_promotion')
                ->setActivityNameLong('View Promotion Detail')
                ->setLocation($retailer)
                ->setObject($promotion)
                ->setNews($promotion)
                ->setModuleName('Promotion')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.mall-promotion', array(
                'page_title' => $promotion->news_name,
                'user' => $user,
                'retailer' => $retailer,
                'promotion' => $promotion,
                'languages' => $languages,
                'all_tenant_inactive' => $allTenantInactive,
                'facebookInfo' => Config::get('orbit.social_login.facebook'),
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
            ));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: Promotion Detail, promotion Id: %s', $promotion_id);
            $activityPage->setUser($user)
                ->setActivityName('view_promotion')
                ->setActivityNameLong('View Promotion Failed')
                ->setLocation($retailer)
                ->setObject($promotion)
                ->setModuleName('Promotion')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Get news list in mall
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getMallNewsList() {
        $user = null;
        $keyword = null;
        $retailer = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $pagetitle = Lang::get('mobileci.page_title.news');

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);

            $prefix = DB::getTablePrefix();
            $mallid = $retailer->merchant_id;

            $news = \News::with('translations')
                            ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                            ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                            ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                            ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                            ->select('*', 'news.description as description');
            // filter by age and gender
            if ($userGender !== null) {
                $news = $news->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $news = $news->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $news = $news->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $news = $news->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            $news = $news->where('news.status', '=', 'active')
                ->where(function ($q) use ($mallid) {
                    $q->where('merchants.parent_id', '=', $mallid)
                      ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', 'news')
                ->whereRaw("? between begin_date and end_date", [$mallTime]);

            OrbitInput::get(
                'keyword',
                function ($keyword) use ($news, $retailer, $alternateLanguage) {
                    $news->leftJoin('news_translations', function($join) use ($alternateLanguage){
                            $join->on('news.news_id', '=', 'news_translations.news_id');
                            $join->where('news_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                        })
                        ->leftJoin('keyword_object', function($join) {
                            $join->on('news.news_id', '=', 'keyword_object.object_id');
                            $join->where('keyword_object.object_type', '=', 'news');
                        })
                        ->leftJoin('keywords', function($join) use ($retailer) {
                            $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                            $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                        })
                        ->where(function($q) use ($keyword) {
                            $q->where('news_translations.news_name', 'like', "%$keyword%")
                                ->orWhere('news_translations.description', 'like', "%$keyword%")
                                ->orWhere('keyword', '=', $keyword);
                        });
                }
            );

            $this->viewItemUserUpdate('news', $user, $retailer);

            $news->groupBy('news.news_id');

            $_news = clone $news;

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $news->take($take);

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $news) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $news->skip($skip);

            $news->orderBy(DB::raw('RAND()'));

            $totalRec = count($_news->get());
            $listOfRec = $news->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $newsTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('news_id', $val->news_id)->first();

                    if (!empty($newsTranslation)) {
                        foreach (['news_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($newsTranslation->{$field}) && $newsTranslation->{$field} !== '') {
                                $val->{$field} = $newsTranslation->{$field};
                            }
                        }

                        $media = $newsTranslation->find($newsTranslation->news_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('news_id', $val->news_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $val->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            if ($listOfRec->isEmpty()) {
                $data = new stdclass();
                $data->status = 0;
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = sizeof($listOfRec);
                $data->records = $listOfRec;
            }

            $languages = $this->getListLanguages($retailer);

            $activityPageNotes = sprintf('Page viewed: %s', 'News List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_news_list')
                ->setActivityNameLong('View News List')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('News')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            $view_data = array(
                'page_title'=>$pagetitle,
                'retailer' => $retailer,
                'data' => $data,
                'active_user' => ($user->status === 'active'),
                'languages' => $languages,
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'user' => $user,
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
            );
            return View::make('mobile-ci.mall-news-list', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'News List');
            $activityPage->setUser($user)
                ->setActivityName('view_news_list')
                ->setActivityNameLong('View News List Failed')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('News')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Get news list in mall
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getSearchNews() {
        $user = null;
        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $maxRecord = (int) Config::get('orbit.pagination.max_record', 50);
            if ($maxRecord <= 0) {
                $maxRecord = Config::get('orbit.pagination.max_record');
            }

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $news = \News::with('translations')
                            ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                            ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                            ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                            ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                            ->select('*', 'news.description as description');
            // filter by age and gender
            if ($userGender !== null) {
                $news = $news->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $news = $news->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $news = $news->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $news = $news->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            OrbitInput::get('ids', function($ids) use ($news)
            {
                $news->whereNotIn('news.news_id', $ids);
            });

            $mallid = $retailer->merchant_id;

            $news = $news->where('news.status', '=', 'active')
                ->where(function ($q) use ($mallid) {
                    $q->where('merchants.parent_id', '=', $mallid)
                      ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', 'news')
                ->whereRaw("? between begin_date and end_date", [$mallTime]);

            OrbitInput::get(
                'keyword',
                function ($keyword) use ($news, $retailer, $alternateLanguage) {
                    $news->leftJoin('news_translations', function($join) use ($alternateLanguage){
                            $join->on('news.news_id', '=', 'news_translations.news_id');
                            $join->where('news_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                        })
                        ->leftJoin('keyword_object', function($join) {
                            $join->on('news.news_id', '=', 'keyword_object.object_id');
                            $join->where('keyword_object.object_type', '=', 'news');
                        })
                        ->leftJoin('keywords', function($join) use ($retailer) {
                            $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                            $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                        })
                        ->where(function($q) use ($keyword) {
                            $q->where('news_translations.news_name', 'like', "%$keyword%")
                                ->orWhere('news_translations.description', 'like', "%$keyword%")
                                ->orWhere('keyword', '=', $keyword);
                        });
                }
            );

            $news = $news->groupBy('news.news_id');

            $_news = clone $news;

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $news->take($take);

            // commenting the skip part because the total records always reduced caused by whereNotIn
            // $skip = 0;
            // OrbitInput::get(
            //     'skip',
            //     function ($_skip) use (&$skip, $news) {
            //         if ($_skip < 0) {
            //             $_skip = 0;
            //         }

            //         $skip = $_skip;
            //     }
            // );
            // $news->skip($skip);

            $news->orderBy(DB::raw('RAND()'));

            $totalRec = $_news->count();
            $listOfRec = $news->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $newsTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('news_id', $val->news_id)->first();

                    if (!empty($newsTranslation)) {
                        foreach (['news_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($newsTranslation->{$field}) && $newsTranslation->{$field} !== '') {
                                $val->{$field} = $newsTranslation->{$field};
                            }
                        }

                        $media = $newsTranslation->find($newsTranslation->news_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('news_id', $val->news_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $val->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            foreach ($listOfRec as $item) {
                $item->image = empty($item->image) ? URL::asset('mobile-ci/images/default_news.png') : URL::asset($item->image);
                $item->url = UrlBlock::blockedRoute('ci-promotion-detail', ['id' => $item->news_id, 'name' => Str::slug($item->news_name)], $this->session);
                $item->redirect_url = URL::route('ci-promotion-detail', ['id' => $item->news_id, 'name' => Str::slug($item->news_name)]);
                $item->name = mb_strlen($item->news_name) > 64 ? mb_substr($item->news_name, 0, 64) . '...' : $item->news_name;
                $item->item_id = $item->news_id;
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            return Response::json($data);

        } catch (Exception $e) {
            switch ($e->getCode()) {
                case Session::ERR_UNKNOWN;
                case Session::ERR_IP_MISS_MATCH;
                case Session::ERR_UA_MISS_MATCH;
                case Session::ERR_SESS_NOT_FOUND;
                case Session::ERR_SESS_EXPIRE;
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = 'session_expired';
                    $data->status = 0;

                    $this->response->data = $data;

                    break;

                default:
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->message = $e->getMessage();
                    $data->status = 0;

                    $this->response->data = $data;
            }

            return Response::json($data);
        }
    }

    /**
     * GET - Pokestop detail page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getMallPokestopDetailView()
    {
        $user = null;
        $product_id = 0;
        $retailer = null;
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        $pokestop = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);
            //Coupon::issueAutoCoupon($retailer, $user, $this->session);

            $languages = $this->getListLanguages($retailer);
            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $mallid = $retailer->merchant_id;

            $pokestop = \News::excludeDeleted()
                        ->where('object_type','pokestop')
                        ->where('mall_id', $mallid)
                        ->with('mediaPokestop')
                        ->first();

            // Get Image from db
            if (isset($pokestop->mediaPokestop->realpath) && $pokestop->mediaPokestop->realpath != '') {
                $pokestop->image = $pokestop->mediaPokestop->realpath;
            } else {
                $pokestop->image = null;
            }

            if (empty($pokestop)) {
                return View::make('mobile-ci.404', array(
                    'page_title'=>Lang::get('mobileci.page_title.not_found'),
                    'retailer'=>$retailer,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                    'languages' => $languages,
                ));
            }

            $product_id = $pokestop->news_id;

            $activityPageNotes = sprintf('Page viewed: Pokestop Detail, pokestop Id: %s', $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_pokestop')
                ->setActivityNameLong('View Pokestop Detail')
                ->setObject($pokestop)
                ->setNews($pokestop)
                ->setLocation($retailer)
                ->setModuleName('Pokestop')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.mall-pokestop-detail', array(
                'page_title' => 'Pok??MAPS<sup>TM</sup>',
                'user' => $user,
                'retailer' => $retailer,
                'pokestop' => $pokestop,
                'languages' => $languages,
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
            ));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: Pokestop Detail, pokestop Id: %s', $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_pokestop')
                ->setActivityNameLong('View Pokestop Detail Failed')
                ->setObject($pokestop)
                ->setLocation($retailer)
                ->setModuleName('Pokestop')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }
    /**
     * GET - News detail page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getMallNewsDetailView()
    {
        $user = null;
        $product_id = 0;
        $retailer = null;
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        $news = null;
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);


            $languages = $this->getListLanguages($retailer);
            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $product_id = trim(OrbitInput::get('id'));

            $mallid = $retailer->merchant_id;
            $mallTime = Carbon::now($retailer->timezone->timezone_name);

            $news = \News::with(['tenants' => function($q) use($retailer) {
                    $q->where('merchants.status', 'active');
                    $q->where('merchants.parent_id', $retailer->merchant_id);
                }])
                ->select('*', 'news.description as description')
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mallid) {
                    $q->where('merchants.parent_id', '=', $mallid)
                      ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.news_id', $product_id)
                ->where('news.status', 'active')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->where('news.object_type', 'news')
                ->first();

            if (empty($news)) {
                // throw new Exception('Product id ' . $product_id . ' not found');
                return View::make('mobile-ci.404', array(
                    'page_title'=>Lang::get('mobileci.page_title.not_found'),
                    'retailer'=>$retailer,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                    'languages' => $languages,
                    'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : ''
                ));
            }

            if (empty($news->image)) {
                $news->image = 'mobile-ci/images/default_news.png';
            }

            // set facebook share url
            $news->facebook_share_url = $this->getFBShareDummyPage('news', $news->news_id, $alternateLanguage->language_id);

            // checking if all tenant linked to this news inactive or not
            // so that if all tenant inactive we can disable the 'see tenant' button on the view
            // for fix bug OM-724
            $_tenants = $news->tenants;

            $allTenantInactive = false;

            $inactiveTenant = 0;

            foreach($_tenants as $key => $value)
            {
                if ($value->status === 'inactive') {
                    $inactiveTenant = $inactiveTenant+1;
                }
            }

            if ($inactiveTenant === count($_tenants)) {
                $allTenantInactive = true;
            }

            // cek if any language active
            if (!empty($alternateLanguage) && !empty($news)) {
                $newsTranslation = \NewsTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('news_id', $news->news_id)->first();

                if (!empty($newsTranslation)) {
                    foreach (['news_name', 'description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($newsTranslation->{$field}) && $newsTranslation->{$field} !== '') {
                            $news->{$field} = $newsTranslation->{$field};
                        }
                    }

                    $media = $newsTranslation->find($newsTranslation->news_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $news->image = $media->path;
                    } else {
                        // back to default image if in the content multilanguage not have image
                        // check the system language
                        $defaultLanguage = $this->getDefaultLanguage($retailer);
                        if ($defaultLanguage !== NULL) {
                            $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                ->where('news_id', $news->news_id)->first();

                            // get default image
                            $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                ->media_orig()
                                ->first();

                            if (isset($mediaDefaultLanguage->path)) {
                                $news->image = $mediaDefaultLanguage->path;
                            }
                        }
                    }
                }
            }

            $activityPageNotes = sprintf('Page viewed: News Detail, news Id: %s', $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_news')
                ->setActivityNameLong('View News Detail')
                ->setObject($news)
                ->setNews($news)
                ->setLocation($retailer)
                ->setModuleName('News')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.mall-news-detail', array(
                'page_title' => $news->news_name,
                'user' => $user,
                'retailer' => $retailer,
                'news' => $news,
                'languages' => $languages,
                'all_tenant_inactive' => $allTenantInactive,
                'facebookInfo' => Config::get('orbit.social_login.facebook'),
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
            ));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: News Detail, news Id: %s', $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_news')
                ->setActivityNameLong('View News Detail Failed')
                ->setObject($news)
                ->setLocation($retailer)
                ->setModuleName('News')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Message list page
     *
     * @param integer    `id`        (required) - The inbox ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getNotificationsView()
    {
        $user = null;
        $retailer = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);

            $languages = $this->getListLanguages($retailer);

            $activityPageNotes = sprintf('Page viewed: %s', 'Notification List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_notification_list')
                ->setActivityNameLong('View Notification List')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('Inbox')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            $view_data = array(
                'page_title' => Lang::get('mobileci.page_title.my_messages'),
                'retailer' => $retailer,
                'active_user' => ($user->status === 'active'),
                'languages' => $languages,
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'user' => $user,
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
            );
            return View::make('mobile-ci.mall-notifications-list', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Notification List');
            $activityPage->setUser($user)
                ->setActivityName('view_notification_list')
                ->setActivityNameLong('View Notification List')
                ->setObject(null)
                ->setLocation($retailer)
                ->setModuleName('Inbox')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Message detail page
     *
     * @param integer    `id`        (required) - The inbox ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getNotificationDetailView()
    {
        $user = null;
        $inbox = null;
        $keyword = null;
        $retailer = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $this->acquireUser($retailer, $user);

            $languages = $this->getListLanguages($retailer);

            $inbox_id = OrbitInput::get('id');

            $inbox = Inbox::excludeDeleted()
                        ->where('user_id', $user->user_id)
                        ->where('merchant_id', $retailer->merchant_id)
                        ->where('inbox_id', $inbox_id)
                        ->first();

            if (! is_object($inbox)) {
                return View::make('mobile-ci.404', [
                    'page_title'    => Lang::get('mobileci.page_title.not_found'),
                    'user'          => $user,
                    'languages'     => $languages,
                    'retailer'      => $retailer,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session)
                ]);
            }

            $inbox->is_read = 'Y';
            $inbox->save();

            switch ($inbox->inbox_type) {
                case 'activation':
                    $activityPageNotes = sprintf('Page viewed: %s', 'Activation Notification Detail Page');
                    $activityPage->setUser($user)
                        ->setActivityName('read_notification')
                        ->setActivityNameLong('Read Activation Notification')
                        ->setObject($inbox)
                        ->setLocation($retailer)
                        ->setModuleName('Inbox')
                        ->setNotes($activityPageNotes)
                        ->responseOK()
                        ->save();
                    break;

                case 'lucky_draw_issuance':
                    $activityPageNotes = sprintf('Page viewed: %s', 'Lucky Draw Number Issuance Notification Detail Page');
                    $activityPage->setUser($user)
                        ->setActivityName('read_notification')
                        ->setActivityNameLong('Read Lucky Draw Number Issuance Notification')
                        ->setObject($inbox)
                        ->setLocation($retailer)
                        ->setModuleName('Inbox')
                        ->setNotes($activityPageNotes)
                        ->responseOK()
                        ->save();
                    break;

                case 'lucky_draw_blast':
                    $activityPageNotes = sprintf('Page viewed: %s', 'Lucky Draw Number Issuance Notification Detail Page');
                    $activityPage->setUser($user)
                        ->setActivityName('read_notification')
                        ->setActivityNameLong('Read Winner Announcement Notification')
                        ->setObject($inbox)
                        ->setLocation($retailer)
                        ->setModuleName('Inbox')
                        ->setNotes($activityPageNotes)
                        ->responseOK()
                        ->save();
                    break;

                case 'coupon_issuance':
                    $activityPageNotes = sprintf('Page viewed: %s', 'Coupon Issuance Notification Detail Page');
                    $activityPage->setUser($user)
                        ->setActivityName('read_notification')
                        ->setActivityNameLong('Read Coupon Issuance Notification')
                        ->setObject($inbox)
                        ->setLocation($retailer)
                        ->setModuleName('Inbox')
                        ->setNotes($activityPageNotes)
                        ->responseOK()
                        ->save();
                    break;

                default:
                    break;
            }

            $view_data = array(
                'page_title' => $inbox->subject,
                'retailer' => $retailer,
                'active_user' => ($user->status === 'active'),
                'languages' => $languages,
                'user_email' => $user->role->role_name !== 'Guest' ? $user->user_email : '',
                'user' => $user,
                'inbox' => $inbox,
                'session' => $this->session,
                'is_logged_in' => UrlBlock::isLoggedIn($this->session),
            );
            return View::make('mobile-ci.mall-notification-detail', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Notification Detail');
            $activityPage->setUser($user)
                ->setActivityName('read_notification')
                ->setActivityNameLong('Read Notification')
                ->setObject($inbox)
                ->setLocation($retailer)
                ->setModuleName('Inbox')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * Get list of downloadable lucky draw number. There is possibilities that
     * one user have hundred even thousands of luckydraw coupons. So, we could
     * not fit all that number into one image. We need to split it into
     * different image.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\View\View
     */
    public function getMallLuckyDrawDownloadList()
    {
        try {
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();
            $lucky_draw_id = OrbitInput::get('id');
            $languages = $this->getListLanguages($retailer);
            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);
            $luckyDraw = LuckyDraw::excludeDeleted()->where('lucky_draw_id', $lucky_draw_id)->first();

            if (! is_object($luckyDraw)) {
                return View::make('mobile-ci.404', [
                    'page_title'    => Lang::get('mobileci.page_title.not_found'),
                    'user'          => $user,
                    'languages'     => $languages,
                    'retailer'      => $retailer,
                    'session' => $this->session,
                    'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                ]);
            }

            if (!empty($alternateLanguage) && !empty($luckyDraw)) {
                $luckyDrawTranslation = \LuckyDrawTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('lucky_draw_id', $luckyDraw->lucky_draw_id)->first();

                $luckyDraw->lucky_draw_name_display = $luckyDraw->lucky_draw_name;

                if (!empty($luckyDrawTranslation)) {
                    foreach (['lucky_draw_name', 'description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($luckyDrawTranslation->{$field}) && $luckyDrawTranslation->{$field} !== '') {
                            $luckyDraw->{$field} = $luckyDrawTranslation->{$field};
                        }
                    }
                }
            }

            $mode = OrbitInput::get('mode', 'view');

            switch ($mode) {
                case 'download':
                    return $this->getMallLuckyDrawDownloadNumber($user->user_id, $luckyDraw->lucky_draw_id);
                    break;

                case 'view':
                default:
                    $totalLuckyDrawNumber = LuckyDrawNumber::active()
                                                           ->where('user_id', $user->user_id)
                                                           ->where('lucky_draw_id', $luckyDraw->lucky_draw_id)
                                                           ->count();
                    $totalPerImage = 160;
                    $totalImage = ceil($totalLuckyDrawNumber / $totalPerImage);

                    return View::make('mobile-ci.lucky-draw-number-download', [
                                     'page_title'   => Lang::get('mobileci.lucky_draw.lucky_draw_download_title'),
                                     'luckydraw'    => $luckyDraw,
                                     'retailer'     => $retailer,
                                     'user'         => $user,
                                     'total_number' => $totalLuckyDrawNumber,
                                     'total_image'  => $totalImage,
                                     'number_per_image'  => $totalPerImage,
                                     'languages' => $languages,
                                     'lucky_draw_id' => $lucky_draw_id,
                                     'session' => $this->session,
                                     'is_logged_in' => UrlBlock::isLoggedIn($this->session),
                    ]);
            }
        } catch (Exception $e) {
            if ($e instanceof UrlException) {
                return $this->redirectIfNotLoggedIn($e, URL::to($e->getRedirectRoute()));
            }

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Get campaign pop up
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getSearchCampaignCardsPopUp() {
        $user = null;
        try {
            $httpCode = 200;
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = null;
            $lang = OrbitInput::get('lang', 'en'); //get user current cookie lang
            $language = \Language::where('name', '=', $lang)->first();
            if (is_object($language)) {
                $alternateLanguage = \MerchantLanguage::excludeDeleted()
                    ->where('merchant_id', '=', $retailer->merchant_id)
                    ->where('language_id', '=', $language->language_id)
                    ->first();
            }

            //$alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);
            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge = $this->calculateAge($user->userDetail->birthdate);
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender = $user->userDetail->gender;
            }

            $mallid = $retailer->merchant_id;
            $prefix = DB::getTablePrefix();

            $promo = DB::table('news')
                ->selectRaw("{$prefix}news.news_id as campaign_id, {$prefix}news.news_name as campaign_name, {$prefix}news.description as campaign_description, {$prefix}news.image as campaign_image, 'promotion' as campaign_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mallid) {
                    $q->where('merchants.parent_id', '=', $mallid)
                      ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', '=', 'promotion')
                ->where('news.status', 'active')
                ->where('news.is_popup', 'Y')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->groupBy('news.news_id');

            $news = DB::table('news')
                ->selectRaw("{$prefix}news.news_id as campaign_id, {$prefix}news.news_name as campaign_name, {$prefix}news.description as campaign_description, {$prefix}news.image as campaign_image, 'news' as campaign_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mallid) {
                    $q->where('merchants.parent_id', '=', $mallid)
                      ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', '=', 'news')
                ->where('news.status', 'active')
                ->where('news.is_popup', 'Y')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->groupBy('news.news_id');

            $coupon = DB::table('promotions')
                ->selectRaw("{$prefix}promotions.promotion_id as campaign_id, {$prefix}promotions.promotion_name as campaign_name, {$prefix}promotions.description as campaign_description, {$prefix}promotions.image as campaign_image, 'coupon' as campaign_type")
                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                ->where(function ($q) use ($mallid) {
                        $q->where('merchants.parent_id', '=', $mallid)
                          ->orWhere('merchants.merchant_id', '=', $mallid);
                    })
                ->where('promotions.is_coupon', '=', 'Y')
                ->where('promotions.is_popup', 'Y')
                ->where('promotions.status', 'active')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->groupBy('promotions.promotion_id');

            if ($userGender !== null) {
                $promo = $promo->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                $news = $news->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $promo = $promo->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    $news = $news->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $promo = $promo->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                        $news = $news->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $promo = $promo->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        $news = $news->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            $promo->orderBy(DB::raw('RAND()'));

            $news->orderBy(DB::raw('RAND()'));

            $coupon->orderBy(DB::raw('RAND()'));

            $results = $promo->unionAll($news)->unionAll($coupon)->get();

            //$campaign_card_total = Config::get('campaign_card_popup_number', 5); <----------- should create config for this number
            $campaign_card_total = 5;
            $max_campaign = count($results) > $campaign_card_total ? $campaign_card_total : count($results);

            shuffle($results);

            // slice shuffled results to 2 parts and shuffle again
            $resultsize = count($results);

            $firsthalf = array_slice($results, 0, ($resultsize / 2));
            $secondhalf = array_slice($results, ($resultsize / 2));
            shuffle($firsthalf);
            shuffle($secondhalf);
            $secondresults = array_merge($firsthalf, $secondhalf);
            shuffle($secondresults);

            $end_results = array_slice($secondresults, 0, $max_campaign);

            foreach($end_results as $near_end_result) {
                $near_end_result->campaign_link = Lang::get('mobileci.campaign_cards.go_to_page');
                if ($near_end_result->campaign_type === 'promotion') {
                    $near_end_result->campaign_url = UrlBlock::blockedRoute('ci-promotion-detail', ['id' => $near_end_result->campaign_id, 'name' => Str::slug($near_end_result->campaign_name)], $this->session);
                    $near_end_result->redirect_campaign_url = URL::route('ci-promotion-detail', ['id' => $near_end_result->campaign_id, 'name' => Str::slug($near_end_result->campaign_name)]);
                    $near_end_result->campaign_image = URL::asset('mobile-ci/images/default_promotion.png');
                } elseif ($near_end_result->campaign_type === 'news') {
                    $near_end_result->campaign_url = UrlBlock::blockedRoute('ci-news-detail', ['id' => $near_end_result->campaign_id, 'name' => Str::slug($near_end_result->campaign_name)], $this->session);
                    $near_end_result->redirect_campaign_url = URL::route('ci-news-detail', ['id' => $near_end_result->campaign_id, 'name' => Str::slug($near_end_result->campaign_name)]);
                    $near_end_result->campaign_image = URL::asset('mobile-ci/images/default_news.png');
                } elseif ($near_end_result->campaign_type === 'coupon') {
                    $near_end_result->campaign_url = UrlBlock::blockedRoute('ci-coupon-detail', ['id' => $near_end_result->campaign_id, 'name' => Str::slug($near_end_result->campaign_name)], $this->session);
                    $near_end_result->redirect_campaign_url = URL::route('ci-coupon-detail', ['id' => $near_end_result->campaign_id, 'name' => Str::slug($near_end_result->campaign_name)]);
                    $near_end_result->campaign_image = URL::asset('mobile-ci/images/default_coupon.png');
                }

                if (!empty($alternateLanguage)) {
                    if ($near_end_result->campaign_type === 'promotion' || $near_end_result->campaign_type === 'news') {
                        $campaignTranslation = \NewsTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('news_id', $near_end_result->campaign_id)->first();
                    } elseif ($near_end_result->campaign_type === 'coupon'){
                        $campaignTranslation = \CouponTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('promotion_id', $near_end_result->campaign_id)->first();
                    }

                    if (!empty($campaignTranslation)) {
                        if ($near_end_result->campaign_type === 'promotion' || $near_end_result->campaign_type === 'news') {

                            //if field translation empty or null, value of field back to english (default)
                            if (isset($campaignTranslation->news_name) && $campaignTranslation->news_name !== '') {
                                $near_end_result->campaign_name = $campaignTranslation->news_name;
                            }
                            if (isset($campaignTranslation->description) && $campaignTranslation->description !== '') {
                                $near_end_result->campaign_description = $campaignTranslation->description;
                            }

                            $media = $campaignTranslation->find($campaignTranslation->news_translation_id)
                                ->media_orig()
                                ->first();

                            if (is_object($media)) {
                                $near_end_result->campaign_image = URL::asset($media->path);
                            } else {
                                // back to default image if in the content multilanguage not have image
                                // check the system language
                                $defaultLanguage = $this->getDefaultLanguage($retailer);
                                if ($defaultLanguage !== NULL) {
                                    $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                        ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                        ->where('news_id', $near_end_result->campaign_id)->first();

                                    // get default image
                                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                        ->media_orig()
                                        ->first();

                                    if (is_object($mediaDefaultLanguage)) {
                                        $near_end_result->campaign_image = URL::asset($mediaDefaultLanguage->path);
                                    }
                                }
                            }
                        } elseif ($near_end_result->campaign_type === 'coupon') {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($campaignTranslation->promotion_name) && $campaignTranslation->promotion_name !== '') {
                                $near_end_result->campaign_name = $campaignTranslation->promotion_name;
                            }
                            if (isset($campaignTranslation->description) && $campaignTranslation->description !== '') {
                                $near_end_result->campaign_description = $campaignTranslation->description;
                            }

                            $media = $campaignTranslation->find($campaignTranslation->coupon_translation_id)
                                ->media_orig()
                                ->first();

                            if (is_object($media)) {
                                $near_end_result->campaign_image = URL::asset($media->path);
                            } else {
                                // back to default image if in the content multilanguage not have image
                                // check the system language
                                $defaultLanguage = $this->getDefaultLanguage($retailer);
                                if ($defaultLanguage !== NULL) {
                                    $contentDefaultLanguage = \CouponTranslation::excludeDeleted()
                                        ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                        ->where('promotion_id', $near_end_result->campaign_id)->first();

                                    // get default image
                                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->coupon_translation_id)
                                        ->media_orig()
                                        ->first();

                                    if (is_object($mediaDefaultLanguage)) {
                                        $near_end_result->campaign_image = URL::asset($mediaDefaultLanguage->path);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $data = new stdclass();
            $data->total_records = count($end_results);
            $data->returned_records = count($end_results);
            $data->records = $end_results;

            if (count($end_results) === 0) {
                $data->records = null;
                $this->response->message = 'No available campaign right now.';
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.inbox.getsearchinbox.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.inbox.getsearchinbox.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.inbox.getsearchinbox.query.error', array($this, $e));

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
            Event::fire('orbit.inbox.getsearchinbox.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.inbox.getsearchinbox.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Get search tenant, coupon, news, promotion, lucky draw
     *
     * @param string    `keyword`        (required) - The search keyword
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Irianto Pratama <irianto@dominopos.com>
     */
    public function getPowerSearch() {
        $user = null;
        $keyword = null;
        $retailer = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('search');

        try {
            $httpCode = 200;

            $keyword = (string) trim(urldecode(OrbitInput::get('keyword')));
            if (empty($keyword) && $keyword !== '0') {
                throw new Exception ('Empty keyword.');
            }

            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = null;
            $lang = OrbitInput::get('lang', 'en'); //get user current cookie lang
            $language = \Language::where('name', '=', $lang)->first();
            if (is_object($language)) {
                $alternateLanguage = \MerchantLanguage::excludeDeleted()
                    ->where('merchant_id', '=', $retailer->merchant_id)
                    ->where('language_id', '=', $language->language_id)
                    ->first();
            }

            //$alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);
            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge = $this->calculateAge($user->userDetail->birthdate);
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender = $user->userDetail->gender;
            }

            $prefix = DB::getTablePrefix();

            $mallid = $retailer->merchant_id;

            $promo = DB::table('news')
                ->selectRaw("{$prefix}news.news_id as object_id, {$prefix}news.news_name as object_name, {$prefix}news.description as object_description, {$prefix}news.image as object_image, 'promotion' as object_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_translations', function($join) use ($language){
                    $join->on('news.news_id', '=', 'news_translations.news_id');
                    $join->where('news_translations.merchant_language_id', '=', $language->language_id);
                })
                ->leftJoin('keyword_object', function($join) {
                    $join->on('news.news_id', '=', 'keyword_object.object_id');
                    $join->where('keyword_object.object_type', '=', 'promotion');
                })
                ->leftJoin('keywords', function($join) use ($retailer) {
                    $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                    $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                })
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                              ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', '=', 'promotion')
                ->where('news.status', 'active')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->where(function($q) use ($keyword) {
                    $q->where('news_translations.news_name', 'like', "%$keyword%")
                        ->orWhere('news_translations.description', 'like', "%$keyword%")
                        ->orWhere('keyword', '=', $keyword);
                });

            $news = DB::table('news')
                ->selectRaw("{$prefix}news.news_id as object_id, {$prefix}news.news_name as object_name, {$prefix}news.description as object_description, {$prefix}news.image as object_image, 'news' as object_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_translations', function($join) use ($language){
                    $join->on('news.news_id', '=', 'news_translations.news_id');
                    $join->where('news_translations.merchant_language_id', '=', $language->language_id);
                })
                ->leftJoin('keyword_object', function($join) {
                    $join->on('news.news_id', '=', 'keyword_object.object_id');
                    $join->where('keyword_object.object_type', '=', 'news');
                })
                ->leftJoin('keywords', function($join) use ($retailer) {
                    $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                    $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                })
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                              ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', '=', 'news')
                ->where('news.status', 'active')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->where(function($q) use ($keyword) {
                    $q->where('news_translations.news_name', 'like', "%$keyword%")
                        ->orWhere('news_translations.description', 'like', "%$keyword%")
                        ->orWhere('keyword', '=', $keyword);
                });

            $coupon = DB::table('promotions')
                ->selectRaw("{$prefix}issued_coupons.promotion_id as object_id, {$prefix}promotions.promotion_name as object_name, {$prefix}promotions.description as object_description, {$prefix}promotions.image as object_image, 'coupon' as object_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('coupon_translations', function($join) use ($language){
                    $join->on('promotions.promotion_id', '=', 'coupon_translations.promotion_id');
                    $join->where('coupon_translations.merchant_language_id', '=', $language->language_id);
                })
                ->leftJoin('keyword_object', function($join) {
                    $join->on('promotions.promotion_id', '=', 'keyword_object.object_id');
                    $join->where('keyword_object.object_type', '=', 'coupon');
                })
                ->leftJoin('keywords', function($join) use ($retailer) {
                    $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                    $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                })
                ->leftJoin('issued_coupons', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                ->where(function ($q) use ($mallid) {
                        $q->where('merchants.parent_id', '=', $mallid)
                          ->orWhere('merchants.merchant_id', '=', $mallid);
                    })
                ->where('issued_coupons.expired_date', '>=', $mallTime)
                ->where('issued_coupons.user_id', '=', $user->user_id)
                ->where('issued_coupons.status', '=', 'active')
                ->where('is_coupon', '=', 'Y')
                ->where('promotions.status', 'active')
                ->where(function($q) use ($keyword) {
                    $q->where('coupon_translations.promotion_name', 'like', "%$keyword%")
                        ->orWhere('coupon_translations.description', 'like', "%$keyword%")
                        ->orWhere('coupon_translations.long_description', 'like', "%$keyword%")
                        ->orWhere('keyword', '=', $keyword);
                });

            $tenant = DB::table('merchants')
                ->selectRaw("{$prefix}merchants.merchant_id as object_id, {$prefix}merchants.name as object_name, {$prefix}merchants.description as object_description, {$prefix}media.path as object_image, 'tenant' as object_type, COUNT(DISTINCT {$prefix}merchants.merchant_id) as counter")
                ->leftJoin('merchant_translations', function($join) use ($language){
                    $join->on('merchants.merchant_id', '=', 'merchant_translations.merchant_id');
                    $join->where('merchant_translations.merchant_language_id', '=', $language->language_id);
                })
                ->leftJoin('keyword_object', function($join) {
                    $join->on('merchants.merchant_id', '=', 'keyword_object.object_id');
                    $join->where('keyword_object.object_type', '=', 'tenant');
                })
                ->leftJoin('keywords', function($join) use ($retailer) {
                    $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                    $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                })
                ->leftJoin('media', function($join) {
                    $join->on('merchants.merchant_id', '=', 'media.object_id')
                        ->where('media.media_name_id', '=', 'retailer_logo')
                        ->where('media.media_name_long', 'like', '%_orig');
                })
                ->where('merchants.object_type', '=', 'tenant')
                ->where('merchants.status', 'active')
                ->where('parent_id', $retailer->merchant_id)
                ->where(function($q) use ($keyword) {
                    $q->where('merchants.name', 'like', "%$keyword%")
                        ->orWhere('merchant_translations.description', 'like', "%$keyword%")
                        ->orWhere('keyword', '=', $keyword);
                });

            $service = DB::table('merchants')
                ->selectRaw("{$prefix}merchants.merchant_id as object_id, {$prefix}merchants.name as object_name, {$prefix}merchants.description as object_description, {$prefix}media.path as object_image, 'service' as object_type, COUNT(DISTINCT {$prefix}merchants.merchant_id) as counter")
                ->leftJoin('merchant_translations', function($join) use ($language){
                    $join->on('merchants.merchant_id', '=', 'merchant_translations.merchant_id');
                    $join->where('merchant_translations.merchant_language_id', '=', $language->language_id);
                })
                ->leftJoin('keyword_object', function($join) {
                    $join->on('merchants.merchant_id', '=', 'keyword_object.object_id');
                    $join->where('keyword_object.object_type', '=', 'tenant');
                })
                ->leftJoin('keywords', function($join) use ($retailer) {
                    $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                    $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                })
                ->leftJoin('media', function($join) {
                    $join->on('merchants.merchant_id', '=', 'media.object_id')
                        ->where('media.media_name_id', '=', 'service_logo')
                        ->where('media.media_name_long', 'like', '%_orig');
                })
                ->where('merchants.object_type', '=', 'service')
                ->where('merchants.status', 'active')
                ->where('parent_id', $retailer->merchant_id)
                ->where(function($q) use ($keyword) {
                    $q->where('merchants.name', 'like', "%$keyword%")
                        ->orWhere('merchant_translations.description', 'like', "%$keyword%")
                        ->orWhere('keyword', '=', $keyword);
                });

            $lucky_draw = DB::table('lucky_draws')
                ->selectRaw("{$prefix}lucky_draws.lucky_draw_id as object_id, {$prefix}lucky_draws.lucky_draw_name as object_name, {$prefix}lucky_draws.description as object_description, {$prefix}lucky_draws.image as object_image, 'lucky_draw' as object_type")
                ->leftJoin('lucky_draw_translations', function($join) use ($language){
                    $join->on('lucky_draws.lucky_draw_id', '=', 'lucky_draw_translations.lucky_draw_id');
                    $join->where('lucky_draw_translations.merchant_language_id', '=', $language->language_id);
                })
                ->where('lucky_draws.status', 'active')
                ->where('mall_id', $retailer->merchant_id)
                ->whereRaw("? between start_date and grace_period_date", [$mallTime])
                ->where(function($q) use ($keyword) {
                    $q->where('lucky_draw_translations.lucky_draw_name', 'like', "%$keyword%")
                        ->orWhere('lucky_draw_translations.description', 'like', "%$keyword%");
                });

            if ($userGender !== null) {
                $promo = $promo->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                $news = $news->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                $coupon = $coupon->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $promo = $promo->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    $news = $news->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    $coupon = $coupon->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $promo = $promo->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                        $news = $news->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                        $coupon = $coupon->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $promo = $promo->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        $news = $news->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        $coupon = $coupon->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            $_promo = clone($promo);
            $_news = clone($news);
            $_coupon = clone($coupon);
            $_tenant = clone($tenant);
            $_service = clone($service);
            $_lucky_draw = clone($lucky_draw);

            $limit = 0; // <---- Config;
            $promo->groupBy('news.news_id')->orderBy(DB::raw('RAND()'))->limit($limit);
            $news->groupBy('news.news_id')->orderBy(DB::raw('RAND()'))->limit($limit);
            $coupon->groupBy('promotions.promotion_id')->orderBy(DB::raw('RAND()'))->limit($limit);
            $tenant->groupBy('merchants.merchant_id')->orderBy(DB::raw('RAND()'))->limit($limit);
            $service->groupBy('merchants.merchant_id')->orderBy(DB::raw('RAND()'))->limit($limit);
            $lucky_draw->groupBy('lucky_draws.lucky_draw_id')->orderBy(DB::raw('RAND()'))->limit($limit);

            $search_results = [];

            foreach ($promo->get() as $promo_result) {
                $search_results[] = $promo_result;
            }
            foreach ($news->get() as $news_result) {
                $search_results[] = $news_result;
            }
            foreach ($coupon->get() as $coupon_result) {
                $search_results[] = $coupon_result;
            }
            foreach ($tenant->get() as $tenant_result) {
                $search_results[] = $tenant_result;
            }
            foreach ($service->get() as $service_result) {
                $search_results[] = $service_result;
            }
            foreach ($lucky_draw->get() as $lucky_draw_result) {
                $search_results[] = $lucky_draw_result;
            }

            $grouped_search_result = new stdclass();
            $grouped_search_result->tenants = [];
            $grouped_search_result->tenants_counts = $_tenant->count();
            $grouped_search_result->tenants_url = UrlBlock::blockedRoute('ci-tenant-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            $grouped_search_result->tenants_redirect_url = URL::route('ci-tenant-list', ['keyword' => htmlspecialchars($keyword)]);
            $grouped_search_result->services = [];
            $grouped_search_result->services_counts = $_service->count();
            $grouped_search_result->services_url = UrlBlock::blockedRoute('ci-service-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            $grouped_search_result->services_redirect_url = URL::route('ci-service-list', ['keyword' => htmlspecialchars($keyword)]);
            $grouped_search_result->news = [];
            $grouped_search_result->news_counts = $_news->count();
            $grouped_search_result->news_url = UrlBlock::blockedRoute('ci-news-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            $grouped_search_result->news_redirect_url = URL::route('ci-news-list', ['keyword' => htmlspecialchars($keyword)]);
            $grouped_search_result->promotions = [];
            $grouped_search_result->promotions_counts = $_promo->count();
            $grouped_search_result->promotions_url = UrlBlock::blockedRoute('ci-promotion-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            $grouped_search_result->promotions_redirect_url = URL::route('ci-promotion-list', ['keyword' => htmlspecialchars($keyword)]);
            $grouped_search_result->coupons = [];
            $grouped_search_result->coupons_counts = count($_coupon->groupBy('promotions.promotion_id')->get());
            $grouped_search_result->coupons_url = UrlBlock::blockedRoute('ci-coupon-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            $grouped_search_result->coupons_redirect_url = URL::route('ci-coupon-list', ['keyword' => htmlspecialchars($keyword)]);
            $grouped_search_result->lucky_draws = [];
            $grouped_search_result->lucky_draws_counts = $_lucky_draw->count();
            $grouped_search_result->lucky_draws_url = UrlBlock::blockedRoute('ci-luckydraw-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            $grouped_search_result->lucky_draws_redirect_url = URL::route('ci-luckydraw-list', ['keyword' => htmlspecialchars($keyword)]);

            foreach($search_results as $near_end_result) {
                if ($near_end_result->object_type === 'promotion') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-promotion-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)], $this->session);
                    $near_end_result->object_redirect_url = URL::route('ci-promotion-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)]);
                    $near_end_result->object_image = URL::asset('mobile-ci/images/default_promotion.png');
                } elseif ($near_end_result->object_type === 'news') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-news-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)], $this->session);
                    $near_end_result->object_redirect_url = URL::route('ci-news-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)]);
                    $near_end_result->object_image = URL::asset('mobile-ci/images/default_news.png');
                } elseif ($near_end_result->object_type === 'coupon') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-coupon-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)], $this->session);
                    $near_end_result->object_redirect_url = URL::route('ci-coupon-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)]);
                    $near_end_result->object_image = URL::asset('mobile-ci/images/default_coupon.png');
                } elseif ($near_end_result->object_type === 'tenant') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-tenant-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)], $this->session);
                    $near_end_result->object_redirect_url = URL::route('ci-tenant-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)]);
                    if (! is_null($near_end_result->object_image)) {
                        $near_end_result->object_image = URL::asset($near_end_result->object_image);
                    } else {
                        $near_end_result->object_image = URL::asset('mobile-ci/images/default_tenants_directory.png');
                    }
                } elseif ($near_end_result->object_type === 'service') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-service-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)], $this->session);
                    $near_end_result->object_redirect_url = URL::route('ci-service-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)]);
                    if (! is_null($near_end_result->object_image)) {
                        $near_end_result->object_image = URL::asset($near_end_result->object_image);
                    } else {
                        $near_end_result->object_image = URL::asset('mobile-ci/images/default_services_directory.png');
                    }
                } elseif ($near_end_result->object_type === 'lucky_draw') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-luckydraw-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)], $this->session);
                    $near_end_result->object_redirect_url = URL::route('ci-luckydraw-detail', ['id' => $near_end_result->object_id, 'name' => Str::slug($near_end_result->object_name)]);
                    $near_end_result->object_image = URL::asset('mobile-ci/images/default_lucky_number.png');
                }

                if (!empty($alternateLanguage)) {
                    if ($near_end_result->object_type === 'promotion' || $near_end_result->object_type === 'news') {
                        $objectTranslation = \NewsTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('news_id', $near_end_result->object_id)->first();
                    } elseif ($near_end_result->object_type === 'coupon'){
                        $objectTranslation = \CouponTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('promotion_id', $near_end_result->object_id)->first();
                    } elseif ($near_end_result->object_type === 'tenant' || $near_end_result->object_type === 'service'){
                        $objectTranslation = \MerchantTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('merchant_id', $near_end_result->object_id)->first();
                    } elseif ($near_end_result->object_type === 'lucky_draw'){
                        $objectTranslation = \LuckyDrawTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('lucky_draw_id', $near_end_result->object_id)->first();
                    }

                    if (!empty($objectTranslation)) {
                        if ($near_end_result->object_type === 'promotion' || $near_end_result->object_type === 'news') {

                            //if field translation empty or null, value of field back to english (default)
                            if (isset($objectTranslation->news_name) && $objectTranslation->news_name !== '') {
                                $near_end_result->object_name = $objectTranslation->news_name;
                            }
                            if (isset($objectTranslation->description) && $objectTranslation->description !== '') {
                                $near_end_result->object_description = $objectTranslation->description;
                            }

                            $media = $objectTranslation->find($objectTranslation->news_translation_id)
                                ->media_orig()
                                ->first();

                            if (is_object($media)) {
                                $near_end_result->object_image = URL::asset($media->path);
                            } else {
                                // back to default image if in the content multilanguage not have image
                                // check the system language
                                $defaultLanguage = $this->getDefaultLanguage($retailer);
                                if ($defaultLanguage !== NULL) {
                                    $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                        ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                        ->where('news_id', $near_end_result->object_id)->first();

                                    // get default image
                                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                        ->media_orig()
                                        ->first();

                                    if (is_object($mediaDefaultLanguage)) {
                                        $near_end_result->object_image = URL::asset($mediaDefaultLanguage->path);
                                    }
                                }
                            }
                        } elseif ($near_end_result->object_type === 'coupon') {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($objectTranslation->promotion_name) && $objectTranslation->promotion_name !== '') {
                                $near_end_result->object_name = $objectTranslation->promotion_name;
                            }
                            if (isset($objectTranslation->description) && $objectTranslation->description !== '') {
                                $near_end_result->object_description = $objectTranslation->description;
                            }

                            $media = $objectTranslation->find($objectTranslation->coupon_translation_id)
                                ->media_orig()
                                ->first();

                            if (is_object($media)) {
                                $near_end_result->object_image = URL::asset($media->path);
                            } else {
                                // back to default image if in the content multilanguage not have image
                                // check the system language
                                $defaultLanguage = $this->getDefaultLanguage($retailer);
                                if ($defaultLanguage !== NULL) {
                                    $contentDefaultLanguage = \CouponTranslation::excludeDeleted()
                                        ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                        ->where('promotion_id', $near_end_result->object_id)->first();

                                    // get default image
                                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->coupon_translation_id)
                                        ->media_orig()
                                        ->first();

                                    if (is_object($mediaDefaultLanguage)) {
                                        $near_end_result->object_image = URL::asset($mediaDefaultLanguage->path);
                                    }
                                }
                            }
                        } elseif ($near_end_result->object_type === 'tenant' || $near_end_result->object_type === 'service') {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($objectTranslation->description) && $objectTranslation->description !== '') {
                                $near_end_result->object_description = $objectTranslation->description;
                            }
                        } elseif ($near_end_result->object_type === 'lucky_draw') {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($objectTranslation->lucky_draw_name) && $objectTranslation->lucky_draw_name !== '') {
                                $near_end_result->object_name = $objectTranslation->lucky_draw_name;
                            }
                            if (isset($objectTranslation->description) && $objectTranslation->description !== '') {
                                $near_end_result->object_description = $objectTranslation->description;
                            }

                            $media = $objectTranslation->find($objectTranslation->lucky_draw_translation_id)
                                ->media_orig()
                                ->first();

                            if (is_object($media)) {
                                $near_end_result->object_image = URL::asset($media->path);
                            } else {
                                // back to default image if in the content multilanguage not have image
                                // check the system language
                                $defaultLanguage = $this->getDefaultLanguage($retailer);
                                if ($defaultLanguage !== NULL) {
                                    $contentDefaultLanguage = \LuckyDrawTranslation::excludeDeleted()
                                        ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                        ->where('lucky_draw_id', $near_end_result->object_id)->first();

                                    // get default image
                                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->lucky_draw_translation_id)
                                        ->media_orig()
                                        ->first();

                                    if (is_object($mediaDefaultLanguage)) {
                                        $near_end_result->object_image = URL::asset($mediaDefaultLanguage->path);
                                    }
                                }
                            }
                        }
                    }
                }

                $near_end_result->object_description = nl2br($near_end_result->object_description);

                if ($near_end_result->object_type === 'promotion') {
                    $grouped_search_result->promotions[] = $near_end_result;
                } elseif ($near_end_result->object_type === 'news') {
                    $grouped_search_result->news[] = $near_end_result;
                } elseif ($near_end_result->object_type === 'coupon') {
                    $grouped_search_result->coupons[] = $near_end_result;
                } elseif ($near_end_result->object_type === 'tenant') {
                    $grouped_search_result->tenants[] = $near_end_result;
                } elseif ($near_end_result->object_type === 'service') {
                    $grouped_search_result->services[] = $near_end_result;
                } elseif ($near_end_result->object_type === 'lucky_draw') {
                    $grouped_search_result->lucky_draws[] = $near_end_result;
                }
            }

            $data = new stdclass();
            $data->total_records = count($search_results);
            $data->returned_records = count($search_results);
            $data->records = $search_results;
            $data->grouped_records = $grouped_search_result;

            if (count($search_results) === 0) {
                $data->records = null;
                $this->response->message = 'No results found.';
            }

            $activityPageNotes = sprintf('Keyword Searched: %s', $keyword);
            $activityPage->setUser($user)
                    ->setActivityName('search_keyword')
                    ->setActivityNameLong('Search')
                    ->setObject(null)
                    ->setLocation($retailer)
                    ->setModuleName('Search')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();

            $this->response->data = $data;
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
            $this->response->message = [$e->getMessage(), $e->getLine(), $e->getFile()];

            switch ($e->getCode()) {
                case Session::ERR_UNKNOWN;
                case Session::ERR_IP_MISS_MATCH;
                case Session::ERR_UA_MISS_MATCH;
                case Session::ERR_SESS_NOT_FOUND;
                case Session::ERR_SESS_EXPIRE;
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->grouped_records = null;
                    $data->message = 'session_expired';

                    $this->response->data = $data;

                    break;

                default:
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->grouped_records = null;
                    $data->message = $e->getMessage();

                    $this->response->data = $data;
            }
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * Method to ouput the download file to the user. Minimize the overhead
     * by using raw queries and avoiding Eloquent.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $userId - The user ID
     * @param int $luckyDrawId - The Lucky Draw ID
     * @return string
     */
    protected function getMallLuckyDrawDownloadNumber($userId, $luckyDrawId)
    {
        $prefix = DB::getTablePrefix();
        $default = Config::get('database.default');
        $dbConfig = Config::get('database.connections.' . $default);
        $mall = $this->getRetailerInfo();

        $pdo = new PDO("mysql:host={$dbConfig['read']['host']};dbname={$dbConfig['database']}", $dbConfig['username'], $dbConfig['password']);
        $query = $pdo->query("SELECT * FROM {$prefix}lucky_draws
                              where lucky_draw_id='{$luckyDrawId}' and status='active' LIMIT 1");

        $luckyDraw = $query->fetch(PDO::FETCH_ASSOC);

        $countQuery = $pdo->query("SELECT count(*) as total FROM {$prefix}lucky_draw_numbers
                                  where user_id='{$userId}' and lucky_draw_id='{$luckyDrawId}'");
        $numberOfLuckyDraw = $countQuery->fetch(PDO::FETCH_ASSOC);
        $numberOfLuckyDraw = (int)$numberOfLuckyDraw['total'];

        // Pagination config
        $currentPage = OrbitInput::get('page', 1);
        $take = 160;
        $start = ($currentPage - 1)  * $take;
        $totalPages = ceil($numberOfLuckyDraw / $take);

        // Image configuration
        $fontSize = 16;
        $xpos = 14;
        $ypos = 5;

        $heighPerLine = 20; // points
        $imageWidth = 595;  // A4 Paper 72ppi

        // Header height + total line + Footer height
        $rowHeight = $take / 5;
        $imageHeight = floor(100 + ($heighPerLine * $rowHeight) + 100);
        $imageHeight = 842; // A4 Paper 72ppi

        $im = imagecreatetruecolor($imageWidth, $imageHeight);
        $black = imagecolorallocate($im, 0, 0, 0);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $white);

        $header = sprintf('%-12s: %s', 'Lucky Draw', $luckyDraw['lucky_draw_name']);
        imagestring($im, $fontSize, $xpos, $ypos, $header, $black);

        $periodHumanStart = date('d/m/Y H:i', strtotime($luckyDraw['start_date']));
        $periodHumanEnd = date('d/m/Y H:i', strtotime($luckyDraw['end_date']));
        $header = sprintf('%-12s: %s - %s', 'Period', $periodHumanStart, $periodHumanEnd);

        $ypos += $heighPerLine;
        imagestring($im, $fontSize, $xpos, $ypos, $header, $black);

        $totalSentences = 'Total of lucky draw number(s) you earn per date';
        $ypos += $heighPerLine * 2;
        imagestring($im, $fontSize, $xpos, $ypos, $totalSentences, $black);

        $mallTime = $mall->timezone->timezone_name;
        $today = Carbon::now($mallTime);

        $totalSentences = sprintf('%s %s %s.', $today, 'is',$numberOfLuckyDraw);
        $ypos += $heighPerLine;
        imagestring($im, $fontSize, $xpos, $ypos, $totalSentences, $black);

        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $uresult = $pdo->query("SELECT * FROM {$prefix}lucky_draw_numbers
                                where user_id='{$userId}' and lucky_draw_id='{$luckyDrawId}' order by issued_date desc,
                                lucky_draw_number_code desc
                                limit $start, $take");

        if ($uresult) {
            $i = 0;
            $ypos += $heighPerLine * 2;
            while ($row = $uresult->fetch(PDO::FETCH_ASSOC)) {
                $string = sprintf('%10s', $row['lucky_draw_number_code']);

                $yplus = 40;
                if ($i++ % 5 === 0) {
                    $string = sprintf('%-10s', $row['lucky_draw_number_code']);
                    $ypos += $heighPerLine;
                    $xpos = 14;
                    $yplus = 0;
                }
                imagestring($im, $fontSize, $xpos, $ypos, $string, $black);
                $xpos += 80 + $yplus;
            }

            $goodLuckString = 'Good luck!';
            $xpos = 14;
            $ypos += $heighPerLine * 2;
            imagestring($im, $fontSize, $xpos, $ypos, $goodLuckString, $black);

            $pageInfoString = sprintf('%s %s %s', $currentPage, 'of', $totalPages);
            $ypos += $heighPerLine;
            $xpos = $imageWidth - 150;
            imagestring($im, 5, $xpos, $ypos, $pageInfoString, $black);

            $file = sprintf('lucky-draw_image-%s_%s.png', $currentPage, date('d-m-Y'));
            $file = storage_path() . '/views/' . $file;

            imagepng($im, $file);
            imagedestroy($im);

            return Response::download($file);
        }
    }

    /**
     * get object from array
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return object
     */
    public function getObjFromArray($haystacks, $needle) {
        foreach($haystacks as $haystack) {
            if($needle == $haystack->setting_name) {
                return $haystack;
            }
        }

        return false;
    }

    /**
     * Proceed payload_login data
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    protected function proceedPayloadData()
    {
        // The sign-in view put the payload from query string to post body on AJAX call
        if (! isset($_POST['payload_login'])) {
            return;
        }

        $payload = $_POST['payload_login'];

        // Decrypt the payload
        $key = md5('--orbit-mall--');
        $payload = (new Encrypter($key))->decrypt($payload);

        // The data is in url encoded
        parse_str($payload, $data);

        // email, fname, lname, gender, mac, ip, login_from
        $email = isset($data['email']) ? $data['email'] : '';
        $fname = isset($data['fname']) ? $data['fname'] : '';

        return ['email' => $email, 'fname' => $fname];
    }

    /**
     * Method to group the issued coupon number based on the coupon name.
     *
     * Array (
     *    '101' => 'A',
     *    '102' => 'A',
     *    '103' => 'B'
     * )
     *
     * Becomes
     *
     * Array(
     *  'A' => [
     *      '101',
     *      '102',
     *  ],
     *  'B'  => [
     *     '103'
     *  ]
     * )
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $source the Array element
     * @return array
     */
    protected function flipArrayElement($source)
    {
        $flipped = [];

        $names = array_flip(array_unique(array_values($source)));
        foreach ($names as $key=>$name) {
            $names[$key] = [];
        }

        foreach ($source as $number=>$name) {
            $flipped[$name][] = $number;
        }

        return $flipped;
    }

    /**
     * Insert issued coupon numbers into inbox table.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $userId - The user id
     * @param array $coupons - Issued Coupons
     * @param array $couponNames - Issued Coupons with name based
     * @return void
     */
    protected function insertCouponInbox($userId, $coupons, $couponNames)
    {
        $user = User::find($userId);

        if (empty($user)) {
            throw new Exception ('Customer user ID not found.');
        }

        $name = $user->getFullName();
        $name = $name ? $name : $user->email;
        $subject = 'Coupon';

        $retailerId = Config::get('orbit.shop.id');

        $inbox = new Inbox();
        $inbox->user_id = $userId;
        $inbox->merchant_id = $retailerId;
        $inbox->from_id = 0;
        $inbox->from_name = 'Orbit';
        $inbox->subject = $subject;
        $inbox->content = '';
        $inbox->inbox_type = 'alert';
        $inbox->status = 'active';
        $inbox->is_read = 'N';
        $inbox->save();

        $retailer = Retailer::isMall()->where('merchant_id', $retailerId)->first();
        $data = [
            'fullName'          => $name,
            'subject'           => 'Coupon',
            'inbox'             => $inbox,
            'retailerName'      => $retailer->name,
            'numberOfCoupon'    => count($coupons),
            'coupons'           => $couponNames,
            'mallName'          => $retailer->name
        ];

        // $template = View::make('mobile-ci.push-notification-coupon', $data);
        // $template = $template->render();

        $inbox->content = $template;
        $inbox->save();
    }

    /**
     * Returns an appropriate MerchantLanguage (if any) that the user wants and the mall supports.
     *
     * @param \User $user
     * @param \Mall $mall the mall
     * @return \MerchantLanguage the language or null if a matching one is not found.
     */
    private function getAlternateMerchantLanguage($user, $mall)
    {
        $priority = ['cookie', 'browser', 'mall_setting'];
        $getters = [
            'cookie' => function ($user) {
                // cannot use Cookie:: or Request::cookie, those insist on signed cookies.
                if (!array_key_exists('orbit_preferred_language', $_COOKIE)) {
                    return null;
                }
                return $_COOKIE['orbit_preferred_language'];
            },
            'browser' => function ($user) {
                $lang = \Request::server('HTTP_ACCEPT_LANGUAGE', null);
                if ($lang === null) {
                    return null;
                }
                return substr($lang, 0, 2);
            },
            'mall_setting' => function ($q) use ($mall) {
                $mobile_default_language = $mall->mobile_default_language;
                // if user has no preference use default
                if (empty($mobile_default_language)) {
                    return null;
                }
                return $mobile_default_language;
            }
        ];

        $language = [];
        $selected_language = null;
        foreach ($priority as $method) {
            $getter = $getters[$method];
            $name = $getter($user);
            if ($name === null) {
                // method does not return language, try next one
                continue;
            }
            $selected_language = null;
            if (array_key_exists($name, $language)) {
                $selected_language = $language[$name];
            } else {
                $language[$name] = \Language::where('name', '=', $name)->first();
                $selected_language = $language[$name];
            }
            if ($selected_language === null) {
                // language returned by method not found
                continue;
            }
            $alternateLanguage = \MerchantLanguage::excludeDeleted()
                ->where('merchant_id', '=', $mall->merchant_id)
                ->where('language_id', '=', $selected_language->language_id)
                ->first();

            if ($alternateLanguage !== null) {
                return $alternateLanguage;
            }
        }
        // above methods did not result in any selected language, use mall default
        return null;
    }

    /**
     * Returns an appropriate MerchantLanguage (if any) that the user wants and the mall supports.
     *
     * @param \Mall $mall the mall
     * @return \MerchantLanguage the language or null if a matching one is not found.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    private function getDefaultLanguage($mall)
    {
        // English is default language
        $deflang = \Mall::where('merchant_id', $mall->merchant_id)->first();
        $language = \Language::where('name', '=', $deflang->mobile_default_language)->first();
        if(isset($language) && count($language) > 0){
            $defaultLanguage = \MerchantLanguage::excludeDeleted()
                ->where('merchant_id', '=', $mall->merchant_id)
                ->where('language_id', '=', $language->language_id)
                ->first();

            if ($defaultLanguage !== null) {
                return $defaultLanguage;
            }
        }

        // above methods did not result in any selected language, use mall default
        return null;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $tenants
     * @param \MerchantLanguage $alternateLanguage
     */
    private function maybeJoinWithTranslationsTable($tenants, $alternateLanguage)
    {
        if (!empty($alternateLanguage)) {
            // join to translations table so can use to search, sort, and overwrite fields
            $prefix = DB::getTablePrefix();

            $tenants->leftJoin('merchant_translations', function ($join) use ($alternateLanguage) {
                $join->on('merchants.merchant_id', '=', 'merchant_translations.merchant_id');
                $join->where('merchant_translations.merchant_language_id', '=',
                    $alternateLanguage->language_id);
            });

            // and overwrite fields with alternate language fields if present
            foreach (['name', 'description', 'ticket_header', 'ticket_footer'] as $field) {
                $tenants->addSelect([
                    DB::raw("COALESCE(${prefix}merchant_translations.${field}, ${prefix}merchants.${field}) as ${field}")
                ]);
            }
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $categories
     * @param \MerchantLanguage $alternateLanguage
     */
    private function maybeJoinWithCategoryTranslationsTable($categories, $alternateLanguage)
    {
        // join to translations table so can use to search, sort, and overwrite fields
        $prefix = DB::getTablePrefix();

        $categories->leftJoin('category_translations', function ($join) use ($alternateLanguage) {
            $join->on('categories.category_id', '=', 'category_translations.category_id');
            $join->where('category_translations.merchant_language_id', '=',
                $alternateLanguage->language_id);
            $join->where('category_translations.category_name', '!=', '');
        });
        // and overwrite fields with alternate language fields if present
        foreach (['category_name', 'description'] as $field) {
            $categories->addSelect([
                DB::raw("COALESCE(${prefix}category_translations.${field}, ${prefix}categories.${field}) as ${field}")
            ]);
        }
        $categories->orderBy('category_name', 'ASC');
    }

    /**
    * POST - Set language choice by customer
    *
    * @param string     `lang`          name of language
    *
    * @author Firmansyah <firmansyah@dominopos.com>
    * @author Irianto Pratama <irianto@dominopos.com>
    */
    public function postLanguagebySelected($lang_name = null)
    {
        $lang_name = OrbitInput::post('lang');

        // Get current mall object
        $current_mall = $this->getRetailerInfo();
        if ($lang_name !== null) {
            //check exist lang in db
            $lang_count = Language::where('name', '=' , $lang_name)->count();

            //set cookies
            if ($lang_count > 0) {
                $date_of_expiry = time() + (31556926 * 5) ; // where 31556926 is total seconds for a year.
                setcookie( "orbit_preferred_language", $lang_name, $date_of_expiry, '/' );
            } else {
                setcookie( "orbit_preferred_language", null, -3600, '/' );
            }
        }

        return \Redirect::to('/customer/home');
   }

    /**
     * @param $user
     * @param $retailer
     * @throws Exception
     */
    public function loginStage2($user, $retailer)
    {
        try {
            $notAllowedStatus = ['inactive'];

            $lowerCasedStatus = strtolower($user->status);
            if (in_array($lowerCasedStatus, $notAllowedStatus)) {
                throw new Exception('You are not allowed to login. Please check with Customer Service.', 13);
            }

            // if a valid MAC specified, associate the MAC with the given email if not associated yet
            $mac = OrbitInput::get('mac_address', '');
            if ($mac !== '') {
                $addr_object = new MacAddr($mac);
                if ($addr_object->isValid()) {
                    $addr_entity = \MacAddress::excludeDeleted()->where('user_email', '=', $user->user_email)->where('mac_address', '=', $mac)->first();
                    if ($addr_entity === null) {
                        $addr_entity = new \MacAddress();
                        $addr_entity->user_email = $user->user_email;
                        $addr_entity->mac_address = $mac;
                        $addr_entity->status = 'active';
                        $addr_entity->save();
                    }
                }
            }

            $user_detail = UserDetail::where('user_id', $user->user_id)->first();
            $user_detail->last_visit_shop_id = $retailer->merchant_id;
            $user_detail->last_visit_any_shop = Carbon::now($retailer->timezone->timezone_name);
            $user_detail->save();

            $user->setHidden(array('user_password', 'apikey'));

            // $user = $this->getLoggedInUser();

            //

            $this->response->data = $user;

            $this->commit();

            // @param: Controller, User, Mall/Retailer
            Event::fire('orbit.postlogininshop.login.done', [$this, $user, $retailer]);
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        }

        return $this->render();

    }

    /**
     * Runs on box.
     *
     * Returns response with redirect_to set to URL of cloud login.
     * Passes email, retailer_id, callback_url in parameters
     *
     * @param string $email
     * @param Mall $retailer
     * @return \OrbitShop\API\v1\ResponseProvider|string
     */
    private function redirectToCloud($email, $password, $retailer, $payload = '', $from = '', $mac_address = '') {
        $this->response->code = 302; // must not be 0
        $this->response->status = 'success';
        $this->response->message = 'Redirecting to cloud'; // stored in activity by IntermediateLoginController
        $url = Config::get('orbit.registration.mobile.cloud_login_url');

        $callback_url = URL::route('customer-login-callback');
        $callback_req = \Symfony\Component\HttpFoundation\Request::create(
            $callback_url, 'GET', ['mac_address' => $mac_address]);

        $from_captive = OrbitInput::post('from_captive', 'no');
        $auto_login = OrbitInput::post('auto_login', 'no');
        $socmed_redirect_to = OrbitInput::post('socmed_redirect_to', '');

        $values = [
            'email' => $email,
            'password' => $password,
            'retailer_id' => $retailer->merchant_id,
            'callback_url' => $callback_req->getUri(),
            'payload' => $payload,
            'from' => $from,
            'full_data' => 'no',
            'check_only' => 'no',
            'auto_login' => $auto_login,
            'from_captive' => $from_captive,
            'socmed_redirect_to' => $socmed_redirect_to,
        ];

        Log::info('-- CI REDIRECT TO CLOUD getUri(): ' . $callback_req->getUri());
        // Log::info('-- CI REDIRECT TO CLOUD Cloud Value: ' . $values);

        $values = CloudMAC::wrapDataFromBox($values);

        $req = \Symfony\Component\HttpFoundation\Request::create($url, 'GET', $values);
        $this->response->data = [
            'redirect_to' => $req->getUri(),
        ];
        return $this->render();
    }

    /**
     * Called from: IntermediateAPIController getCloudLogin
     *
     * Gets: email, retailer_id
     *
     * Calls: LoginAPIController postRegisterUserInShop
     *
     * Returns: { user_id: ..., user_email: ..., user_detail_id: ..., apikey_id: ... }
     *
     * @param bool $forceReload force reload of box user, userdetail data.
     * @param bool $forceInsert
     * @return \OrbitShop\API\v1\ResponseProvider|string
     */
    public function getCloudLogin($forceReload = true, $forceInsert = true)
    {
        $this->beginTransaction();
        try {
            // getCloudLogin uses: GET[email, retailer_id]
            // postRegisterUserInShop: requires: POST[email]
            $retailer = null;
            OrbitInput::get('retailer_id', function ($id) use (&$retailer) {
                $retailer = Mall::with('parent', 'settings')->where('merchant_id', $id)->first();
            });
            if ($retailer === null) {
                OrbitShopAPI::throwInvalidArgument('Retailer not found');
            }
            $email = OrbitInput::get('email');
            $password = OrbitInput::get('password');
            $from = OrbitInput::get('from', 'form');

            $socialid = null;

            $user = User::with('apikey', 'userdetail', 'role')
                ->excludeDeleted()
                ->where('user_email', $email)
                ->whereHas(
                    'role',
                    function ($query) {
                        $query->where('role_name', 'Consumer');
                        // guest not included here because guest logins should be seeded in initial sync
                        // and there should be no need to go to cloud for guest login
                    }
                )->sharedLock()
                ->first();

            if ($user === null && !$forceInsert) {
                // just say now that it is not found
                $this->response->code = 0;
                $this->response->status = 'success';
                $this->response->data = (object)[
                    'user_id' => null,
                ];
                $this->commit();
                return $this->render();
            }

            if ($user === null) {
                $_POST['email'] = $email;
                $_POST['password'] = $password;
                $_POST['from'] = $from;

                $response = \LoginAPIController::create('raw')->setRetailerId(OrbitInput::get('retailer_id'))->setUseTransaction(false)->postRegisterUserInShop();

                if ($response->code !== 0) {
                    throw new Exception($response->message, $response->code);
                }

                $user = $response->data;
                $mall = Mall::active()->where('merchant_id', OrbitInput::get('retailer_id'))->first();

                SignInRecorder::setSignInActivity($user, 'form', $mall, NULL, TRUE);
            }

            $payload = OrbitInput::get('payload');

            if (! empty($payload)) {
                // Decrypt the payload
                $key = md5(static::PAYLOAD_KEY);
                $payload = (new Encrypter($key))->decrypt($payload);

                // The data is in url encoded
                parse_str($payload, $data);

                $from = isset($data['login_from']) ? $data['login_from'] : 'form';
                $socialid = isset($data['social_id']) ? $data['social_id'] : null;
            }

            // @author Irianto Pratama <irianto@dominopos.com>
            // send email if user status pending
            $socialmediaList = ['facebook', 'google'];
            if ($user->status === 'pending' && ! in_array($from, $socialmediaList)) {

                $mall_time = Carbon::now($retailer->timezone->timezone_name);
                $pending_date = $mall_time;

                $token = Token::where('status', 'active')
                              ->where('token_name','user_registration_mobile')
                              ->where('user_id', $user->user_id)
                              ->orderBy('created_at', 'desc')
                              ->first();

                if (! empty($token)) {
                    $pending_date = date('Y-m-d', date(strtotime("+1 day", strtotime($token->created_at))));
                }

                if (empty($token) || ($pending_date <= $mall_time)) {
                    // Send email process to the queue
                    \Queue::push('Orbit\\Queue\\RegistrationMail', [
                        'user_id' => $user->user_id,
                        'merchant_id' => $retailer->merchant_id,
                        'mode' => 'gotomalls',
                    ]);
                }
            }

            $acq = \UserAcquisition::where('user_id', $user->user_id)
                ->where('acquirer_id', $retailer->merchant_id)
                ->orderBy('created_at', 'desc')
                ->lockForUpdate()->first();

            if (($acq === null || $acq->signup_via === 'cs') && $forceInsert) {
                $from = empty($from) ? 'form' : $from;

                $acq = new \UserAcquisition();
                $acq->user_id = $user->user_id;
                $acq->acquirer_id = $retailer->merchant_id;
                $acq->signup_via = $from;
                $acq->social_id = $socialid;
                $acq->save();
                $acq->forceBoxReloadUserData($forceReload);
                // cannot use $user as $user has extra properties added and would fail
                // if we saved it.
                $dup_user = User::find($user->user_id);
                $dup_user->touch();
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = (object)[
                'user_id' => $user->user_id,
                'user_status' => $user->status,
                'user_email' => $user->user_email,
                'apikey_id' => $user->apikey->apikey_id,
                'user_detail_id' => $user->userdetail->user_detail_id,
                'user_acquisition_id' => isset($acq) ? $acq->user_acquisition_id : ''
            ];
            $this->commit();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
        }

        return $this->render();
    }

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

    /**
     * Check the validity of email check query the MX record
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $email
     * @return boolean
     */
    public static function isValidMX($email)
    {
        // Fake the response of MX checking
        if (Config::get('orbit.security.email.fake_mx') === TRUE) {
            return TRUE;
        }

        $hosts = MXEmailChecker::create($email)->check()->getMXRecords();

        if (empty($hosts)) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Validate the registration data.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string $email Consumer's email
     * @return array|string
     * @throws Exception
     */
    protected function validateRegistrationData($email, $password, $password_confirmation)
    {
        // Only do the validation if there is 'mode=registration' on post body.
        $mode = OrbitInput::post('mode');

        if ($mode !== 'registration') {
            return '';
        }

        $firstname = OrbitInput::post('first_name');
        $lastname = OrbitInput::post('last_name');
        $gender = OrbitInput::post('gender');
        $birthdate = OrbitInput::post('birth_date');

        $input = array(
            'first_name' => $firstname,
            'last_name'  => $lastname,
            'gender'     => $gender,
            'birth_date' => $birthdate,
        );

        $validator = Validator::make(
            array(
                'first_name' => $firstname,
                'last_name'  => $lastname,
                'gender'     => $gender,
                'birth_date' => $birthdate,
                'password_confirmation' => $password_confirmation,
                'password' => $password,
            ),
            array(
                'first_name' => 'required',
                'last_name'  => 'required',
                'gender'     => 'required|in:m,f',
                'birth_date' => 'required|date_format:d-m-Y',
                'password_confirmation' => 'required|min:5',
                'password'  => 'min:5|confirmed',
            ),
            array(
                'birth_date.date_format' => Lang::get('validation.orbit.formaterror.date.dmy_date')
            )
        );

        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        // Transform the d-m-y to Y-m-d for storing
        list($d, $m, $y) = explode('-', $birthdate);

        $payloadData = [
            'fname'         => $firstname,
            'lname'         => $lastname,
            'gender'        => $gender,
            'birthdate'     => sprintf('%s-%s-%s', $y, $m, $d),
            'login_from'    => 'form',
            'email'         => $email
        ];

        $key = md5(static::PAYLOAD_KEY);
        $data = http_build_query($payloadData);

        return (new Encrypter($key))->encrypt($data);
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

    protected function viewItemUserUpdate($type, $user, $retailer){
        $viewedItems = [];
        $now = Carbon::now($retailer->timezone->timezone_name);
        $mallid = $retailer->merchant_id;

        if ($type == 'tenant') {
            $newTenantsCount = Tenant::active()
                ->where('parent_id', $retailer->merchant_id)
                ->lists('merchant_id');

            foreach ($newTenantsCount as $counter) {
                $viewedItems[] = array(
                        'viewed_item_user_id' => (string)ObjectID::make(),
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
                        'viewed_item_user_id' => (string)ObjectID::make(),
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
            $newPromotionsCount = \News::select('news.news_id')
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
                        'viewed_item_user_id' => (string)ObjectID::make(),
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
                        'viewed_item_user_id' => (string)ObjectID::make(),
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

            $prefix = DB::getTablePrefix();
            $merchant_id = $retailer->merchant_id;
            $user_id = $user->user_id;
            $quote = function ($arg) {
                return DB::connection()->getPdo()->quote($arg);
            };
            $newCoupons->join('promotion_rules', function($join) {
                    $join->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')
                        ->where('promotions.is_coupon', '=', 'Y');
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
                ->groupBy('promotions.promotion_id');

            $couponData = $newCoupons->get();

            foreach ($couponData as $counter) {
                $viewedItems[] = array(
                        'viewed_item_user_id' => (string)ObjectID::make(),
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
                        'viewed_item_user_id' => (string)ObjectID::make(),
                        'item_id' => $counter,
                        'user_id' => $user->user_id,
                        'mall_id' => $retailer->merchant_id,
                        'item_type' => 'lucky_draw',
                        'created_at' => Carbon::now($retailer->timezone->timezone_name),
                        'updated_at' => NULL
                    );
            }
        }

        if (! empty($viewedItems)) {
            \ViewItemUser::insert($viewedItems);
        }
    }

    /**
     * The purpose of this function is to by pass the new sign in process that use password
     * e.g: User came from Facebook / Google sign in
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @param string $email User email
     * @return User $user (IF user exist; FALSE: user not exist)
     */
    public function doAutoLogin($email)
    {
        $user = User::excludeDeleted()
            ->with('role')
            ->where('user_email', $email)
            ->whereHas('role', function($q) {
                $q->where('role_name', 'Consumer');
            })
            ->first();

        if (is_object($user)) {
            // link previous guest user to the real user
            $this->linkGuestToUser($user);

            $this->session->start(array(), 'no-session-creation');
            // get the session data
            $sessionData = $this->session->read(NULL);
            $sessionData['logged_in'] = TRUE;
            $sessionData['user_id'] = $user->user_id;
            $sessionData['email'] = $user->user_email;
            $sessionData['role'] = $user->role->role_name;
            $sessionData['fullname'] = $user->getFullName();
            $sessionData['visited_location'] = [];
            $sessionData['coupon_location'] = [];
            $sessionData['status'] = $user->status;

            $this->beginTransaction();
            // update the guest session data, append user data to it so the user will be recognized
            $this->session->update($sessionData);
            $this->commit();
            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();

            return $user;
        }

        return FALSE;
    }

    /**
     * Link the guest user before sign up/sign in to the user after
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @param User $user (User object from registration/sign in process)
     * @return \OrbitShop\API\v1\ResponseProvider
     */
    public function linkGuestToUser($user, $transaction = TRUE)
    {
        try {
            if (! is_object($this->session)) {
                // Return mall_portal, cs_portal, pmp_portal etc
                $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                               ->getAppName();

                // Session Config
                $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);
                $applicationId = Config::get('orbit.session.app_id.' . $appOrigin);

                // Instantiate the OrbitSession object
                $config = new SessionConfig(Config::get('orbit.session'));
                $config->setConfig('session_origin', $orbitSessionConfig);
                $config->setConfig('expire', $orbitSessionConfig['expire']);
                $config->setConfig('application_id', $applicationId);

                $this->session = new Session($config);
                $this->session->start(array(), 'no-session-creation');
            }

            $guest_id = $this->session->read('guest_user_id');

            // check guest user id on session if empty create new one
            if (empty($guest_id)) {
                $guestConfig = [
                    'session' => $this->session
                ];
                $guest = GuestUserGenerator::create($guestConfig)->generate();
                $guest_id = $guest->user_id;
                $sessionData = $this->session->read(NULL);
                $sessionData['logged_in'] = TRUE;
                $sessionData['guest_user_id'] = $guest->user_id;
                $sessionData['guest_email'] = $guest->user_email;
                $sessionData['role'] = $user->role->role_name;
                $sessionData['fullname'] = '';
                $sessionData['status'] = $user->status;

                $this->session->update($sessionData);
            }

            if ($transaction) {
                $this->beginTransaction();
            }

            $userguest = new UserGuest();
            $userguest->user_id = $user->user_id;
            $userguest->guest_id = $guest_id;
            $userguest->status = 'active';
            $userguest->save();

            $userSignin = DB::table('user_signin')->where('user_id', '=', $guest_id)->delete();

            $listConnectedUser = DB::table('list_connected_user')->where('user_id', '=', $guest_id)->delete();

            if ($transaction) {
                $this->commit();
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $user;
            $this->response->message = 'Success';
        } catch (Exception $e) {
            if ($transaction) {
                $this->rollback();
            }
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }

    protected function acquireUser($retailer, $user, $signUpVia = null)
    {
        if (is_null($signUpVia)) {
            $signUpVia = 'form';
            if (isset($_COOKIE['login_from'])) {
                switch (strtolower($_COOKIE['login_from'])) {
                    case 'google':
                        $signUpVia = 'google';
                        break;
                    case 'facebook':
                        $signUpVia = 'facebook';
                        break;
                    default:
                        $signUpVia = 'form';
                        break;
                }
            }

            $signUpVia = $user->isGuest() ? 'guest' : $signUpVia;
        }

        if ($user->isConsumer()) {
            $firstAcquired = $retailer->acquireUser($user, $signUpVia);

            // if the user is viewing the mall for the 1st time then set the signup activity
            if ($firstAcquired) {
                SignInRecorder::setSignUpActivity($user, $signUpVia, $retailer);
            }
        }

        $session = $this->session;
        $visited_locations = [];
        if (! empty($session->read('visited_location'))) {
            $visited_locations = $session->read('visited_location');
        }
        if (! in_array($retailer->merchant_id, $visited_locations)) {
            SignInRecorder::setSignInActivity($user, $signUpVia, $retailer, NULL, TRUE);
            $session->write('visited_location', array_merge($visited_locations, [$retailer->merchant_id]));
        }
    }

    /**
     * POST - Login customer in shop v2 : whithout cloud-box sync
     *
     * @param string    `email`          (required) - Email address of the user
     * @param string    `mode`           (required) - registration or login
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postLoginInShopV2()
    {
        $this->response = new \OrbitShop\API\v1\ResponseProvider();
        try {
            $retailer = $this->getRetailerInfo();
            $email = trim(OrbitInput::post('email'));
            $password = OrbitInput::post('password');
            $password_confirmation = OrbitInput::post('password_confirmation');
            $mode = OrbitInput::post('mode');
            $socmed_redirect_to = OrbitInput::post('socmed_redirect_to');
            $to_url = OrbitInput::post('to_url', URL::route('ci-customer-home'));

            DB::beginTransaction();
            if ($mode === 'registration') {
                // do the registration
                $_POST['activity_name_long'] = 'Sign Up via Mobile (Email Address)';
                $_POST['use_transaction'] = FALSE;
                $registration = \Orbit\Controller\API\v1\Pub\RegistrationAPIController::create('raw');
                $response = $registration
                    ->setAppOrigin('mobile_ci')
                    ->setMallId($retailer->merchant_id)
                    ->postRegisterCustomer();
                $response_data = json_decode($response->getOriginalContent());

                unset($_POST['activity_name_long']);
                if($response_data->code !== 0) {
                    $errorMessage = $response_data->message;
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                // set activation notification
                $inbox = new Inbox();
                $inbox->addToInbox($response_data->data->user_id, $response_data->data, $retailer->merchant_id, 'activation');
            }

            // do the login
            $_POST['activity_name_long'] = 'Sign In via Mobile (Email Address)';
            $_POST['activity_origin'] = 'mobileci';
            $login_response = \Orbit\Controller\API\v1\Pub\LoginAPIController::create('raw')
                ->setAppOrigin('mobile_ci')
                ->postLoginCustomer();
            $login_response_data = json_decode($login_response->getOriginalContent());
            unset($_POST['activity_name_long']);
            unset($_POST['activity_origin']);
            if($login_response_data->code !== 0) {
                $errorMessage = $login_response_data->message;
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            $user = $login_response_data->data;
            $this->linkGuestToUser($user);

            // acquire user
            $user_obj = User::where('user_id', $user->user_id)->first();
            $this->acquireUser($retailer, $user_obj, 'form');

            // append the redirect url to user object
            // remove the orbit_session from query string for this redirect url
            $user->redirect_to = $this->remove_querystring_var($to_url, $this->getOrbitSessionQueryStringName());
            // $user->redirect_to = $this->add_querystring_var($user->redirect_to, $this->getOrbitSessionQueryStringName(), $this->session->getSessionId());

            // do the stage 2
            $notAllowedStatus = ['inactive'];
            $lowerCasedStatus = strtolower($user->status);
            if (in_array($lowerCasedStatus, $notAllowedStatus)) {
                OrbitShopAPI::throwInvalidArgument('You are not allowed to login. Please check with Customer Service.');
            }
            // if a valid MAC specified, associate the MAC with the given email if not associated yet
            $mac = OrbitInput::get('mac_address', '');
            if ($mac !== '') {
                $addr_object = new MacAddr($mac);
                if ($addr_object->isValid()) {
                    $addr_entity = \MacAddress::excludeDeleted()->where('user_email', '=', $user->user_email)->where('mac_address', '=', $mac)->first();
                    if ($addr_entity === null) {
                        $addr_entity = new \MacAddress();
                        $addr_entity->user_email = $user->user_email;
                        $addr_entity->mac_address = $mac;
                        $addr_entity->status = 'active';
                        $addr_entity->save();
                    }
                }
            }
            // update last visited records
            $user_detail = UserDetail::where('user_id', $user->user_id)->first();
            $user_detail->last_visit_shop_id = $retailer->merchant_id;
            $user_detail->last_visit_any_shop = Carbon::now($retailer->timezone->timezone_name);
            $user_detail->save();

            // auto coupon issuance checkwill happen on each page after the login success
            //Coupon::issueAutoCoupon($retailer, $user_obj, $this->session);

            DB::commit();

            // remove activity from user object
            unset($user->activity);
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Sign in success';
            $this->response->data = $user;
        } catch (ACLForbiddenException $e) {
            DB::rollback();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            DB::rollback();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            DB::rollback();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = Config::get('app.debug') ? [$e->getMessage(), $e->getFile(), $e->getLine()] : null;
        }

        return $this->render();
    }

    public function remove_querystring_var($url, $key)
    {
        $parsed_url = parse_url((string)$url);
        if(isset($parsed_url['query'])){
            $query = parse_str($parsed_url['query'], $output);
            unset($output[$key]);
            $query_string = http_build_query($output);
            $parsed_url['query'] = $query_string;
            $new_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'] . '?' . $parsed_url['query'];
        } else {
            $new_url = $url;
        }

        return $new_url;
    }

    public function add_querystring_var($url, $key, $value)
    {
        $parsed_url = parse_url((string)$url);
        if(isset($parsed_url['query'])){
            $query = parse_str($parsed_url['query'], $output);
            $output[$key] = $value;
            $query_string = http_build_query($output);
            $new_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'] . '?' . $query_string;
        } else {
            $params = [];
            $params[$key] = $value;
            $query_string = http_build_query($params);
            $new_url = str_replace('?', '', $url);
            $new_url = $new_url . '?' . $query_string;
        }

        return $new_url;
    }

    public function getOrbitSessionQueryStringName()
    {
        return Config::get('orbit.session.session_origin.query_string.name');
    }

    public function setSession($session)
    {
        $this->session = $session;

        return $this;
    }

    public function setCookieFromWifi()
    {
        if (! isset($_COOKIE['from_wifi'])) {
            $domain = Config::get('orbit.captive.from_wifi.domain', NULL);
            $path = Config::get('orbit.captive.from_wifi.path', '/');
            $expire = time() + Config::get('orbit.captive.from_wifi.expire', 60); // default expired if doesnt exist is 60 second (1 minute)

            setcookie(Config::get('orbit.captive.from_wifi.name', 'from_wifi'), 'Y', $expire, $path, $domain, FALSE);
        }
    }
}
