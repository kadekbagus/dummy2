<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Orbit\CloudMAC;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \View;
use \User;
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

class MobileCIAPIController extends ControllerAPI
{
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

            if (trim($email) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            $retailer = $this->getRetailerInfo();

            $this->beginTransaction();

            $user = User::with('apikey', 'userdetail', 'role')
                        ->excludeDeleted()
                        ->where('user_email', $email)
                        ->whereHas(
                            'role',
                            function ($query) {
                                $query->where('role_name', 'Consumer');
                                $query->orWhere('role_name', 'Guest');
                            }
                        )->sharedLock()
                        ->first();

            if (! is_object($user)) {
                return $this->redirectToCloud($email, $retailer);
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

            $alternate_language = $this->getAlternateMerchantLanguage($user, $retailer);

            if (empty(\Cookie::get('event'))) {
                $event_store = array();
            } else {
                $event_store = \Cookie::get('event');
            }

            $events = EventModel::active()->where('merchant_id', $retailer->merchant_id)
                ->where(
                    function ($q) {
                        $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now());
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

                if (! empty($alternate_language)) {
                    $event_translation = \EventTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternate_language->merchant_language_id)
                        ->where('event_id', $events->event_id)->first();

                    if (! empty($event_translation)) {
                        foreach (['event_name', 'description'] as $field) {
                            if (isset($event_translation->{$field})) {
                                $events->{$field} = $event_translation->{$field};
                            }
                        }

                        $media = $event_translation->find($event_translation->event_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $events->image = $media->path;
                        }
                    }
                }
            }

            $widgets = Widget::with('media')
                ->active()
                ->where('merchant_id', $retailer->parent->merchant_id)
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

            foreach ($widgets as $widget) {
                if ($widget->widget_type == 'tenant') {
                    $widget_singles->tenant = $widget;
                }
                if ($widget->widget_type == 'promotion') {
                    $widget_singles->promotion = $widget;
                }
                if ($widget->widget_type == 'news') {
                    $widget_singles->news = $widget;
                }
                if ($widget->widget_type == 'coupon') {
                    $widget_singles->coupon = $widget;
                }
                if ($widget->widget_type == 'lucky_draw') {
                    $widget_singles->luckydraw = $widget;
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
     *
     * @return Illuminate\View\View
     */
    public function getSignInView()
    {
        $bg = null;
        if (\Input::get('payload')) {
            // has payload, clear out prev cookies
            $_COOKIE['orbit_firstname'] = '';
            $_COOKIE['orbit_email'] = '';
        }
        $landing_url = URL::route('ci-customer-home');
        $cookie_fname = isset($_COOKIE['orbit_firstname']) ? $_COOKIE['orbit_firstname'] : '';
        $cookie_email = isset($_COOKIE['orbit_email']) ? $_COOKIE['orbit_email'] : '';
        $display_name = '';

        if (! empty($cookie_email)) {
            $display_name = $cookie_email;
        }

        if (! empty($cookie_fname)) {
            $display_name = $cookie_fname;
        }
        $display_name = OrbitInput::get('fname', $display_name);

        $languages = [];

        $internet_info = 'no';
        try {
            $retailer = $this->getRetailerInfo();

            $languages = $this->getListLanguages($retailer);

            $mall = Mall::with('settings')->where('merchant_id', $retailer->merchant_id)
                ->first();

            //get internet_info from setting
            $internet_info_obj = $this->getObjFromArray($retailer->settings, 'internet_info');

            if (is_object($internet_info_obj)) {
                $internet_info = $internet_info_obj->setting_value;
            }

            $landing_url = $this->getLandingUrl($mall);

            try {
                $bg = Setting::getFromList($mall->settings, 'background_image');
            } catch (Exception $e) {
            }

            // Get email from query string
            $loggedUser = $this->getLoggedInUser();
            $user_email = $loggedUser->user_email;

            // Captive Portal Apple CNA Window
            // -------------------------------
            // Payload login is set and the user is logged in, no need to ask user log in again
            // assuming they was already asked on CNA captive
            if (isset($_GET['payload_login'])) {
                $payloadData = $this->proceedPayloadData();
                Cookie::forever('orbit_email', $payloadData['email'], '/', NULL, FALSE, FALSE);
                Cookie::forever('orbit_firstname', $payloadData['fname'], '/', NULL, FALSE, FALSE);

                return Redirect::to($landing_url . '?internet_info=' . $internet_info );
            }

            $viewData = array(
                'retailer' => $retailer,
                'user_email' => htmlentities($user_email),
                'bg' => $bg,
                'landing_url' => $landing_url . '?internet_info=' . $internet_info,
                'display_name' => $display_name,
                'languages' => $languages,
            );
        } catch (Exception $e) {
            $retailer = $this->getRetailerInfo();

            $user_email = OrbitInput::get('email', $cookie_email);

            $viewData = array(
                'retailer' => $retailer,
                'user_email' => htmlentities($user_email),
                'bg' => $bg,
                'landing_url' => $landing_url . '?internet_info=' . $internet_info,
                'display_name' => $display_name,
                'languages' => $languages
            );
        }

        return View::make('mobile-ci.signin', $viewData);
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
            $retailer_id = Config::get('orbit.shop.id');
            $retailer = Mall::with('parent')->where('merchant_id', $retailer_id)->first();

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
            $widget = Widget::active()->where('widget_id', $widget_id)->first();

            $activityNotes = sprintf('Widget Click. Widget Id : %s', $widget_id);
            $activity->setUser($user)
                ->setActivityName('widget_click')
                ->setActivityNameLong('Widget Click ' . ucwords(str_replace('_', ' ', $widget->widget_type)))
                ->setObject($widget)
                ->setModuleName('Widget')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (Exception $e) {
            $activityNotes = sprintf('Widget Click Failed. Widget Id : %s', $widget_id);
            $activity->setUser($user)
                ->setActivityName('widget_click')
                ->setActivityNameLong('Widget Click Failed')
                ->setObject(null)
                ->setModuleName('Widget')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
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
                    function ($q) use ($user, $value) {
                        $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.issued_coupon_id', $value)->where('expired_date', '>=', Carbon::now());
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

        $user = User::with('userDetail')->find($userId);

        if (! $user) {
            throw new Exception('Session error: user not found.');
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

            $this->session = new Session($config);
            $this->session->start();
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

            $alternate_language = $this->getAlternateMerchantLanguage($user, $retailer);


            $categories = Category::active('categories')
                ->where('category_level', 1)
                ->where('merchant_id', $retailer->merchant_id);

            $categories->select('categories.*');
            $this->maybeJoinWithCategoryTranslationsTable($categories, $alternate_language);

            $categories = $categories->get();

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $floorList = Tenant::with('mediaLogo', 'categories') // no translation needed
                ->active()
                ->where('is_mall', 'no')
                ->where('parent_id', $retailer->merchant_id)
                ->groupBy('floor')
                ->orderBy('floor')
                ->lists('floor');

            $tenants = Tenant::with('mediaLogo');
            if (!empty($alternate_language)) {
                $tenants = $tenants->with(['categories' => function ($q) use ($alternate_language) {
                    $prefix = DB::getTablePrefix();
                    $q->leftJoin('category_translations', function ($join) use ($alternate_language) {
                        $join->on('categories.category_id', '=', 'category_translations.category_id');
                        $join->where('category_translations.merchant_language_id', '=', $alternate_language->merchant_language_id);
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

            $this->maybeJoinWithTranslationsTable($tenants, $alternate_language);

            // Filter product by name pattern
            OrbitInput::get(
                'keyword',
                function ($name) use ($tenants, $alternate_language) {
                    $name_like = "%$name%";
                    $tenants->where(
                        function ($q) use ($name_like, $alternate_language) {
                            $q->where('merchants.name', 'like', $name_like)
                                ->orWhere('merchants.description', 'like', $name_like)
                                ->orWhere('merchants.floor', 'like', $name_like);
                            $q->orWhereHas('categories', function($q2) use ($name_like) {
                                $q2->where('category_name', 'like', $name_like);
                            });
                            if (!empty($alternate_language)) {
                                $q->orWhere('merchant_translations.name', 'like', $name_like)
                                    ->orWhere('merchant_translations.description', 'like', $name_like);
                            }
                        }
                    );
                }
            );

            OrbitInput::get(
                'cid',
                function ($cid) use ($tenants) {
                    if (! empty($cid)) {
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
                function ($pid) use ($tenants) {
                    if (! empty($pid)) {
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
                function ($pid) use ($tenants) {
                    if (! empty($pid)) {
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
                function ($pid) use ($tenants) {
                    if (! empty($pid)) {
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

            // Get the take args
            $take = 1000;
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

            if (!empty($alternate_language) && $sortBy === 'merchants.name') {
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
                    // dd($tenant->phone);
                }
            }

            // should not be limited for new products - limit only when searching
            $search_limit = Config::get('orbit.shop.search_limit');
            if ($totalRec>$search_limit) {
                $data = new stdclass();
                $data->status = 0;
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = count($listOfRec);
                $data->records = $listOfRec;
            }

            if (! empty(OrbitInput::get('promotion_id'))) {
                $pagetitle = Lang::get('mobileci.page_title.promotions_tenants');

                $activityPageNotes = sprintf('Page viewed: Promotion Tenants List Page, promotion ID: %s', OrbitInput::get('promotion_id'));
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Promotion Tenant')
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
                    ->setActivityNameLong('View News Tenant')
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
                    ->setActivityNameLong('View Events Tenant')
                    ->setObject(null)
                    ->setModuleName('Tenant')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();
            }

            if (! empty(OrbitInput::get('event_id')) && ! empty(OrbitInput::get('promotion_id'))) {
                $activityPageNotes = sprintf('Page viewed: Tenant Listing Page');
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Tenant')
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

            $alternate_language = $this->getAlternateMerchantLanguage($user, $retailer);

            $tenant = Tenant::with( // translated
                'media',
                'mediaLogoOrig',
                'mediaMapOrig',
                'mediaImageOrig',
                'news',
                'newsPromotions')
                ->active('merchants')
                ->where('is_mall', 'no')
                ->where('parent_id', $retailer->merchant_id)
                ->where('merchants.merchant_id', $product_id);
            $tenant->select('merchants.*');
            $this->maybeJoinWithTranslationsTable($tenant, $alternate_language);
            $tenant = $tenant->first();

            if (empty($tenant)) {
                // throw new Exception('Product id ' . $product_id . ' not found');
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer));
            }

            if (empty($tenant->logo)) {
                $tenant->logo = 'mobile-ci/images/default_product.png';
            }

            $languages = $this->getListLanguages($retailer);

            // cek if any language active
            if (!empty($alternate_language) && !empty($tenant)) {
                    $merchant_translation = \MerchantTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternate_language->merchant_language_id)
                        ->where('merchant_id', $tenant->merchant_id)->first();

                if (!empty($merchant_translation)) {
                    foreach (['merchant_name', 'description'] as $field) {
                        if (isset($merchant_translation->{$field})) {
                            $tenant->{$field} = $merchant_translation->{$field};
                        }
                    }

                }
            }


            if (! empty($promo_id)) {
                $activityPageNotes = sprintf('Page viewed: Tenant Detail Page from Promotion, tenant ID: ' . $tenant->merchant_id . ', promotion ID: '. $promo_id);
                $activityPage->setUser($user)
                    ->setActivityName('view_retailer')
                    ->setActivityNameLong('View Tenant')
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
                    ->setActivityNameLong('View Tenant')
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
                    ->setActivityNameLong('View Tenant')
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
            $luckydraw = LuckyDraw::active()->where('mall_id', $retailer->merchant_id)->first();

            if (empty($luckydraw)) {
                return View::make('mobile-ci.luckydraw', [
                                'page_title'    => 'LUCKY DRAW',
                                'user'          => $user,
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

            // Pass information to the API
            $_GET['user_id'] = $user->user_id;
            $_GET['apikey'] = $user->apikey->api_key;
            $_GET['apitimestamp'] = time();
            $_GET['lucky_draw_id'] = (array) $luckydraw->lucky_draw_id;

            $currentPage = (int)OrbitInput::get('page', 1);
            $take = 100;
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

            if ($totalPages > 1) {
                $prevUrl = URL::route('ci-luckydraw') . '?page=' . ($currentPage - 1);
                $nextUrl = URL::route('ci-luckydraw') . '?page=' . ($currentPage + 1);

                if ($currentPage >= $totalPages) {
                    $nextUrl = '#';
                }

                if ($currentPage === 1) {
                    $prevUrl = '#';
                }
            }

            $activityProductNotes = sprintf('Page viewed: Lucky Draw Page');
            $activityProduct->setUser($user)
                ->setActivityName('view_lucky_draw')
                ->setActivityNameLong('View Lucky Draw')
                ->setObject($luckydraw)
                ->setModuleName('Lucky Draw')
                ->setNotes($activityProductNotes)
                ->responseOK()
                ->save();

            $numbers = empty($apiResponse->data->records) ? array() : $apiResponse->data->records;

            $servertime = Carbon::now();

            return View::make('mobile-ci.luckydraw', [
                                'page_title'    => 'LUCKY DRAW',
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
            ]);
        } catch (Exception $e) {
            $activityProductNotes = sprintf('Failed to view: Lucky Draw Page');
            $activityProduct->setUser($user)
                ->setActivityName('view_lucky_draw')
                ->setActivityNameLong('View Lucky Draw')
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

            $sort_by = OrbitInput::get('sort_by');
            $keyword = trim(OrbitInput::get('keyword'));
            $category_id = trim(OrbitInput::get('cid'));
            $floor = trim(OrbitInput::get('floor'));

            $pagetitle = Lang::get('mobileci.page_title.coupons');

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

            $alternate_language = $this->getAlternateMerchantLanguage($user, $retailer);

            // $categories = Category::active()->where('category_level', 1)->where('merchant_id', $retailer->merchant_id)->get();

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $coupons = DB::select(
                DB::raw(
                    'SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                WHERE ic.expired_date >= "' . Carbon::now(). '"
                    AND p.merchant_id = :merchantid
                    AND ic.user_id = :userid'
                ),
                array('merchantid' => $retailer->merchant_id, 'userid' => $user->user_id)
            );

            if (! empty($alternate_language)) {
                foreach ($coupons as $coupon) {
                    $coupon_translation = \CouponTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternate_language->merchant_language_id)
                        ->where('promotion_id', $coupon->promotion_id)->first();

                    if (! empty($coupon_translation)) {
                        foreach (['promotion_name', 'description', 'long_description'] as $field) {
                            if (isset($coupon_translation->{$field})) {
                                $coupon->{$field} = $coupon_translation->{$field};
                            }
                        }

                        $media = $coupon_translation->find($coupon_translation->coupon_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $coupon->promo_image = $media->path;
                        }
                    }
                }
            }

            if (sizeof($coupons) < 1) {
                $data = new stdclass();
                $data->status = 0;
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = sizeof($coupons);
                $data->returned_records = sizeof($coupons);
                $data->records = $coupons;
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
     * GET - Coupon detail page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
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

            // $coupons = Coupon::select(
            //     DB::raw(
            //         'SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
            //     inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y" and p.status = "active" AND ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y"))
            //     inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
            //     WHERE ic.expired_date >= "' . Carbon::now(). '" AND p.merchant_id = :merchantid AND ic.user_id = :userid AND ic.expired_date >= "' . Carbon::now() . '" AND ic.issued_coupon_id = :issuedid'
            //     ),
            //     array('merchantid' => $retailer->merchant_id, 'userid' => $user->user_id, 'issuedid' => $issued_coupon_id)
            // );

            $coupons = Coupon::with(array('couponRule', 'issuedCoupons' => function($q) use($issued_coupon_id, $user){
                $q->where('issued_coupons.issued_coupon_id', $issued_coupon_id);
                $q->where('issued_coupons.user_id', $user->user_id);
                $q->where('issued_coupons.expired_date', '>=', Carbon::now());
                $q->where('issued_coupons.status', 'active');
            }))
            ->where('merchant_id', $retailer->merchant_id)
            // ->active()
            // ->where(function($q){
            //     $q->where(function($q2){
            //         $q2->where('begin_date', '<=', Carbon::now());
            //         $q2->where('end_date', '>=',  Carbon::now());
            //     });
            //     $q->orWhere(function($q2){
            //         $q2->where('begin_date', '<=', Carbon::now());
            //         $q2->whereNull('end_date');
            //     });
            //     $q->orWhere(function($q2){
            //         $q2->whereNull('begin_date');
            //         $q2->where('end_date', '>=',  Carbon::now());
            //     });
            // })
            ->whereHas('issuedCoupons', function($q) use($issued_coupon_id, $user) {
                $q->where('issued_coupons.issued_coupon_id', $issued_coupon_id);
                $q->where('issued_coupons.user_id', $user->user_id);
                $q->where('issued_coupons.expired_date', '>=', Carbon::now());
                $q->where('issued_coupons.status', 'active');
            })->first();

            $coupon_id = $coupons->promotion_id;

            $alternate_language = $this->getAlternateMerchantLanguage($user, $retailer);

            if (! empty($alternate_language)) {
                $coupon_translation = \CouponTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternate_language->merchant_language_id)
                    ->where('promotion_id', $coupons->promotion_id)->first();

                if (! empty($coupon_translation)) {
                    foreach (['promotion_name', 'description', 'long_description'] as $field) {
                        if (isset($coupon_translation->{$field})) {
                            $coupons->{$field} = $coupon_translation->{$field};
                        }
                    }

                    $media = $coupon_translation->find($coupon_translation->coupon_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $coupons->image = $media->path;
                    }
                }
            }

            $tenants = \CouponRetailer::with('tenant')->where('promotion_id', $coupon_id)->get();

            if (empty($coupons)) {
                // throw new Exception('Product id ' . $issued_coupon_id . ' not found');
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer));
            }

            if (empty($coupons->image)) {
                $coupons->image = 'mobile-ci/images/default_product.png';
            }

            $languages = $this->getListLanguages($retailer);

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
                            $cso_exists = TRUE;
                            $cso_flag = 1;
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
                'cso_exists' => $cso_exists));

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

            $alternate_language = $this->getAlternateMerchantLanguage($user, $retailer);

            $sort_by = OrbitInput::get('sort_by');
            $keyword = trim(OrbitInput::get('keyword'));
            $category_id = trim(OrbitInput::get('cid'));
            $floor = trim(OrbitInput::get('floor'));

            $pagetitle = Lang::get('mobileci.page_title.promotions');

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

            // $categories = Category::active()->where('category_level', 1)->where('merchant_id', $retailer->merchant_id)->get();

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

            $coupons = \News::active()
                            ->where('mall_id', $retailer->merchant_id)
                            ->where('object_type', 'promotion')
                            ->whereRaw("NOW() between begin_date and end_date")
                            ->orderBy('sticky_order', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->get();

            if (!empty($alternate_language) && !empty($coupons)) {
                foreach ($coupons as $key => $val) {

                    $coupon_translation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternate_language->merchant_language_id)
                        ->where('news_id', $val->news_id)->first();

                    if (!empty($coupon_translation)) {
                        foreach (['news_name', 'description'] as $field) {
                            if (isset($coupon_translation->{$field})) {
                                $val->{$field} = $coupon_translation->{$field};
                            }
                        }

                        $media = $coupon_translation->find($coupon_translation->news_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        }

                    }
                }
            }


            if ($coupons->isEmpty()) {
                $data = new stdclass();
                $data->status = 0;
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = sizeof($coupons);
                $data->returned_records = sizeof($coupons);
                $data->records = $coupons;
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
     * GET - Promotion detail page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
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

            $alternate_language = $this->getAlternateMerchantLanguage($user, $retailer);

            $product_id = trim(OrbitInput::get('id'));

            $coupons = \News::with('tenants')->active()->where('mall_id', $retailer->merchant_id)->where('object_type', 'promotion')->where('news_id', $product_id)->first();

            if (empty($coupons)) {
                // throw new Exception('Product id ' . $product_id . ' not found');
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer));
            }

            if (empty($coupons->image)) {
                $coupons->image = 'mobile-ci/images/default_product.png';
            }

            if (! empty($alternate_language)) {
                $coupon_translation = \NewsTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternate_language->merchant_language_id)
                    ->where('news_id', $coupons->news_id)->first();

                if (!empty($coupon_translation)) {
                    foreach (['news_name', 'description'] as $field) {
                        if (isset($coupon_translation->{$field})) {
                            $coupons->{$field} = $coupon_translation->{$field};
                        }
                    }

                    $media = $coupon_translation->find($coupon_translation->news_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $coupons->image = $media->path;
                    }

                }
            }

            $languages = $this->getListLanguages($retailer);

            $activityPageNotes = sprintf('Page viewed: Promotion Detail, promotion Id: %s', $product_id);
            $activityPage->setUser($user)
                ->setActivityName('view_promotion')
                ->setActivityNameLong('View Promotion')
                ->setObject($coupons)
                ->setNews($coupons)
                ->setModuleName('Promotion')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.mall-promotion', array('page_title' => $coupons->news_name, 'user' => $user, 'retailer' => $retailer, 'product' => $coupons, 'languages' => $languages));

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

            $alternate_language = $this->getAlternateMerchantLanguage($user, $retailer);

            $sort_by = OrbitInput::get('sort_by');
            $keyword = trim(OrbitInput::get('keyword'));
            $category_id = trim(OrbitInput::get('cid'));
            $floor = trim(OrbitInput::get('floor'));

            $pagetitle = Lang::get('mobileci.page_title.news');

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

            // $categories = Category::active()->where('category_level', 1)->where('merchant_id', $retailer->merchant_id)->get();

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 250;
            }

            $news = \News::with('translations')->active()
                            ->where('mall_id', $retailer->merchant_id)
                            ->where('object_type', 'news')
                            ->whereRaw("NOW() between begin_date and end_date")
                            ->orderBy('sticky_order', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->get();

            if (!empty($alternate_language) && !empty($news)) {
                foreach ($news as $key => $val) {

                    $news_translation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternate_language->merchant_language_id)
                        ->where('news_id', $val->news_id)->first();

                    if (!empty($news_translation)) {
                        foreach (['news_name', 'description'] as $field) {
                            if (isset($news_translation->{$field})) {
                                $val->{$field} = $news_translation->{$field};
                            }
                        }

                        $media = $news_translation->find($news_translation->news_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        }

                    }
                }
            }

            if ($news->isEmpty()) {
                $data = new stdclass();
                $data->status = 0;
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = sizeof($news);
                $data->returned_records = sizeof($news);
                $data->records = $news;
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
     * GET - News detail page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
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

            $alternate_language = $this->getAlternateMerchantLanguage($user, $retailer);

            $product_id = trim(OrbitInput::get('id'));

            $news = \News::with('tenants')->active()->where('mall_id', $retailer->merchant_id)->where('object_type', 'news')->where('news_id', $product_id)->first();

            if (empty($news)) {
                // throw new Exception('Product id ' . $product_id . ' not found');
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer));
            }

            if (empty($news->image)) {
                $news->image = 'mobile-ci/images/default_product.png';
            }

            // cek if any language active
            if (!empty($alternate_language) && !empty($news)) {
                $news_translation = \NewsTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternate_language->merchant_language_id)
                    ->where('news_id', $news->news_id)->first();

                if (!empty($news_translation)) {
                    foreach (['news_name', 'description'] as $field) {
                        if (isset($news_translation->{$field})) {
                            $news->{$field} = $news_translation->{$field};
                        }
                    }

                    $media = $news_translation->find($news_translation->news_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $news->image = $media->path;
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

            return View::make('mobile-ci.mall-news-detail', array('page_title' => $news->news_name, 'user' => $user, 'retailer' => $retailer, 'product' => $news, 'languages' => $languages));

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
            $luckyDraw = LuckyDraw::active()->first();

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

                    $languages = $this->getListLanguages($retailer);

                    return View::make('mobile-ci.lucky-draw-number-download', [
                                     'page_title'   => 'Download Lucky Draw Number',
                                     'luckydraw'    => $luckyDraw,
                                     'retailer'     => $retailer,
                                     'user'         => $user,
                                     'total_number' => $totalLuckyDrawNumber,
                                     'total_image'  => $totalImage,
                                     'number_per_image'  => $totalPerImage,
                                     'languages' => $languages,
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

        $pdo = new PDO("mysql:host=localhost;dbname={$dbConfig['database']}", $dbConfig['username'], $dbConfig['password']);
        $query = $pdo->query("SELECT * FROM {$prefix}lucky_draws
                              where lucky_draw_id=$luckyDrawId and status='active' LIMIT 1");
        $luckyDraw = $query->fetch(PDO::FETCH_ASSOC);

        $countQuery = $pdo->query("SELECT count(*) as total FROM {$prefix}lucky_draw_numbers
                                  where user_id=$userId");
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
        $header = sprintf('%-12s: %s - %s', 'Periode', $periodHumanStart, $periodHumanEnd);

        $ypos += $heighPerLine;
        imagestring($im, $fontSize, $xpos, $ypos, $header, $black);

        $totalSentences = 'Total nomor lucky draw yang anda peroleh per tanggal';
        $ypos += $heighPerLine * 2;
        imagestring($im, $fontSize, $xpos, $ypos, $totalSentences, $black);

        $today = date('d/m/Y H:i');
        $totalSentences = sprintf('%s adalah sebanyak %s nomor.', $today, $numberOfLuckyDraw);
        $ypos += $heighPerLine;
        imagestring($im, $fontSize, $xpos, $ypos, $totalSentences, $black);

        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $uresult = $pdo->query("SELECT * FROM {$prefix}lucky_draw_numbers
                                where user_id=$userId order by issued_date desc,
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

            $goodLuckString = 'Semoga Anda Beruntung!';;
            $xpos = 14;
            $ypos += $heighPerLine * 2;
            imagestring($im, $fontSize, $xpos, $ypos, $goodLuckString, $black);

            $pageInfoString = sprintf('Gambar %s dari %s', $currentPage, $totalPages, $take);
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
        $subject = Lang::get('mobileci.inbox.coupon.subject');

        $inbox = new Inbox();
        $inbox->user_id = $userId;
        $inbox->from_id = 0;
        $inbox->from_name = 'Orbit';
        $inbox->subject = $subject;
        $inbox->content = '';
        $inbox->inbox_type = 'alert';
        $inbox->status = 'active';
        $inbox->is_read = 'N';
        $inbox->save();

        $retailerId = Config::get('orbit.shop.id');
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
        $languages = MerchantLanguage::with('language')->excludeDeleted()
                                    ->where('merchant_id', $mall->merchant_id)
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
        $priority = ['cookie', 'user_preference', 'browser'];
        $getters = [
            'cookie' => function ($user) {
                // cannot use Cookie:: or Request::cookie, those insist on signed cookies.
                if (!array_key_exists('orbit_preferred_language', $_COOKIE)) {
                    return null;
                }
                return $_COOKIE['orbit_preferred_language'];
            },
            'user_preference' => function ($user) {
                $details = $user->userdetail;
                // if user has no preference use default
                if (empty($details)) {
                    return null;
                }
                return $details->preferred_language;
            },
            'browser' => function ($user) {
                $lang = \Request::server('HTTP_ACCEPT_LANGUAGE', null);
                if ($lang === null) {
                    return null;
                }
                return substr($lang, 0, 2);
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
            $alternate_language = \MerchantLanguage::excludeDeleted()
                ->where('merchant_id', '=', $mall->merchant_id)
                ->where('language_id', '=', $selected_language->language_id)
                ->first();

            if ($alternate_language !== null) {
                return $alternate_language;
            }
        }
        // above methods did not result in any selected language, use mall default
        return null;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $tenants
     * @param \MerchantLanguage $alternate_language
     */
    private function maybeJoinWithTranslationsTable($tenants, $alternate_language)
    {
        if (!empty($alternate_language)) {
            // join to translations table so can use to search, sort, and overwrite fields
            $prefix = DB::getTablePrefix();

            $tenants->leftJoin('merchant_translations', function ($join) use ($alternate_language) {
                $join->on('merchants.merchant_id', '=', 'merchant_translations.merchant_id');
                $join->where('merchant_translations.merchant_language_id', '=',
                    $alternate_language->merchant_language_id);
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
     * @param \MerchantLanguage $alternate_language
     */
    private function maybeJoinWithCategoryTranslationsTable($categories, $alternate_language)
    {
        if (!empty($alternate_language)) {
            // join to translations table so can use to search, sort, and overwrite fields
            $prefix = DB::getTablePrefix();

            $categories->leftJoin('category_translations', function ($join) use ($alternate_language) {
                $join->on('categories.category_id', '=', 'category_translations.category_id');
                $join->where('category_translations.merchant_language_id', '=',
                    $alternate_language->merchant_language_id);
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

            $user_detail = UserDetail::where('user_id', $user->user_id)->first();
            $user_detail->last_visit_shop_id = $retailer->merchant_id;
            $user_detail->last_visit_any_shop = Carbon::now();
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
                          and ic.status!="deleted") as total_issued_coupon
                FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id
                WHERE pr.rule_type = "auto_issue_on_signup"
                    AND p.merchant_id = :merchantid
                    AND p.is_coupon = "Y" AND p.status = "active"
                    AND p.begin_date <= "' . $user->created_at . '"
                    AND p.end_date >= "' . $user->created_at . '"
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
                    AND p.begin_date <= "' . $user->created_at . '"
                    AND p.end_date >= "' . $user->created_at . '"
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
                $subject = Lang::get('mobileci.inbox.coupon.subject');

                $inbox = new Inbox();
                $inbox->user_id = $user->user_id;
                $inbox->from_id = 0;
                $inbox->from_name = 'Orbit';
                $inbox->subject = $subject;
                $inbox->content = '';
                $inbox->inbox_type = 'alert';
                $inbox->status = 'active';
                $inbox->is_read = 'N';
                $inbox->save();

                $retailerId = Config::get('orbit.shop.id');
                $retailer = Mall::where('merchant_id', $retailerId)->first();
                $data = [
                    'fullName'          => $name,
                    'subject'           => 'Coupon',
                    'inbox'             => $inbox,
                    'retailerName'      => $retailer->name,
                    'numberOfCoupon'    => count($issuedCoupons),
                    'coupons'           => $issuedCouponNames,
                    'mallName'          => $retailer->name
                ];

                $template = View::make('mobile-ci.push-notification-coupon', $data);

                $inbox->content = $template;
                $inbox->save();
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
    private function redirectToCloud($email, $retailer) {
        $this->response->code = 302; // must not be 0
        $this->response->status = 'success';
        $this->response->message = 'Redirecting to cloud'; // stored in activity by IntermediateLoginController
        $url = Config::get('orbit.registration.mobile.cloud_login_url');
        $values = [
            'email' => $email,
            'retailer_id' => $retailer->merchant_id,
            'callback_url' => URL::route('customer-login-callback'),
        ];
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
     * @return \OrbitShop\API\v1\ResponseProvider|string
     */
    public function getCloudLogin()
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
            $user = User::with('apikey', 'userdetail', 'role')
                ->excludeDeleted()
                ->where('user_email', $email)
                ->whereHas(
                    'role',
                    function ($query) {
                        $query->where('role_name', 'Consumer');
                    }
                )->sharedLock()
                ->first();

            if ($user === null) {
                $_POST['email'] = $email;
                $response = \LoginAPIController::create('raw')->setRetailerId(OrbitInput::get('retailer_id'))->setUseTransaction(false)->postRegisterUserInShop();
                if ($response->code !== 0) {
                    throw new Exception($response->message, $response->code);
                }

                $user = $response->data;
            }

            $acq = \UserAcquisition::where('user_id', $user->user_id)
                ->where('acquirer_id', $retailer->merchant_id)
                ->lockForUpdate()->first();

            if ($acq === null) {
                $acq = new \UserAcquisition();
                $acq->user_id = $user->user_id;
                $acq->acquirer_id = $retailer->merchant_id;
                $acq->save();
            }


            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = (object)[
                'user_id' => $user->user_id,
                'user_email' => $user->user_email,
                'apikey_id' => $user->apikey->apikey_id,
                'user_detail_id' => $user->userdetail->user_detail_id,
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

}
