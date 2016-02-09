<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Log;
use Net\MacAddr;
use Orbit\Helper\Email\MXEmailChecker;
use Orbit\Helper\Net\Domain;
use Orbit\CloudMAC;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
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

class MobileCIAPIController extends ControllerAPI
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
            $payload = OrbitInput::post('payload');

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

            $payloadFromReg = $this->validateRegistrationData($email);

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
                $acq = \UserAcquisition::where('user_id', $user->user_id)
                    ->where('acquirer_id', $retailer->merchant_id)
                    ->lockForUpdate()->first();
                if ($acq === null) {
                    $user = null;
                }
            }

            // if not from cloud callback we redirect to cloud if pending so cloud
            // can resend activation email.
            // if from cloud callback we do not redirect to cloud again
            // cloud callback sends apikey_id (and other ids) in GET
            $from_cloud_callback = OrbitInput::get('apikey_id', null) !== null;

            if (! is_object($user) || ($user->status === 'pending' && !$from_cloud_callback) ) {
                if (empty($payload)) {
                    $payload = $payloadFromReg;
                }

                return $this->redirectToCloud($email, $retailer, $payload, '', OrbitInput::post('mac_address', ''));
            } else {
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
            $this->response->data = $e->getFile();

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

        return \Redirect::to('/customer');
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
        $activityPage = Activity::mobileci()
                            ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            if (empty(\Cookie::get('event'))) {
                $event_store = array();
            } else {
                $event_store = \Cookie::get('event');
            }

            $events = EventModel::with(array('retailers' => function ($q) {
                    $q->where('merchants.status', 'active');
                }))
                ->active()->where('merchant_id', $retailer->merchant_id)
                ->where(
                    function ($q) use ($retailer) {
                        $q->where('begin_date', '<=', Carbon::now($retailer->timezone->timezone_name))->where('end_date', '>=', Carbon::now($retailer->timezone->timezone_name));
                    }
                );

            if (! empty($event_store)) {
                foreach ($event_store as $event_idx) {
                    $events->where('event_id', '!=', $event_idx);
                }
            }

            $events = $events->orderBy('events.event_id', 'DESC')->first();
            $event_families = array();
            if (! empty($events)) {
                if ($events->link_object_type == 'family') {
                    if (! empty($events->link_object_id1)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id1)->active()->first();
                    }
                    if (! empty($events->link_object_id2)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id2)->active()->first();
                    }
                    if (! empty($events->link_object_id3)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id3)->active()->first();
                    }
                    if (! empty($events->link_object_id4)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id4)->active()->first();
                    }
                    if (! empty($events->link_object_id5)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id5)->active()->first();
                    }
                }
            }

            $event_family_url_param = '';
            for ($i = 0; $i <= count($event_families) - 1; $i++) {
                $event_family_url_param = $event_family_url_param . 'f' . ($i + 1) . '=' . $event_families[$i]->category_id;
                if ($i < count($event_families) - 1) {
                    $event_family_url_param = $event_family_url_param . '&';
                }
            }

            if (! empty($events)) {
                $event_store[] = $events->event_id;
                \Cookie::queue('event', $event_store, 1440);

                if (! empty($alternateLanguage)) {
                    $eventTranslation = \EventTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
                        ->where('event_id', $events->event_id)->first();

                    if (! empty($eventTranslation)) {
                        foreach (['event_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($eventTranslation->{$field}) && $eventTranslation->{$field} !== '') {
                                $events->{$field} = $eventTranslation->{$field};
                            }
                        }

                        $media = $eventTranslation->find($eventTranslation->event_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $events->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \EventTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
                                    ->where('event_id', $events->event_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->event_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $events->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            $widgets = Widget::with('media')
                ->active()
                ->where('merchant_id', $retailer->merchant_id)
                ->whereHas(
                    'retailers',
                    function ($q) use ($retailer) {
                        $q->where('retailer_id', $retailer->merchant_id);
                    }
                )
                ->orderBy('widget_order', 'ASC')
                ->groupBy('widget_type')
                ->take(5)
                ->get();

            $widget_singles = new stdclass();
            $widget_singles->tenant = NULL;
            $widget_singles->promotion = NULL;
            $widget_singles->news = NULL;
            $widget_singles->coupon = NULL;
            $widget_singles->luckydraw = NULL;

            $new_offset = Config::get('orbit.shop.widget_new_threshold', 1); //in days
            $now = Carbon::now($retailer->timezone->timezone_name);
            $new_date = Carbon::now($retailer->timezone->timezone_name)->subDays($new_offset);

            foreach ($widgets as $widget) {
                if ($widget->widget_type == 'tenant') {
                    // get all tenant count
                    $tenantsCount = Tenant::active()
                        ->where('parent_id', $retailer->merchant_id)
                        ->count();

                    // get all new tenant after new_date
                    $newTenantsCount = Tenant::active()
                        ->where('parent_id', $retailer->merchant_id)
                        ->whereRaw("created_at between ? and ?", [$new_date, $now])
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
                    $widget->url = 'tenants';
                }
                if ($widget->widget_type == 'promotion') {
                    // get all news count filter by age range and gender
                    $promotionsCount = \News::active()
                                ->where('mall_id', $retailer->merchant_id)
                                ->where('object_type', 'promotion')
                                ->whereRaw("? between begin_date and end_date", [$now])
                                ->count();

                    // get all new news after new_date filter by age range and gender
                    $newPromotionsCount = \News::active()
                                ->where('mall_id', $retailer->merchant_id)
                                ->where('object_type', 'promotion')
                                ->whereRaw("? between begin_date and end_date", [$now])
                                ->whereRaw("begin_date between ? and ?", [$new_date, $now])
                                ->count();

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
                    $widget->url = 'mallpromotions';
                }
                if ($widget->widget_type == 'news') {
                    // get all news count filter by age range and gender
                    $newsCount = \News::active()
                                ->where('mall_id', $retailer->merchant_id)
                                ->where('object_type', 'news')
                                ->whereRaw("? between begin_date and end_date", [$now])
                                ->count();

                    // get all new news after new_date filter by age range and gender
                    $newNewsCount = \News::active()
                                ->where('mall_id', $retailer->merchant_id)
                                ->where('object_type', 'news')
                                ->whereRaw("? between begin_date and end_date", [$now])
                                ->whereRaw("begin_date between ? and ?", [$new_date, $now])
                                ->count();

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
                    $widget->url = 'mallnews';
                }
                if ($widget->widget_type == 'coupon') {
                    $coupons = DB::select(
                        DB::raw(
                            'SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                        inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.is_coupon = "Y" AND p.status = "active"
                        inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                        WHERE ic.expired_date >= "' . Carbon::now($retailer->timezone->timezone_name). '"
                            AND p.merchant_id = :merchantid
                            AND ic.user_id = :userid
                            ORDER BY RAND()' // randomize
                        ),
                        array('merchantid' => $retailer->merchant_id, 'userid' => $user->user_id)
                    );
                    $couponsCount = count($coupons);

                    $newCoupons = DB::select(
                        DB::raw(
                            'SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                        inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.is_coupon = "Y" AND p.status = "active"
                        inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                        WHERE ic.expired_date >= "' . Carbon::now($retailer->timezone->timezone_name). '"
                            AND p.merchant_id = :merchantid
                            AND ic.user_id = :userid
                            AND ic.issued_date between :new_date and :now
                            ORDER BY RAND()' // randomize
                        ),
                        array(
                            'merchantid' => $retailer->merchant_id,
                            'userid' => $user->user_id,
                            'new_date' => $new_date,
                            'now' => $now
                        )
                    );
                    $newCouponsCount = count($newCoupons);

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
                    $widget->url = 'mallcoupons';
                }
                if ($widget->widget_type == 'lucky_draw') {
                    $luckydrawsCount = LuckyDraw::active()
                        ->where('mall_id', $retailer->merchant_id)
                        ->whereRaw("? between start_date and grace_period_date", [$now])
                        ->count();

                    $newLuckydrawsCount = LuckyDraw::active()
                        ->where('mall_id', $retailer->merchant_id)
                        ->whereRaw("? between start_date and grace_period_date", [$now])
                        ->whereRaw("start_date between ? and ?", [$new_date, $now])
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
                    $widget->url = 'luckydraws';
                }
            }

            $widget_flags = new stdclass();
            $widget_flags->enable_coupon = $this->getObjFromArray($retailer->settings, 'enable_coupon');
            $widget_flags->enable_coupon_widget = $this->getObjFromArray($retailer->settings, 'enable_coupon_widget');
            $widget_flags->enable_lucky_draw = $this->getObjFromArray($retailer->settings, 'enable_lucky_draw');
            $widget_flags->enable_lucky_draw_widget = $this->getObjFromArray($retailer->settings, 'enable_lucky_draw_widget');

            $languages = $this->getListLanguages($retailer);

            $activityPageNotes = sprintf('Page viewed: %s', 'Home');
            $activityPage->setUser($user)
                ->setActivityName('view_page_home')
                ->setActivityNameLong('View (Home Page)')
                ->setObject(null)
                ->setNotes($activityPageNotes)
                ->setModuleName('Widget')
                ->responseOK()
                ->save();

            $data = array(
                'page_title' => Lang::get('mobileci.page_title.home'),
                'retailer' => $retailer,
                'events' => $events,
                'event_families' => $event_families,
                'event_family_url_param' => $event_family_url_param,
                'widgets' => $widgets,
                'widget_flags' => $widget_flags,
                'widget_singles' => $widget_singles,
                'languages' => $languages,
                'active_user' => ($user->status === 'active'),
                'user_email' => $user->user_email,
                'user' => $user
            );

            return View::make('mobile-ci.home', $data)->withCookie($event_store);
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Home');
            $activityPage->setUser($user)
                ->setActivityName('view_page_home')
                ->setActivityNameLong('View (Home Page) Failed')
                ->setObject(null)
                ->setModuleName('Widget')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

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
        }

        $landing_url = URL::route('ci-customer-home');
        $cookie_fname = isset($_COOKIE['orbit_firstname']) ? $_COOKIE['orbit_firstname'] : (isset($mac_model) ? $mac_model->user->user_firstname : '');
        $cookie_email = isset($_COOKIE['orbit_email']) ? $_COOKIE['orbit_email'] : (isset($mac_model) ? $mac_model->user->user_email : '');
        $cookie_lang = isset($_COOKIE['orbit_preferred_language']) ? $_COOKIE['orbit_preferred_language'] : '';
        $cookie_login_from = isset($_COOKIE['login_from']) ? $_COOKIE['login_from'] : '';
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
        $permissions = ['email'];
        $facebookCallbackUrl = URL::route('mobile-ci.social_login_callback', ['orbit_origin' => 'facebook', 'from_captive' => OrbitInput::post('from_captive'), 'mac_address' => \Input::get('mac_address', '')]);

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
        return Redirect::route('mobile-ci.signin', ['error' => $errorMessage, 'isInProgress' => 'true']);
    }

    public function getGoogleCallbackView()
    {
        $oldRouteSessionConfigValue = Config::get('orbit.session.availability.query_string');
        Config::set('orbit.session.availability.query_string', false);

        $recognized = \Input::get('recognized', 'none');
        $code = \Input::get('code', NULL);


        $googleService = OAuth::consumer( 'Google' );

        if ( !empty( $code ) ) {
            try {

                Config::set('orbit.session.availability.query_string', $oldRouteSessionConfigValue);
                $token = $googleService->requestAccessToken( $code );

                $user = json_decode( $googleService->request( 'https://www.googleapis.com/oauth2/v1/userinfo' ), true );

                $userEmail = isset($user['email']) ? $user['email'] : '';
                $firstName = isset($user['given_name']) ? $user['given_name'] : '';
                $lastName = isset($user['family_name']) ? $user['family_name'] : '';
                $gender = isset($user['gender']) ? $user['gender'] : '';
                $socialid = isset($user['id']) ? $user['id'] : '';

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
                $this->prepareSession();

                // There is a chance that user not 'grant' his email while approving our app
                // so we double check it here
                if (empty($userEmail)) {
                    return Redirect::route('mobile-ci.signin', ['error' => 'Email is required.']);
                }

                $key = $this->getPayloadEncryptionKey();
                $payload = (new Encrypter($key))->encrypt(http_build_query($data));
                $query = ['payload' => $payload, 'email' => $userEmail, 'auto_login' => 'yes', 'isInProgress' => 'true'];
                if (\Input::get('from_captive') === 'yes') {
                    $query['from_captive'] = 'yes';
                }

                $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

                setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                setcookie('login_from', 'Google', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

                // todo can we not do this directly
                return Redirect::route('mobile-ci.signin', $query);

            } catch (Exception $e) {
                $errorMessage = 'Error: ' . $e->getMessage();
                return Redirect::route('mobile-ci.signin', ['error' => $errorMessage]);
            }

        } else {
            try {
                // get googleService authorization
                $url = $googleService->getAuthorizationUri();
                return Redirect::to( (string)$url );
            } catch (Exception $e) {
                $errorMessage = 'Error: ' . $e->getMessage();
                return Redirect::route('mobile-ci.signin', ['error' => $errorMessage, 'isInProgress' => 'true']);
            }
        }
    }

    public function getSocialLoginCallbackView()
    {
        $recognized = \Input::get('recognized', 'none');
        // error=access_denied&
        // error_code=200&
        // error_description=Permissions+error
        // &error_reason=user_denied
        // &state=28d0463ac4dc53131ae19826476bff74#_=_
        $error = \Input::get('error', NULL);

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

        $response = $fb->get('/me?fields=email,name,first_name,last_name,gender', $accessToken->getValue());
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


        // There is a chance that user not 'grant' his email while approving our app
        // so we double check it here
        if (empty($userEmail)) {
            return Redirect::route('mobile-ci.signin', ['error' => 'Email is required.', 'isInProgress' => 'true']);
        }

        $key = $this->getPayloadEncryptionKey();
        $payload = (new Encrypter($key))->encrypt(http_build_query($data));
        $query = ['payload' => $payload, 'email' => $userEmail, 'auto_login' => 'yes', 'isInProgress' => 'true'];
        if (\Input::get('from_captive') === 'yes') {
            $query['from_captive'] = 'yes';
        }

        $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

        setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('login_from', 'Facebook', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

        // todo can we not do this directly
        return Redirect::route('mobile-ci.signin', $query);
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
     * GET - Get current active mall
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Mall
     */
    public function getRetailerInfo()
    {
        try {
            $retailer_id = App::make('orbitSetting')->getSetting('current_retailer');
            $retailer = Mall::with('parent')->where('merchant_id', $retailer_id)->first();
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

            $activityNotes = sprintf('Widget Click. Widget Id : %s', $widget_id);
            $activity->setUser($user)
                ->setActivityName('widget_click')
                ->setActivityNameLong('Widget Click ' . ucwords(str_replace('_', ' ', $widget->widget_type)))
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

                $coupon = Coupon::with(
                    array('issueretailers' => function ($q) use ($retailer) {
                        $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                    })
                )->active()
                ->where('promotion_id', $value)
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
     * Redirect user if not logged in to sign page
     *
     * @param object $e - Error object
     *
     * @return Illuminate\Support\Facades\Redirect
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function redirectIfNotLoggedIn($e)
    {
        if (Config::get('app.debug')) {
            return $e;
        }

        switch ($e->getCode()) {
            case Session::ERR_UNKNOWN;
            case Session::ERR_IP_MISS_MATCH;
            case Session::ERR_UA_MISS_MATCH;
            case Session::ERR_SESS_NOT_FOUND;
            case Session::ERR_SESS_EXPIRE;
                return \Redirect::to('/customer/logout');
                break;

            default:
                return \Redirect::to('/customer');
        }
    }

    /**
     * Get current logged in user used in view related page.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return User $user
     */
    protected function getLoggedInUser()
    {
        $this->prepareSession();

        $userId = $this->session->read('user_id');

        if ($this->session->read('logged_in') !== true || ! $userId) {
            throw new Exception('Invalid session data.');
        }

        $retailer = $this->getRetailerInfo();

        $user = User::with(['userDetail',
            'membershipNumbers' => function($q) use ($retailer) {
                $q->select('membership_numbers.*')
                    ->with('membership.media')
                    ->join('memberships', 'memberships.membership_id', '=', 'membership_numbers.membership_id')
                    ->excludeDeleted('membership_numbers')
                    ->excludeDeleted('memberships')
                    ->where('memberships.merchant_id', $retailer->merchant_id);
            }])->where('user_id', $userId)->first();

        if (! $user) {
            throw new Exception('Session error: user not found.');
        } else {
            $_user = clone($user);
            if (count($_user->membershipNumbers)) {
               $user->membership_number = $_user->membershipNumbers[0]->membership_number;
            }
        }

        return $user;
    }

    /**
     * Prepare session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    protected function prepareSession()
    {
        if (! is_object($this->session)) {
            // This user assumed are Consumer, which has been checked at login process
            $config = new SessionConfig(Config::get('orbit.session'));
            $config->setConfig('session_origin.header.name', 'X-Orbit-Session');
            $config->setConfig('session_origin.query_string.name', 'orbit_session');
            $config->setConfig('session_origin.cookie.name', 'orbit_sessionx');
            $config->setConfig('application_id', MobileCIAPIController::APPLICATION_ID);

            try {
                $this->session = new Session($config);
                $this->session->start();
            } catch (Exception $e) {
                Redirect::to('/customer/logout');
            }
        }
    }

    /**
     * @param $mall
     * @return string
     * @throws Exception
     */
    public function getLandingUrl($mall)
    {
        $landing = Setting::getFromList($mall->settings, 'landing_page');

        // Get the landing page URL based on settings
        $landing_url = '';

        switch ($landing[0]) {
            case 'tenant':
                $landing_url = URL::route('ci-tenants');
                break;

            case 'promotion':
                $landing_url = URL::route('ci-mall-promotions');
                break;

            case 'news':
                $landing_url = URL::route('ci-mall-news');
                break;

            case 'widget':
            default:
                $landing_url = URL::route('ci-customer-home');
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
     */
    public function getTenantsView()
    {
        $user = null;
        $keyword = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

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
            }

            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $categories = Category::active('categories')
                ->where('category_level', 1)
                ->where('merchant_id', $retailer->merchant_id);

            $categories->select('categories.*');
            $this->maybeJoinWithCategoryTranslationsTable($categories, $alternateLanguage);

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

            $tenants = Tenant::with('mediaLogo');
            if (!empty($alternateLanguage)) {
                $tenants = $tenants->with(['categories' => function ($q) use ($alternateLanguage) {
                    $prefix = DB::getTablePrefix();
                    $q->leftJoin('category_translations', function ($join) use ($alternateLanguage) {
                        $join->on('categories.category_id', '=', 'category_translations.category_id');
                        $join->where('category_translations.merchant_language_id', '=', $alternateLanguage->merchant_language_id);
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

            $tenants->select('merchants.*');

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

            if ($notfound) {
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer));
            }

            OrbitInput::get(
                'fid',
                function ($fid) use ($tenants) {
                    if (! empty($fid)) {
                        $tenants->where('merchants.floor', $fid);
                    }
                }
            );

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

            $cartitems = $this->getCartForToolbar();

            $totalRec = $_tenants->count();
            $listOfRec = $tenants->get();
            foreach ($listOfRec as $tenant) {
                if (empty($tenant->logo)) {
                    $tenant->logo = 'mobile-ci/images/default_product.png';
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
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if (! empty(OrbitInput::get('promotion_id'))) {
                $pagetitle = Lang::get('mobileci.page_title.promotions_tenants');

                $activityPageNotes = sprintf('Page viewed: Promotion Tenants List Page, promotion ID: %s', OrbitInput::get('promotion_id'));
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Promotion Tenant List')
                    ->setObject(null)
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
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if (empty(OrbitInput::get('event_id')) && empty(OrbitInput::get('promotion_id')) && empty(OrbitInput::get('news_id'))) {
                $activityPageNotes = sprintf('Page viewed: Tenant Listing Page');
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Tenant List')
                    ->setObject(null)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            $languages = $this->getListLanguages($retailer);

            return View::make('mobile-ci.catalogue-tenant', array(
                'page_title'=>$pagetitle,
                'user' => $user,
                'retailer' => $retailer,
                'data' => $data,
                'cartitems' => $cartitems,
                'categories' => $categories,
                'active_user' => ($user->status === 'active'),
                'floorList' => $floorList,
                'user_email' => $user->user_email,
                'languages' => $languages));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: Tenant Listing Page');
            $activityPage->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant')
                ->setObject(null)
                ->setModuleName('Tenant')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
                // return $e;
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
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        $tenant = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            $product_id = trim(OrbitInput::get('id'));
            $promo_id = trim(OrbitInput::get('pid'));
            $news_id = trim(OrbitInput::get('nid'));

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $tenant = Tenant::with( // translated
                array(
                    'media',
                    'mediaLogoOrig',
                    'mediaMapOrig',
                    'mediaImageOrig',
                    'news' => function($q) {
                        $q->whereRaw("NOW() between begin_date and end_date");
                    },
                    'newsPromotions' => function($q) {
                        $q->whereRaw("NOW() between begin_date and end_date");
                    }
                ))
                ->active('merchants')
                ->where('parent_id', $retailer->merchant_id)
                ->where('merchants.merchant_id', $product_id);
            $tenant->select('merchants.*');
            // $this->maybeJoinWithTranslationsTable($tenant, $alternateLanguage);
            $tenant = $tenant->first();

            // News per tenant
            if (!empty($alternateLanguage) && !empty($tenant->news)) {
                foreach ($tenant->news as $keyNews => $news) {

                    $newsTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
                        ->where('news_id', $news->news_id)->first();

                    if (!empty($newsTranslation)) {
                        foreach (['news_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($newsTranslation->{$field}) && $newsTranslation->{$field} !== '') {
                                $tenant->news[$keyNews]->{$field} = $newsTranslation->{$field};
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
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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
            if (!empty($alternateLanguage) && !empty($tenant->newsPromotions)) {
                foreach ($tenant->newsPromotions as $keyNewsPromotions => $newsPromotions) {

                    $newsPromotionsTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
                        ->where('news_id', $newsPromotions->news_id)->first();

                    if (!empty($newsPromotionsTranslation)) {
                        foreach (['news_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($newsPromotionsTranslation->{$field}) && $newsPromotionsTranslation->{$field} !== '') {
                                $tenant->newsPromotions[$keyNewsPromotions]->{$field} = $newsPromotionsTranslation->{$field};
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
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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

            if (empty($tenant)) {
                // throw new Exception('Product id ' . $product_id . ' not found');
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer));
            }

            if (empty($tenant->logo)) {
                $tenant->logo = 'mobile-ci/images/default_product.png';
            }

            $languages = $this->getListLanguages($retailer);

            // cek if any language active
            if (!empty($alternateLanguage) && !empty($tenant)) {
                    $merchant_translation = \MerchantTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                'box_url' => $box_url));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: Tenant Detail Page, tenant ID: ' . $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_retailer')
                ->setActivityNameLong('View Tenant')
                ->setObject(null)
                ->setModuleName('Tenant')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

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
        $user = $this->getLoggedInUser();

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
                    $join->where('category_translations.merchant_language_id', '=', $alternateLanguage->merchant_language_id);
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

        $tenants->select('merchants.*');

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

        $cartitems = $this->getCartForToolbar();

        $totalRec = $_tenants->count();
        $listOfRec = $tenants->get();
        foreach ($listOfRec as $tenant) {
            if (empty($tenant->logo)) {
                $tenant->logo = 'mobile-ci/images/default_product.png';
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
            $tenant->url = URL::to('customer/tenant?id='.$tenant->merchant_id);
            if (count($tenant->mediaLogo) > 0) {
                foreach ($tenant->mediaLogo as $media) {
                    if ($media->media_name_long == 'retailer_logo_orig') {
                        $tenant->logo_orig = URL::asset($media->path);
                    }
                }
            } else {
                $tenant->logo_orig = URL::asset('mobile-ci/images/default_product.png');
            }
            $tenant->name = mb_strlen($tenant->name) > 64 ? mb_substr($tenant->name, 0, 64) . '...' : $tenant->name;
        }

        $data = new stdclass();
        $data->status = 1;
        $data->total_records = $totalRec;
        $data->returned_records = count($listOfRec);
        $data->records = $listOfRec;

        return Response::json($data);
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
        $activityPage = Activity::mobileci()
            ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $languages = $this->getListLanguages($retailer);
            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $luckydraws = LuckyDraw::with('translations')
                ->active()
                ->where('mall_id', $retailer->merchant_id)
                ->whereRaw("? between start_date and grace_period_date", [$mallTime]);

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

            $luckydraws->orderBy('start_date', 'desc');

            $totalRec = $_luckydraws->count();
            $listOfRec = $luckydraws->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $luckyDrawTranslation = \LuckyDrawTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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

            $activityPageNotes = sprintf('Page viewed: %s', 'News List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_lucky_draw_list')
                ->setActivityNameLong('View Lucky Draw List')
                ->setObject(null)
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
                'user_email' => $user->user_email,
                'user' => $user
            );

            return View::make('mobile-ci.luckydraws', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: Lucky Draw List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_lucky_draw_list')
                ->setActivityNameLong('View Lucky Draw List')
                ->setObject(null)
                ->setModuleName('LuckyDraw')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

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
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $luckydraws = LuckyDraw::with('translations')
                ->active()
                ->where('mall_id', $retailer->merchant_id)
                ->whereRaw("? between start_date and grace_period_date", [$mallTime]);

            $_luckydraws = clone $luckydraws;

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

            $luckydraws->orderBy('start_date', 'desc');

            $totalRec = $_luckydraws->count();
            $listOfRec = $luckydraws->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $luckyDrawTranslation = \LuckyDrawTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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
                $item->url = URL::to('customer/luckydraw?id='.$item->lucky_draw_id);
                $item->name = mb_strlen($item->lucky_draw_name) > 64 ? mb_substr($item->lucky_draw_name, 0, 64) . '...' : $item->lucky_draw_name;
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            return Response::json($data);

        } catch (Exception $e) {
            $data = new stdclass();
            $data->status = 0;
            $data->message = $e->getMessage();
            $data->total_records = 0;
            $data->returned_records = 0;
            $data->records = null;

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
        $activityProduct = Activity::mobileci()
                                   ->setActivityType('view');
        $product = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $lucky_draw_id = OrbitInput::get('id');

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $luckydraw = LuckyDraw::with('translations', 'prizes')->excludeDeleted()->where('mall_id', $retailer->merchant_id)->where('lucky_draw_id', $lucky_draw_id)->first();

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
                ]);
            }

            if (!empty($alternateLanguage) && !empty($luckydraw)) {
                $luckyDrawTranslation = \LuckyDrawTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                                ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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
                // $prevUrl = URL::route('ci-luckydraw') . '?id='. $luckydraw->lucky_draw_id . '&page=' . ($currentPage - 1);
                // $nextUrl = URL::route('ci-luckydraw') . '?id='. $luckydraw->lucky_draw_id . '&page=' . ($currentPage + 1);

                if ($currentPage >= $totalPages) {
                    $nextUrl = '#';
                }

                if ($currentPage === 1) {
                    $prevUrl = '#';
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

            $activityProductNotes = sprintf('Page viewed: Lucky Draw Page');
            $activityProduct->setUser($user)
                ->setActivityName('view_lucky_draw')
                ->setActivityNameLong('View Lucky Draw Detail')
                ->setObject($luckydraw, TRUE)
                ->setModuleName('LuckyDraw')
                ->setNotes($activityProductNotes)
                ->responseOK()
                ->save();

            $numbers = empty($apiResponse->data->records) ? array() : $apiResponse->data->records;

            $servertime = Carbon::now($retailer->timezone->timezone_name);

            return View::make('mobile-ci.luckydraw', [
                                'page_title'    => $luckydraw->lucky_draw_name,
                                'user'          => $user,
                                'retailer'      => $retailer,
                                'luckydraw'     => $luckydraw,
                                'numbers'       => $numbers,
                                'total_number'  => $apiResponse->data->total_records,
                                'prev_url'      => $prevUrl,
                                'next_url'      => $nextUrl,
                                'total_pages'   => $totalPages,
                                'current_page'  => $currentPage,
                                'per_page'      => $take,
                                'servertime'    => $servertime,
                                'languages'     => $languages,
                                'paginationPage'=> $paginationPage
            ]);
        } catch (Exception $e) {
            $activityProductNotes = sprintf('Failed to view: Lucky Draw Page');
            $activityProduct->setUser($user)
                ->setActivityName('view_lucky_draw')
                ->setActivityNameLong('View Lucky Draw Detail')
                ->setObject(null)
                ->setModuleName('Lucky Draw')
                ->setNotes($activityProductNotes)
                ->responseOK()
                ->save();

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
        $activityProduct = Activity::mobileci()
                                   ->setActivityType('view');

        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

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
                                'luckydraw'     => null
                ]);
            }

            if (!empty($alternateLanguage) && !empty($luckydraw)) {
                $luckyDrawTranslation = \LuckyDrawTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                                ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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
                    ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                                ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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

            $activityProductNotes = sprintf('Page viewed: Lucky Draw Winning Numbers & Prizes');
            $activityProduct->setUser($user)
                ->setActivityName('view_lucky_draw_announcement')
                ->setActivityNameLong('View Winning Numbers & Prizes')
                ->setObject($luckydraw, TRUE)
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
                                'ongoing'       => $ongoing
            ]);
        } catch (Exception $e) {
            $activityProductNotes = sprintf('Failed to view: Lucky Draw Winning Numbers & Prizes');
            $activityProduct->setUser($user)
                ->setActivityName('view_lucky_draw')
                ->setActivityNameLong('View Winning Numbers & Prizes')
                ->setObject(null)
                ->setModuleName('Lucky Draw')
                ->setNotes($activityProductNotes)
                ->responseOK()
                ->save();

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
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

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
     * GET - Get coupon list in mall
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getMallCouponList() {
        $user = null;
        $keyword = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

            $pagetitle = Lang::get('mobileci.page_title.coupons');

            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            // $categories = Category::active()->where('category_level', 1)->where('merchant_id', $retailer->merchant_id)->get();

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $coupons = Coupon::selectRaw('*, ' . DB::getTablePrefix() . 'promotions.image AS promo_image')
                ->join('promotion_rules', function ($join) {
                    $join->on('promotion_rules.promotion_id', '=', 'promotions.promotion_id');
                    $join->where('promotions.status', '=', 'active');
                })
                ->join('issued_coupons', function ($join) {
                    $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                    $join->where('issued_coupons.status', '=', 'active');
                })
                ->where('issued_coupons.expired_date', '>=', Carbon::now($retailer->timezone->timezone_name))
                ->where('promotions.merchant_id', $retailer->merchant_id)
                ->where('issued_coupons.user_id', $user->user_id);

            $_coupons = clone $coupons;

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

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $coupons) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $coupons->skip($skip);

            $coupons->orderBy('issued_date', 'desc');

            $totalRec = $_coupons->count();
            $listOfRec = $coupons->get();

            if (! empty($alternateLanguage) || ! empty($listOfRec)) {
                foreach ($listOfRec as $coupon) {
                    $couponTranslation = \CouponTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
                        ->where('promotion_id', $coupon->promotion_id)->first();

                    if (! empty($couponTranslation)) {
                        foreach (['promotion_name', 'description', 'long_description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($couponTranslation->{$field}) && $couponTranslation->{$field} !== '') {
                                $coupon->{$field} = $couponTranslation->{$field};
                            }
                        }

                        $media = $couponTranslation->find($couponTranslation->coupon_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $coupon->promo_image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \CouponTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
                                    ->where('promotion_id', $coupon->promotion_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->coupon_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $coupon->promo_image = $mediaDefaultLanguage->path;
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

            $activityPageNotes = sprintf('Page viewed: %s', 'Coupon List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_coupon_list')
                ->setActivityNameLong('View Coupon List')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            $view_data = array(
                'page_title' => $pagetitle,
                'retailer' => $retailer,
                'data' => $data,
                'active_user' => ($user->status === 'active'),
                'languages' => $languages,
                'user' => $user
            );
            return View::make('mobile-ci.mall-coupon-list', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Coupon List');
            $activityPage->setUser($user)
                ->setActivityName('view_coupon_list')
                ->setActivityNameLong('View Coupon List Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

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
        $user = null;
        $keyword = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $coupons = Coupon::selectRaw('*, ' . DB::getTablePrefix() . 'promotions.image AS promo_image')
                ->join('promotion_rules', function ($join) {
                    $join->on('promotion_rules.promotion_id', '=', 'promotions.promotion_id');
                    $join->where('promotions.status', '=', 'active');
                })
                ->join('issued_coupons', function ($join) {
                    $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                    $join->where('issued_coupons.status', '=', 'active');
                })
                ->where('issued_coupons.expired_date', '>=', Carbon::now($retailer->timezone->timezone_name))
                ->where('promotions.merchant_id', $retailer->merchant_id)
                ->where('issued_coupons.user_id', $user->user_id);

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

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $coupons) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $coupons->skip($skip);

            $coupons->orderBy('issued_date', 'desc');

            $totalRec = $_coupons->count();
            $listOfRec = $coupons->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $couponTranslation = \CouponTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
                        ->where('promotion_id', $val->promotion_id)->first();

                    if (!empty($couponTranslation)) {
                        foreach (['promotion_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($couponTranslation->{$field}) && $couponTranslation->{$field} !== '') {
                                $val->{$field} = $couponTranslation->{$field};
                            }
                        }

                        $media = $couponTranslation->find($couponTranslation->news_translation_id)
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
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
                                    ->where('promotion_id', $val->promotion_id)->first();

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
                $item->url = URL::to('customer/mallcoupon?id='.$item->issued_coupon_id);
                $item->name = mb_strlen($item->promotion_name) > 64 ? mb_substr($item->promotion_name, 0, 64) . '...' : $item->promotion_name;
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            return Response::json($data);

        } catch (Exception $e) {
            $data = new stdclass();
            $data->status = 0;
            $data->message = $e->getMessage();
            $data->total_records = 0;
            $data->returned_records = 0;
            $data->records = null;

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
        $issued_coupon_id = 0;
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            $issued_coupon_id = trim(OrbitInput::get('id'));

            $coupons = Coupon::with(array(
                'couponRule',
                'issuedCoupons' => function($q) use ($issued_coupon_id, $user, $retailer) {
                    $q->where('issued_coupons.issued_coupon_id', $issued_coupon_id);
                    $q->where('issued_coupons.user_id', $user->user_id);
                    $q->where('issued_coupons.expired_date', '>=', Carbon::now($retailer->timezone->timezone_name));
                    $q->where('issued_coupons.status', 'active');
                })
            )
            ->where('merchant_id', $retailer->merchant_id)
            ->where('promotions.status', 'active')
            ->whereHas('issuedCoupons', function($q) use($issued_coupon_id, $user, $retailer) {
                $q->where('issued_coupons.issued_coupon_id', $issued_coupon_id);
                $q->where('issued_coupons.user_id', $user->user_id);
                $q->where('issued_coupons.expired_date', '>=', Carbon::now($retailer->timezone->timezone_name));
                $q->where('issued_coupons.status', 'active');
            })->first();

            if (empty($coupons)) {
                // throw new Exception('Product id ' . $issued_coupon_id . ' not found');
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer));
            }

            $coupon_id = $coupons->promotion_id;

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            if (! empty($alternateLanguage)) {
                $couponTranslation = \CouponTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
            if ($coupons->is_all_retailer === 'Y') {
                $linkToAllTenant = TRUE;
                $cso_exists = FALSE;
                $tenants = \Tenant::where('parent_id','=', $retailer->merchant_id)
                ->where('status', 'active')
                ->get();
            } elseif ($coupons->is_all_retailer === 'N') {
                $linkToAllTenant = FALSE;

                $tenants = \CouponRetailer::with('tenant')->where('promotion_id', $coupon_id)->get();

                $tenants = \CouponRetailer::with('tenant', 'tenant.categories')
                    ->wherehas('tenant', function($q){
                        $q->where('merchants.status', 'active');
                    })
                    ->where('promotion_id', $coupon_id)->get();

                // -- START hack
                // 2015-9-23 17:33:00 : extracting multiple CSOs from Tenants so they won't showed up on coupon detail view

                $cso_exists = FALSE;

                $pure_tenants = array();

                foreach ($tenants as $tenant) {
                    $cso_flag = 0;

                    if (count($tenant->tenant->categories) > 0) { // check if tenant has category
                        foreach ($tenant->tenant->categories as $category) {
                            if ($category->category_name !== 'Customer Service') {
                                $cso_flag = 1;
                            } else {
                                $cso_exists = true;
                            }
                        }
                        if($cso_flag === 1) {
                            $pure_tenants[] = $tenant;
                        }
                    } else { // if not, add it right away
                        $pure_tenants[] = $tenant;
                    }
                }

                $tenants = $pure_tenants; // 100% pure tenant ready to be served
                // -- END of hack
            }

            if (empty($coupons->image)) {
                $coupons->image = 'mobile-ci/images/default_product.png';
            }

            $languages = $this->getListLanguages($retailer);

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

            $activityPageNotes = sprintf('Page viewed: Coupon Detail, Issued Coupon Id: %s', $issued_coupon_id);
            $activityPage->setUser($user)
                ->setActivityName('view_coupon')
                ->setActivityNameLong('View Coupon Detail')
                ->setObject($coupons)
                ->setCoupon($coupons)
                ->setModuleName('Coupon')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.mall-coupon', array(
                'page_title' => $coupons->promotion_name,
                'user' => $user,
                'retailer' => $retailer,
                'coupon' => $coupons,
                'tenants' => $tenants,
                'languages' => $languages,
                'cso_exists' => $cso_exists,
                'cs_reedem' => $cs_reedem,
                'link_to_all_tenant' => $linkToAllTenant
                ));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: Coupon Detail, Issued Coupon Id: %s', $issued_coupon_id);
            $activityPage->setUser($user)
                ->setActivityName('view_coupon')
                ->setActivityNameLong('View Coupon Detail Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
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
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $pagetitle = Lang::get('mobileci.page_title.promotions');

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $promotions = \News::active()
                            ->where('mall_id', $retailer->merchant_id)
                            ->where('object_type', 'promotion')
                            ->whereRaw("? between begin_date and end_date", [$mallTime]);

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

            $promotions->orderBy('sticky_order', 'desc')
                    ->orderBy('created_at', 'desc');

            $totalRec = $_promotions->count();
            $listOfRec = $promotions->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $promotionTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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
                'user_email' => $user->user_email,
                'user' => $user
            );
            return View::make('mobile-ci.mall-promotion-list', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Promotion List');
            $activityPage->setUser($user)
                ->setActivityName('view_promotion_list')
                ->setActivityNameLong('View Promotion List Failed')
                ->setObject(null)
                ->setModuleName('Promotion')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

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
    public function getSearchPromotion() {
        $user = null;
        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $promotions = \News::active()
                            ->where('mall_id', $retailer->merchant_id)
                            ->where('object_type', 'promotion')
                            ->whereRaw("? between begin_date and end_date", [$mallTime]);

            $_promotions = clone $promotions;

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

            $promotions->orderBy('sticky_order', 'desc')->orderBy('created_at', 'desc');

            $totalRec = $_promotions->count();
            $listOfRec = $promotions->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $promotionTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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
                $item->url = URL::to('customer/mallpromotion?id='.$item->news_id);
                $item->name = mb_strlen($item->news_name) > 64 ? mb_substr($item->news_name, 0, 64) . '...' : $item->news_name;
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            return Response::json($data);

        } catch (Exception $e) {
            $data = new stdclass();
            $data->status = 0;
            $data->message = $e->getMessage();
            $data->total_records = 0;
            $data->returned_records = 0;
            $data->records = null;

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
        $product_id = 0;
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        $product = null;
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $product_id = trim(OrbitInput::get('id'));

            $coupons = \News::with('tenants')->active()->where('mall_id', $retailer->merchant_id)->where('object_type', 'promotion')->where('news_id', $product_id)->first();

            if (empty($coupons)) {
                // throw new Exception('Product id ' . $product_id . ' not found');
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer));
            }

            if (empty($coupons->image)) {
                $coupons->image = 'mobile-ci/images/default_product.png';
            }

            // checking if all tenant linked to this promotion inactive or not
            // so that if all tenant inactive we can disable the 'see tenant' button on the view
            // for fix bug OM-724
            $_tenants = $coupons->tenants;

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
                    ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
                    ->where('news_id', $coupons->news_id)->first();

                if (!empty($promotionTranslation)) {
                    foreach (['news_name', 'description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($promotionTranslation->{$field}) && $promotionTranslation->{$field} !== '') {
                            $coupons->{$field} = $promotionTranslation->{$field};
                        }
                    }

                    $media = $promotionTranslation->find($promotionTranslation->news_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $coupons->image = $media->path;
                    } else {
                        // back to default image if in the content multilanguage not have image
                        // check the system language
                        $defaultLanguage = $this->getDefaultLanguage($retailer);
                        if ($defaultLanguage !== NULL) {
                            $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
                                ->where('news_id', $coupons->news_id)->first();

                            // get default image
                            $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                ->media_orig()
                                ->first();

                            if (isset($mediaDefaultLanguage->path)) {
                                $coupons->image = $mediaDefaultLanguage->path;
                            }
                        }
                    }
                }
            }

            $languages = $this->getListLanguages($retailer);

            $activityPageNotes = sprintf('Page viewed: Promotion Detail, promotion Id: %s', $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_promotion')
                ->setActivityNameLong('View Promotion Detail')
                ->setObject($coupons)
                ->setNews($coupons)
                ->setModuleName('Promotion')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.mall-promotion', array('page_title' => $coupons->news_name, 'user' => $user, 'retailer' => $retailer, 'product' => $coupons, 'languages' => $languages, 'all_tenant_inactive' => $allTenantInactive));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: Promotion Detail, promotion Id: %s', $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_promotion')
                ->setActivityNameLong('View Promotion Failed')
                ->setObject(null)
                ->setModuleName('Promotion')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
                // return $e;
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
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $pagetitle = Lang::get('mobileci.page_title.news');

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $news = \News::with('translations')->active()
                            ->where('mall_id', $retailer->merchant_id)
                            ->where('object_type', 'news')
                            ->whereRaw("? between begin_date and end_date", [$mallTime]);

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

            $news->orderBy('sticky_order', 'desc')
                    ->orderBy('created_at', 'desc');

            $totalRec = $_news->count();
            $listOfRec = $news->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $newsTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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
                'user_email' => $user->user_email,
                'user' => $user
            );
            return View::make('mobile-ci.mall-news-list', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'News List');
            $activityPage->setUser($user)
                ->setActivityName('view_news_list')
                ->setActivityNameLong('View News List Failed')
                ->setObject(null)
                ->setModuleName('News')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

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
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $news = \News::active()
                            ->where('mall_id', $retailer->merchant_id)
                            ->where('object_type', 'news')
                            ->whereRaw("? between begin_date and end_date", [$mallTime]);

            $_news = clone $news;

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

            $news->orderBy('sticky_order', 'desc')->orderBy('created_at', 'desc');

            $totalRec = $_news->count();
            $listOfRec = $news->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {
                foreach ($listOfRec as $key => $val) {

                    $newsTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                                    ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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
                $item->url = URL::to('customer/mallpromotion?id='.$item->news_id);
                $item->name = mb_strlen($item->news_name) > 64 ? mb_substr($item->news_name, 0, 64) . '...' : $item->news_name;
            }

            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            return Response::json($data);

        } catch (Exception $e) {
            $data = new stdclass();
            $data->status = 0;
            $data->message = $e->getMessage();
            $data->total_records = 0;
            $data->returned_records = 0;
            $data->records = null;

            return Response::json($data);
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
        $activityPage = Activity::mobileci()
                                   ->setActivityType('view');
        $product = null;
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);

            $product_id = trim(OrbitInput::get('id'));

            $news = \News::with('tenants')->active()->where('mall_id', $retailer->merchant_id)->where('object_type', 'news')->where('news_id', $product_id)->first();

            if (empty($news)) {
                // throw new Exception('Product id ' . $product_id . ' not found');
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer));
            }

            if (empty($news->image)) {
                $news->image = 'mobile-ci/images/default_product.png';
            }

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
                    ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
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
                                ->where('merchant_language_id', '=', $defaultLanguage->merchant_language_id)
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

            $languages = $this->getListLanguages($retailer);

            $activityPageNotes = sprintf('Page viewed: News Detail, news Id: %s', $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_news')
                ->setActivityNameLong('View News Detail')
                ->setObject($news)
                ->setNews($news)
                ->setModuleName('News')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.mall-news-detail', array('page_title' => $news->news_name, 'user' => $user, 'retailer' => $retailer, 'product' => $news, 'languages' => $languages, 'all_tenant_inactive' => $allTenantInactive));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: News Detail, news Id: %s', $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_news')
                ->setActivityNameLong('View News Detail Failed')
                ->setObject(null)
                ->setModuleName('News')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

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
        $keyword = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $languages = $this->getListLanguages($retailer);

            $activityPageNotes = sprintf('Page viewed: %s', 'Notification List Page');
            $activityPage->setUser($user)
                ->setActivityName('view_notification_list')
                ->setActivityNameLong('View Notification List')
                ->setObject(null)
                ->setModuleName('Inbox')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            $view_data = array(
                'page_title' => Lang::get('mobileci.page_title.my_messages'),
                'retailer' => $retailer,
                'active_user' => ($user->status === 'active'),
                'languages' => $languages,
                'user_email' => $user->user_email,
                'user' => $user
            );
            return View::make('mobile-ci.mall-notifications-list', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Notification List');
            $activityPage->setUser($user)
                ->setActivityName('view_notification_list')
                ->setActivityNameLong('View Notification List')
                ->setObject(null)
                ->setModuleName('Inbox')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

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
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();
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
                                'retailer'      => $retailer
                ]);
            }

            $inbox->is_read = 'Y';
            $inbox->save();



            switch ($inbox->inbox_type) {
                case 'activation':
                    $activityPageNotes = sprintf('Page viewed: %s', 'Activation Notification Detail Page');
                    $activityPage->setUser($user)
                        ->setActivityName('read_notification')
                        ->setActivityNameLong('Read Notification Activation')
                        ->setObject($inbox)
                        ->setModuleName('Inbox')
                        ->setNotes($activityPageNotes)
                        ->responseOK()
                        ->save();
                    break;

                case 'lucky_draw_issuance':
                    $activityPageNotes = sprintf('Page viewed: %s', 'Lucky Draw Number Issuance Notification Detail Page');
                    $activityPage->setUser($user)
                        ->setActivityName('read_notification')
                        ->setActivityNameLong('Read Notification Lucky Draw Number Issuance')
                        ->setObject($inbox)
                        ->setModuleName('Inbox')
                        ->setNotes($activityPageNotes)
                        ->responseOK()
                        ->save();
                    break;

                case 'lucky_draw_blast':
                    $activityPageNotes = sprintf('Page viewed: %s', 'Lucky Draw Number Issuance Notification Detail Page');
                    $activityPage->setUser($user)
                        ->setActivityName('read_notification')
                        ->setActivityNameLong('View Winner Announcement Notification')
                        ->setObject($inbox)
                        ->setModuleName('Inbox')
                        ->setNotes($activityPageNotes)
                        ->responseOK()
                        ->save();
                    break;

                case 'coupon_issuance':
                    $activityPageNotes = sprintf('Page viewed: %s', 'Coupon Issuance Notification Detail Page');
                    $activityPage->setUser($user)
                        ->setActivityName('read_notification')
                        ->setActivityNameLong('Read Notification Coupon Issuance')
                        ->setObject($inbox)
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
                'user_email' => $user->user_email,
                'user' => $user,
                'inbox' => $inbox
            );
            return View::make('mobile-ci.mall-notification-detail', $view_data);

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Notification Detail');
            $activityPage->setUser($user)
                ->setActivityName('read_notification')
                ->setActivityNameLong('Read Notification')
                ->setObject(null)
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
            $retailer = $this->getRetailerInfo();
            $lucky_draw_id = OrbitInput::get('id');
            $languages = $this->getListLanguages($retailer);
            $luckyDraw = LuckyDraw::excludeDeleted()->where('lucky_draw_id', $lucky_draw_id)->first();

            if (! is_object($luckyDraw)) {
                return View::make('mobile-ci.404', [
                                'page_title'    => Lang::get('mobileci.page_title.not_found'),
                                'user'          => $user,
                                'languages'     => $languages,
                                'retailer'      => $retailer
                ]);
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
                                     'page_title'   => 'Download Lucky Draw Number',
                                     'luckydraw'    => $luckyDraw,
                                     'retailer'     => $retailer,
                                     'user'         => $user,
                                     'total_number' => $totalLuckyDrawNumber,
                                     'total_image'  => $totalImage,
                                     'number_per_image'  => $totalPerImage,
                                     'languages' => $languages,
                                     'lucky_draw_id' => $lucky_draw_id,
                    ]);
            }
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
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

        $pdo = new PDO("mysql:host=localhost;dbname={$dbConfig['database']}", $dbConfig['username'], $dbConfig['password']);
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
    * Get list language from current merchant or mall
    *
    * @param mall     `mall`    mall object
    *
    * @author Firmansyah <firmansyah@dominopos.com>
    * @author Irianto Pratama <irianto@dominopos.com>
    *
    * @return array or collection
    */
    protected function getListLanguages($mall)
    {
        $languages = MerchantLanguage::with('language')
                                     ->where('merchant_languages.status', '!=', 'deleted')
                                     ->where('merchant_id', $mall->merchant_id)
                                     ->join('languages', 'languages.language_id', '=','merchant_languages.language_id')
                                     ->orderBy('languages.name_long', 'ASC')
                                     ->get();

        return $languages;
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
        $language = \Language::where('name', '=', $mall->mobile_default_language)->first();
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
                    $alternateLanguage->merchant_language_id);
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
        if (!empty($alternateLanguage)) {
            // join to translations table so can use to search, sort, and overwrite fields
            $prefix = DB::getTablePrefix();

            $categories->leftJoin('category_translations', function ($join) use ($alternateLanguage) {
                $join->on('categories.category_id', '=', 'category_translations.category_id');
                $join->where('category_translations.merchant_language_id', '=',
                    $alternateLanguage->merchant_language_id);
            });

            // and overwrite fields with alternate language fields if present
            foreach (['category_name', 'description'] as $field) {
                $categories->addSelect([
                    DB::raw("COALESCE(${prefix}category_translations.${field}, ${prefix}categories.${field}) as ${field}")
                ]);
            }
        }
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
            $lang_count = MerchantLanguage::where('merchant_id', $current_mall->merchant_id)
                                        ->wherehas('language', function($q) use ($lang_name)
                                        {
                                            $q->where('name', '=' , $lang_name);
                                        })->count();

            //set cookies
            if ($lang_count > 0) {
                $date_of_expiry = time() + (31556926 * 5) ; // where 31556926 is total seconds for a year.
                setcookie( "orbit_preferred_language", $lang_name, $date_of_expiry );
            } else {
                setcookie( "orbit_preferred_language", null, -3600 );
            }
        }

        return \Redirect::to('/customer/home');
   }

    /**
     * @param $user
     * @param $retailer
     * @throws Exception
     */
    protected function loginStage2($user, $retailer)
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

            $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
            if (is_null($cart)) {
                $cart = new Cart();
                $cart->customer_id = $user->user_id;
                $cart->merchant_id = $retailer->parent_id;
                $cart->retailer_id = $retailer->merchant_id;
                $cart->status = 'active';
                $cart->save();
                $cart->cart_code = Cart::CART_INCREMENT + $cart->cart_id;
                $cart->save();
            }

            $user->setHidden(array('user_password', 'apikey'));
            // check available auto-issuance coupon
            $coupons = DB::select(
                DB::raw(
                    'SELECT *, p.image AS promo_image,
                    (select count(ic.issued_coupon_id) from ' . DB::getTablePrefix() . 'issued_coupons ic
                          where ic.promotion_id = p.promotion_id
                          and ic.status != "deleted"
                          and ic.expired_date >= "' . Carbon::now($retailer->timezone->timezone_name). '") as total_issued_coupon
                FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id
                WHERE pr.rule_type = "auto_issue_on_signup"
                    AND p.merchant_id = :merchantid
                    AND p.is_coupon = "Y" AND p.status = "active"
                    AND p.begin_date <= "' . Carbon::createFromFormat('Y-m-d H:i:s', $user->created_at)->timezone($retailer->timezone->timezone_name) . '"
                    AND p.end_date >= "' . Carbon::createFromFormat('Y-m-d H:i:s', $user->created_at)->timezone($retailer->timezone->timezone_name) . '"
                    AND p.coupon_validity_in_date >= "' . Carbon::now($retailer->timezone->timezone_name) . '"
                HAVING
                    (p.maximum_issued_coupon > total_issued_coupon AND p.maximum_issued_coupon <> 0)
                    OR
                    (p.maximum_issued_coupon = 0)
                    '
                ),
                array('merchantid' => $retailer->merchant_id)
            );

            // check available autho-issuance coupon that already obtainde by user
            $obtained_coupons = DB::select(
                DB::raw(
                    'SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id
                WHERE pr.rule_type = "auto_issue_on_signup"
                    AND p.merchant_id = :merchantid
                    AND ic.user_id = :userid
                    AND p.is_coupon = "Y" AND p.status = "active"
                    AND p.begin_date <= "' . Carbon::createFromFormat('Y-m-d H:i:s', $user->created_at)->timezone($retailer->timezone->timezone_name) . '"
                    AND p.end_date >= "' . Carbon::createFromFormat('Y-m-d H:i:s', $user->created_at)->timezone($retailer->timezone->timezone_name) . '"
                    '
                ),
                array('merchantid' => $retailer->merchant_id, 'userid' => $user->user_id)
            );

            // get obtained auto-issuance coupon ids
            $obtained_coupon_ids = array();
            foreach ($obtained_coupons as $obtained_coupon) {
                $obtained_coupon_ids[] = $obtained_coupon->promotion_id;
            }

            // filter available auto-issuance coupon id by array above
            $coupons_to_be_obtained = array_filter(
                $coupons,
                function ($v) use ($obtained_coupon_ids) {
                    $match = TRUE;
                    foreach ($obtained_coupon_ids as $key => $obtained_coupon) {
                        if($v->promotion_id === $obtained_coupon) {
                            $match = $match && FALSE;
                        }
                    }

                    if($match) {
                        return $v;
                    }
                }
            );

            // get available auto-issuance coupon ids
            $couponIds = array();
            foreach ($coupons_to_be_obtained as $coupon_to_be_obtained) {
                $couponIds[] = $coupon_to_be_obtained->promotion_id;
            }

            // use them to issue
            if(count($couponIds)) {
                // Issue coupons
                $objectCoupons = [];
                $issuedCoupons = [];
                $numberOfCouponIssued = 0;
                $applicableCouponNames = [];
                $issuedCouponNames = [];
                $prefix = DB::getTablePrefix();

                foreach ($couponIds as $couponId) {
                    $coupon = Coupon::select('promotions.*',
                        DB::raw("(select count(ic.issued_coupon_id) from {$prefix}issued_coupons ic
                                                      where ic.promotion_id={$prefix}promotions.promotion_id
                                                      and ic.status!='deleted') as total_issued_coupon"))
                        ->active('promotions')
                        ->where('promotion_id', $couponId)
                        ->first();

                    $issuedCoupon = new IssuedCoupon();
                    $tmp = $issuedCoupon->issue($coupon, $user->user_id, $user);

                    $obj = new stdClass();
                    $obj->coupon_number = $tmp->issued_coupon_code;
                    $obj->coupon_name = $coupon->promotion_name;
                    $obj->promotion_id = $coupon->promotion_id;

                    $objectCoupons[] = $coupon;
                    $issuedCoupons[] = $obj;
                    $applicableCouponNames[] = $coupon->promotion_name;
                    $issuedCouponNames[$tmp->issued_coupon_code] = $coupon->promotion_name;

                    $tmp = NULL;
                    $obj = NULL;

                    $numberOfCouponIssued++;
                }

                // Insert to alert system
                $issuedCouponNames = $this->flipArrayElement($issuedCouponNames);

                $name = $user->getFullName();
                $name = trim($name) ? trim($name) : $user->user_email;
                $subject = 'Coupon';

                $retailerId = Config::get('orbit.shop.id');

                $inbox = new Inbox();
                $inbox->addToInbox($user->user_id, $issuedCouponNames, $retailerId, 'coupon_issuance');

                foreach ($objectCoupons as $object) {
                    $activity = Activity::mobileci()
                                        ->setActivityType('view');
                    $activityPageNotes = sprintf('Page viewed: %s', 'Coupon List Page');
                    $activity->setUser($user)
                            ->setActivityName('view_coupon_list')
                            ->setActivityNameLong('Coupon Issuance')
                            ->setObject($object)
                            ->setCoupon($object)
                            ->setModuleName('Coupon')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();
                }
            }

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
    private function redirectToCloud($email, $retailer, $payload = '', $from = '', $mac_address = '') {
        $this->response->code = 302; // must not be 0
        $this->response->status = 'success';
        $this->response->message = 'Redirecting to cloud'; // stored in activity by IntermediateLoginController
        $url = Config::get('orbit.registration.mobile.cloud_login_url');

        $callback_url = URL::route('customer-login-callback');
        $callback_req = \Symfony\Component\HttpFoundation\Request::create(
            $callback_url, 'GET', ['mac_address' => $mac_address]);

        $from_captive = OrbitInput::post('from_captive', 'no');
        $auto_login = OrbitInput::post('auto_login', 'no');
        $values = [
            'email' => $email,
            'retailer_id' => $retailer->merchant_id,
            'callback_url' => $callback_req->getUri(),
            'payload' => $payload,
            'from' => $from,
            'full_data' => 'no',
            'check_only' => 'no',
            'auto_login' => $auto_login,
            'from_captive' => $from_captive
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
            $from = OrbitInput::get('from');

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
                $_POST['from'] = $from;
                $response = \LoginAPIController::create('raw')->setRetailerId(OrbitInput::get('retailer_id'))->setUseTransaction(false)->postRegisterUserInShop();
                if ($response->code !== 0) {
                    throw new Exception($response->message, $response->code);
                }

                $user = $response->data;
            }

            $payload = OrbitInput::get('payload');

            if (! empty($payload)) {
                // Decrypt the payload
                $key = md5(static::PAYLOAD_KEY);
                $payload = (new Encrypter($key))->decrypt($payload);

                // The data is in url encoded
                parse_str($payload, $data);

                $from = isset($data['login_from']) ? $data['login_from'] : '';
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
                        'merchant_id' => $retailer->merchant_id
                    ]);
                }
            }

            $acq = \UserAcquisition::where('user_id', $user->user_id)
                ->where('acquirer_id', $retailer->merchant_id)
                ->lockForUpdate()->first();

            if ($acq === null && $forceInsert) {
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
                'user_acquisition_id' => isset($acq) ? $acq->user_acquisition_id : '',
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
    protected function validateRegistrationData($email)
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
            ),
            array(
                'first_name' => 'required',
                'last_name'  => 'required',
                'gender'     => 'required|in:m,f',
                'birth_date' => 'required|date_format:d-m-Y',
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
}
