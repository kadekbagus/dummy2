<?php
/**
 * Class for represent the activities table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitRelation\BelongsTo as BelongsToObject;
use DominoPOS\OrbitSession\SessionConfig;
use DominoPOS\OrbitSession\Session;
use Orbit\Helper\Session\AppOriginProcessor;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class Activity extends Eloquent
{
    protected $primaryKey = 'activity_id';
    protected $table = 'activities';

    /**
     * Field which need to be masked.
     */
    protected $maskedFields = ['password', 'password_confirmation'];

    const ACTIVITY_REPONSE_OK = 'OK';
    const ACTIVITY_RESPONSE_FAILED = 'Failed';

    const ACTIVTY_GROUP_MOBILE = 'mobile-ci';

    protected $hidden = ['http_method', 'request_uri', 'post_data', 'metadata_user', 'metadata_staff', 'metadata_object', 'metadata_location'];

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Store the session object.
     *
     * @var Session
     */
    protected static $session = NULL;

    /**
     * Add new masked fields, so it will not saved plaintext
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Activity
     */
    public function setMaskedFields(array $maskedFields)
    {
        $this->maskedFields = array_merge($this->maskedFields + $maskedFields);

        return $this;
    }

    /**
     * Common task which called by multiple group.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    protected function fillCommonValues()
    {
        $this->ip_address = static::getIPAddress();
        $this->user_agent = static::getUserAgent();
        $this->http_method = static::getRequestMethod();
        $this->request_uri = static::getRequestUri();

        if (isset($_POST) && ! empty($_POST)) {
            $post = $_POST;

            // Check for masked fields
            foreach ($post as $key=>&$field) {
                if (in_array($key, $this->maskedFields)) {
                    $field = '**********';
                }
            }
            $this->post_data = serialize($post);
        }

        if ($this->group === 'pos' || $this->group === 'mobile-ci') {
            $this->location_id = Config::get('orbit.shop.id');
        }

        if ($this->group === 'mobile-ci') {
            if (isset($_COOKIE['from_wifi'])) {
                $domain = Config::get('orbit.captive.from_wifi.domain', NULL);
                $path = Config::get('orbit.captive.from_wifi.path', '/');
                $expire = time() + Config::get('orbit.captive.from_wifi.expire', 60); // default expired if doesnt exist is 60 second (1 minute)

                setcookie(Config::get('orbit.captive.from_wifi.name', 'from_wifi'), 'Y', $expire, $path, $domain, FALSE);

                $this->from_wifi = 'Y';
            } else {
                $this->from_wifi = 'N';
            }
        }

        return $this;
    }

    /**
     * Create an activity based on parent value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Activity
     */
    public static function parent($activityParent)
    {
        $activity = clone $activityParent;
        $activity->exists = FALSE;
        $activity->parent_id = $activityParent->activity_id;
        unset($activity->activity_id);

        return $activity;
    }

    /**
     * Set the value of `group`, `ip_address`, `user_agent`, and `location_id`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Activity
     */
    public static function mobileCI()
    {
        $activity = new static();
        $activity->group = 'mobile-ci';
        $activity->fillCommonValues();

        return $activity;
    }

    /**
     * Set the value of `group`, `ip_address`, `user_agent`, and `location_id`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Activity
     */
    public static function csportal()
    {
        $activity = new static();
        $activity->group = 'cs-portal';
        $activity->fillCommonValues();

        return $activity;
    }

    /**
     * Set the value of `group`, `ip_address`, `user_agent`, and `location_id`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Activity
     */
    public static function pos()
    {
        $activity = new static();
        $activity->group = 'pos';
        $activity->fillCommonValues();

        return $activity;
    }

    /**
     * Set the value of `group`, `ip_address`, `user_agent`, and `location_id`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Activity
     */
    public static function unknown($group='unknown')
    {
        $activity = new static();
        $activity->group = $group;
        $activity->fillCommonValues();

        return $activity;
    }

    /**
     * Set the value of `group`, `ip_address`, `user_agent`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Activity
     */
    public static function portal()
    {
        $activity = new static();
        $activity->group = 'portal';
        $activity->fillCommonValues();

        return $activity;
    }

    /**
     * Set the value of `activity_name`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $activityName
     * @return Activity
     */
    public function setActivityName($activityName)
    {
        $this->activity_name = $activityName;

        return $this;
    }

    /**
     * Set the value of `activity_name_long`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $activityNameLong
     * @return Activity
     */
    public function setActivityNameLong($activityNameLong)
    {
        $this->activity_name_long = $activityNameLong;

        return $this;
    }

    /**
     * Set the value of `activity_type`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @type string $type
     * @return Activity
     */
    public function setActivityType($type)
    {
        $this->activity_type = $type;

        return $this;
    }

    /**
     * Set the value of `notes`
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $notes - Notes
     * @return Activity
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * Set the value of `user_id`, `user_email`, and `metadata_user`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string|User $user
     * @return Activity
     */
    public function setUser($user='guest')
    {
        if (is_object($user)) {
            $this->user_id = $user->user_id;
            $this->user_email = $user->user_email;
            $this->full_name = $user->getFullName();
            $this->role_id = $user->role->role_id;
            $this->role = $user->role->role_name;
            try {
                $this->gender = $user->userdetail->gender;
            } catch (Exception $e) {
                $this->gender = NULL;
            }

            $this->metadata_user = $user->toJSON();
        }

        if ($user === 'guest' || is_null($user)) {
            $this->user_id = 0;
            $this->user_email = 'guest';
            $this->role_id = 0;
            $this->role = 'Guest';
            $this->full_name = 'Guest User';
        }

        return $this;
    }

    /**
     * Set the value of `staff_id` and `metadata_staff`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param User $user
     * @return Activity
     */
    public function setStaff($user)
    {
        if (is_object($user)) {
            $user->employee;

            $this->staff_id = $user->user_id;
            $this->staff_name = $user->getFullName();
            $this->metadata_staff = $user->toJSON();
        }

        return $this;
    }

    /**
     * Set the value of `location_id`, `location_name`, and `metadata_location`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Retailer|Merchant $location
     * @return Activity
     */
    public function setLocation($location)
    {
        if (is_object($location)) {
            $this->location_id = $location->merchant_id;
            $this->location_name = $location->name;
            $this->metadata_location = $location->toJSON();
        }

        return $this;
    }

    /**
     * Set the value of `object_id`, `object_name`, and `metadata_object`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Object $object
     * @return Activity
     */
    public function setObject($object)
    {
        if (is_object($object)) {
            $primaryKey = $object->getKeyName();
            $this->object_id = $object->$primaryKey;
            $this->object_name = get_class($object);

            switch (get_class($object)) {
                case 'News':
                    $this->object_display_name = $object->news_name;
                    break;

                case 'Coupon':
                case 'Promotion':
                    $this->object_display_name = $object->promotion_name;
                    break;

                case 'IssuedCoupon':
                    $coupon = Coupon::excludeDeleted()->where('promotion_id', $object->promotion_id)->first();
                    if (is_object($coupon)) {
                        $this->object_display_name = $coupon->promotion_name;
                    }
                    break;

                case 'Merchant':
                case 'Retailer':
                case 'MallGroup':
                case 'Mall':
                case 'Tenant':
                case 'TenantStoreAndService':
                case 'CampaignLocation':
                    $this->object_display_name = $object->name;
                    break;

                case 'LuckyDraw':
                    $this->object_display_name = $object->lucky_draw_name;
                    break;

                case 'EventModel':
                    $this->object_display_name = $object->event_name;
                    break;

                case 'Event':
                    $this->object_display_name = $object->event_name;
                    break;

                case 'Object':
                    $this->object_display_name = $object->object_name;
                    break;

                case 'User':
                    $this->object_display_name = $object->getFullName();
                    break;

                case 'Inbox':
                    $this->object_display_name = $object->subject;
                    break;

                default:
                    $this->object_display_name = NULL;
                    break;
            }

            $this->metadata_object = $object->toJSON();
        }

        return $this;
    }

    /**
     * Set the value of `product_id`, `product_name`, and `metadata_object`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Object $object
     * @return Activity
     */
    public function setProduct($object)
    {
        if (is_object($object)) {
            $primaryKey = $object->getKeyName();
            $this->product_id = $object->$primaryKey;
            $this->product_name = $object->product_name;

            $this->metadata_object = $object->toJSON();
        }

        return $this;
    }

    /**
     * Set the value of `promotion_id`, `promotion_name`, and `metadata_object`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Object $object
     * @return Activity
     */
    public function setPromotion($object)
    {
        if (is_object($object)) {
            $primaryKey = $object->getKeyName();
            $this->promotion_id = $object->$primaryKey;
            $this->promotion_name = $object->promotion_name;

            $this->metadata_object = $object->toJSON();
        } else {
            $this->promotion_id = null;
            $this->promotion_name = null;

            $this->metadata_object = null;
        }

        return $this;
    }

    /**
     * Set the value of `coupon_id`, `coupon_name`, and `metadata_object`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Object $object
     * @return Activity
     */
    public function setCoupon($object)
    {
        if (is_object($object)) {
            $primaryKey = $object->getKeyName();
            $this->coupon_id = $object->$primaryKey;
            $this->coupon_name = $object->promotion_name;

            $this->metadata_object = $object->toJSON();
        } else {
            $this->coupon_id = null;
            $this->coupon_name = null;

            $this->metadata_object = null;
        }

        return $this;
    }

    /**
     * Set the value of `news_id`, and `metadata_object`.
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param Object $object
     * @return Activity
     */
    public function setNews($object)
    {
        if (is_object($object)) {
            $primaryKey = $object->getKeyName();
            $this->news_id = $object->$primaryKey;

            $this->metadata_object = $object->toJSON();
        } else {
            $this->news_id = null;

            $this->metadata_object = null;
        }

        return $this;
    }

    /**
     * Set the value of `event_id`, `event_name`, and `metadata_object`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Object $object
     * @return Activity
     */
    public function setEvent($object)
    {
        if (is_object($object)) {
            $primaryKey = $object->getKeyName();
            $this->event_id = $object->$primaryKey;
            $this->event_name = $object->event_name;

            $this->metadata_object = $object->toJSON();
        } else {
            $this->event_id = null;
            $this->event_name = null;

            $this->metadata_object = null;
        }

        return $this;
    }

    /**
     * Set the value of module name.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $name
     * @return Activity
     */
    public function setModuleName($name)
    {
        $this->module_name = $name;

        return $this;
    }

    /**
     * Set the value of 'response_status' field with OK status
     *
     * @author Rio Astamal
     * @return Activity
     */
    public function responseOK()
    {
        $this->response_status = static::ACTIVITY_REPONSE_OK;

        return $this;
    }

    /**
     * Set the value of 'response_status' field with Failed status
     *
     * @author Rio Astamal
     * @return Activity
     */
    public function responseFailed()
    {
        $this->response_status = static::ACTIVITY_RESPONSE_FAILED;

        return $this;
    }

    /**
     * Activity belongs to a User
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    /**
     * Activity belongs to a Location (retailer/shop)
     */
    public function retailer()
    {
        return $this->belongsTo('Retailer', 'location_id', 'merchant_id');
    }

    /**
     * Activity belongs to a Staff
     */
    public function staff()
    {
        return $this->belongsTo('User', 'staff_id', 'user_id');
    }

    /**
     * An activity belongs to an Object (Product)
     */
    public function product()
    {
        return $this->belongsToObject('Product', 'object_id', 'product_id');
    }

    /**
     * An activity could belongs to a ProductVariant
     */
    public function productVariant()
    {
        return $this->belongsToObject('ProductVariant', 'object_id', 'product_variant_id');
    }

    /**
     * An activity could belongs to a Promotion
     */
    public function promotion()
    {
        return $this->belongsToObject('Promotion', 'object_id', 'promotion_id');
    }

    /**
     * An activity could belongs to a Coupon
     */
    public function coupon()
    {
        return $this->belongsToObject('Coupon', 'object_id', 'promotion_id');
    }

    /**
     * An activity could belongs to an Event
     */
    public function event()
    {
        return $this->belongsToObject('Event', 'object_id', 'event_id');
    }

    /**
     * An activity could belongs to an Widget
     */
    public function widget()
    {
        return $this->belongsToObject('Widget', 'object_id', 'widget_id');
    }

    /**
     * Activity has many children.
     *
     */
    public function children()
    {
        return $this->hasMany('Activity', 'parent_id', 'activity_id');
    }

    /**
     * Scope to join with news table with object_type news
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param Illuminate\Database\Query\Builder $builder
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeJoinNews($builder)
    {
        return $builder->addSelect('news.news_name')
                       ->leftJoin('news', function ($join) {
                            $join->on('news.news_id', '=', 'activities.news_id');
                            $join->on('news.object_type', '=', DB::raw('"news"'));
                            $join->on('news.status', '!=', DB::raw('"deleted"'));
                       });
    }

    /**
     * Scope to join with news table with object_type promotion
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param Illuminate\Database\Query\Builder $builder
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeJoinPromotionNews($builder)
    {
        $prefix = DB::getTablePrefix();

        return $builder->addSelect(DB::raw('promotion_news.news_name as promotion_news_name'))
                       ->leftJoin(DB::raw($prefix . 'news promotion_news'), function ($join) {
                            $join->on(DB::raw('promotion_news.news_id'), '=', 'activities.news_id');
                            $join->on(DB::raw('promotion_news.object_type'), '=', DB::raw('"promotion"'));
                            $join->on(DB::raw('promotion_news.status'), '!=', DB::raw('"deleted"'));
                       });
    }

    /**
     * Scope to join with merchants table for tenants data
     *
     * @param Illuminate\Database\Query\Builder $builder
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeJoinRetailer($builder)
    {
        $prefix = DB::getTablePrefix();

        return $builder->addSelect(DB::raw($prefix . 'merchants.name as retailer_name'))
                       ->leftJoin('merchants', function ($join) {
                            $join->on('activities.object_name', '=', DB::raw('"Tenant"'));
                            $join->on('merchants.merchant_id', '=', 'activities.object_id');
                            $join->on('merchants.object_type', '=', DB::raw('"tenant"'));
                            $join->on('merchants.status', '!=', DB::raw('"deleted"'));
                       });
    }

    /**
     * Scope to filter based on merchant ids
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Illuminate\Database\Query\Builder $builder
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeMerchantIds($builder)
    {
        // need to rename this so it does not conflict if used with scopeJoinRetailer
        return $builder->select('activities.*')
                       ->join('merchants as ' . DB::getTablePrefix() .  'malls', 'malls.merchant_id', '=', 'activities.location_id')
                       ->where('malls.status', 'active')
                       ->where('malls.object_type', 'mall');
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function belongsToObject($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if (is_null($foreignKey))
        {
            $foreignKey = snake_case($relation).'_id';
        }

        $instance = new $related;

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $query = $instance->newQuery();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new BelongsToObject($query, $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * Override the save method
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return int
     */
    public function save(array $options = array())
    {
        if ((App::environment() === 'testing') && (Config::get('orbit.activity.force.save', FALSE) !== TRUE)) {
            // Skip saving
            return 1;
        }

        if (empty($this->module_name)) {
            $this->module_name = $this->object_name;

            if (! empty($this->product_name)) {
                $this->module_name = $this->product_name;
            }
            if (! empty($this->coupon_name)) {
                $this->module_name = $this->coupon_name;
            }
            if (! empty($this->promotion_name)) {
                $this->module_name = $this->promotion_name;
            }
            if (! empty($this->event_name)) {
                $this->module_name = $this->event_name;
            }
        }

        // Try to get the session id if this is coming from mobile activity
        if ($this->group === static::ACTIVTY_GROUP_MOBILE) {
            // does the session_id already filled?
            if (! $this->session_id) {
                // try to get the current session id
                $this->session_id = static::getSessionId();
            }

            if ($this->activity_type !== 'logout'){
                $this->saveToConnectedNow();
            }
        }

        $this->setUserLocation();

        $result = parent::save($options);

        // Save to additional activities table
        $this->saveToCampaignPageViews();
        $this->saveToCampaignPopUpView();
        $this->saveToCampaignPopUpClick();
        $this->saveToMerchantPageView();
        $this->saveToWidgetClick();
        $this->saveToConnectionTime();

        if ($this->group === 'mobile-ci') {
            $this->saveToElasticSearch();
        }

        return $result;
    }

    /**
     * Get IP Address of the request.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    protected static function getIPAddress()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Detect the user agent of the request.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    protected static function getUserAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown-UA/?';
    }

    /**
     * Detect request method.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    protected static function getRequestMethod()
    {
        return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'UNKNOWN';
    }

    /**
     * Detect request Uri.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    protected static function getRequestUri()
    {
        return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'Activity: Unknown request Uri';
    }

    /**
     * scope to consider activity from users
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     * @param Illuminate\Database\Query\Builder $builder
     * @param array $merchantIds
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeConsiderCustomer($builder)
    {
        $builder->whereNotIn('group', array('pos', 'portal'));

        if (! empty($merchantIds)) {
            $this->scopeMerchantIds($builder);
        }

        return $builder;
    }

    /**
     * Scope to filter based on merchant ids in widget click
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     * @param Illuminate\Database\Query\Builder $builder
     * @param array $merchantIds
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeMerchantIds_widget_click($builder, array $merchantIds)
    {
        // need to rename this so it does not conflict if used with scopeJoinRetailer
        return $builder->select('activities.*')
                       ->join('widgets', 'widgets.widget_id', '=', 'activities.object_id' )
                       ->join('merchants as ' . DB::getTablePrefix() .  'mall', 'mall.parent_id', '=', 'widgets.merchant_id')
                       ->whereIn('mall.merchant_id', $merchantIds)
                       ->where('mall.status', 'active')
                       ->where('mall.object_type', 'mall');
    }


    /**
     * Set Session id for certain condition
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     * @param string $id
     * @return Activity
     */
    public function setSessionId($id) {
        $this->session_id = $id;

        return $this;
    }

    /**
     *  Set Session static to force session field id
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     * @param $session
     */
    public static function setSession($session) {
        static::$session = $session;
    }

    /**
     * Detect Session Id
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     * @return string
     */
    protected static function getSessionId()
    {
        $session = static::$session;
        if ($session === null) {

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

            $session = new Session($config);
            // There is possibility that the session are already expired
            // So we need to catch those
            try {
                $session = $session->disableForceNew()->start();
            } catch (Exception $e) {
                // do nothing
            }
        }

        return $session->getSessionId();
    }

    /**
     * Save to campaign_page_views table
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return void
     */
    protected function saveToCampaignPageViews()
    {
        if (empty($this->object_id)) {
            return;
        }
        // Save also the activity to particular `campaign_xyz` table
        switch ($this->activity_name) {
            case 'view_promotion':
            case 'view_coupon':
            case 'view_lucky_draw':
            case 'view_event':
            case 'view_news':
                $campaign = new CampaignPageView();
                $campaign->campaign_id = $this->object_id;
                $campaign->user_id = $this->user_id;
                $campaign->location_id = $this->location_id;
                $campaign->activity_id = $this->activity_id;
                $campaign->campaign_group_name_id = $this->campaignGroupNameIdFromActivityName();
                $campaign->save();
                break;
        }
    }

    /**
     * Check, Create and Update Connected Now.
     *
     * @author Irianto <irianto@dominopos.com>
     * @throws Exception
     */
    protected function saveToConnectedNow()
    {
        $date = date('Y-m-d');
        $hour = date('H');
        $minute = date('i');

        $activity = ConnectedNow::select('connected_now.*', 'list_connected_user.user_id')->leftJoin('list_connected_user', function ($join) {
                $join->on('connected_now.connected_now_id', '=', 'list_connected_user.connected_now_id');
                $join->where('list_connected_user.user_id', '=', $this->user_id);
            })
            ->where('merchant_id', '=', $this->location_id)
            ->where('date', '=', $date)
            ->where('hour', '=', $hour)
            ->where('minute', '=', $minute)
            ->first();

        if (empty($activity)) {
            if (! empty($this->location_id)) {
                $newConnected = new ConnectedNow();
                $newConnected->merchant_id = $this->location_id;
                $newConnected->customer_connected = 1;
                $newConnected->date = $date;
                $newConnected->hour = $hour;
                $newConnected->minute = $minute;
                $newConnected->save();

                if (! empty($this->user_id)) {
                    $newListConnectedUser = new ListConnectedUser();
                    $newListConnectedUser->connected_now_id = $newConnected->connected_now_id;
                    $newListConnectedUser->user_id = $this->user_id;
                    $newListConnectedUser->save();
                }
            }
        } else {
            if (is_null($activity->user_id)) {
                if (! empty($this->user_id)) {
                    $newListConnectedUser = new ListConnectedUser();
                    $newListConnectedUser->connected_now_id = $activity->connected_now_id;
                    $newListConnectedUser->user_id = $this->user_id;
                    $newListConnectedUser->save();
                }

                $activity->customer_connected += 1;
                $activity->save();
            }
        }
    }

    /**
     * Save to merchant_page_views table
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return void
     */
    protected function saveToMerchantPageView()
    {
        $proceed = $this->activity_name === 'view_retailer' && $this->activity_name_long == 'View Tenant Detail';
        if (! $proceed) {
            return;
        }

        // Save also the activity to particular `campaign_xyz` table
        $pageview = new MerchantPageView();
        $pageview->merchant_id = $this->object_id;
        $pageview->merchant_type = strtolower($this->object_name);
        $pageview->user_id = $this->user_id;
        $pageview->location_id = $this->location_id;
        $pageview->activity_id = $this->activity_id;
        $pageview->save();
    }

    /**
     * Save to campaign_popup_views table
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @return void
     */
    protected function saveToCampaignPopUpView()
    {
        $activity_name_long_array = array(
            'View Coupon Pop Up'       => 'View Coupon Pop Up',
            'View Promotion Pop Up'    => 'View Promotion Pop Up',
            'View News Pop Up'         => 'View News Pop Up'
        );

        $proceed = in_array($this->activity_name_long, $activity_name_long_array);
        if (! $proceed) {
            return;
        }

        // Save also the activity to particular `campaign_xyz` table
        $popupview = new CampaignPopupView();
        $popupview->campaign_id = $this->object_id;
        $popupview->user_id = $this->user_id;
        $popupview->location_id = $this->location_id;
        $popupview->activity_id = $this->activity_id;
        $popupview->campaign_group_name_id = $this->campaignGroupNameIdFromActivityName();
        $popupview->save();
    }

    /**
     * Save to campaign_popup_views table
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @return void
     */
    protected function saveToCampaignPopUpClick()
    {
        $activity_name_long_array = array(
            'Click Coupon Pop Up'      => 'Click Coupon Pop Up',
            'Click Promotion Pop Up'   => 'Click Promotion Pop Up',
            'Click News Pop Up'        => 'Click News Pop Up',
        );

        $proceed = in_array($this->activity_name_long, $activity_name_long_array);
        if (! $proceed) {
            return;
        }

        // Save also the activity to particular `campaign_xyz` table
        $popupview = new CampaignClicks();
        $popupview->campaign_id = $this->object_id;
        $popupview->user_id = $this->user_id;
        $popupview->location_id = $this->location_id;
        $popupview->activity_id = $this->activity_id;
        $popupview->campaign_group_name_id = $this->campaignGroupNameIdFromActivityName();
        $popupview->save();
    }

    /**
     * Save to `connection_times` table. Only succesful operation (no failed response) recorded.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return void
     */
    protected function saveToConnectionTime()
    {
        $proceed = ($this->activity_name === 'login_ok' || $this->activity_name === 'logout_ok') && $this->session_id;
        if (! $proceed) {
            return;
        }

        // Save also the activity to particular `campaign_xyz` table
        $connection = ConnectionTime::where('session_id', $this->session_id)->where('location_id', $this->location_id)->first();
        if (! is_object($connection)) {
            $connection = new ConnectionTime();
        }

        $connection->session_id = $this->session_id;
        $connection->user_id = $this->user_id;
        $connection->location_id = $this->location_id;

        $now = date('Y-m-d H:i:s');
        if ($this->activity_name === 'login_ok') {
            $connection->login_at = $now;
            $connection->logout_at = NULL;
        }
        if ($this->activity_name === 'logout_ok') {
            $connection->logout_at = $now;
        }

        $connection->save();
    }

    /**
     * Create new document in elasticsearch.
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @author Rio Astamal
     * @return void
     */
    protected function saveToElasticSearch()
    {
        // Normal referer
        $referer = NULL;
        // Orbit Referer (Custom one for AJAX nagivation)
        $orbitReferer = NULL;

        if (isset($_SERVER['HTTP_REFERER']) && ! empty($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
        }

        // Orbit specific referer, this may override above
        if (isset($_SERVER['HTTP_X_ORBIT_REFERER']) && ! empty($_SERVER['HTTP_X_ORBIT_REFERER'])) {
            $orbitReferer = $_SERVER['HTTP_X_ORBIT_REFERER'];
        }

        // queue for create/update activity document in elasticsearch
        Queue::push('Orbit\\Queue\\Elasticsearch\\ESActivityUpdateQueue', [
            'activity_id' => $this->activity_id,
            'referer' => substr($referer, 0, 1024),
            'orbit_referer' => substr($orbitReferer, 0, 1024)
        ]);
    }

    /**
     * Save to `widget_clicks` table
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return void
     */
    protected function saveToWidgetClick()
    {
        if ($this->activity_name !== 'widget_click') {
            return;
        }

        $click = new WidgetClick();
        $click->widget_id = $this->object_id;
        $click->user_id = $this->user_id;
        $click->location_id = $this->location_id;
        $click->activity_id = $this->activity_id;

        $groupName = 'Unknown';
        switch ($this->activity_name_long) {
            case 'Widget Click Promotion':
                $groupName = 'Promotion';
                break;

            case 'Widget Click News':
                $groupName = 'News';
                break;

            case 'Widget Click Tenant':
                $groupName = 'Tenant';
                break;

            case 'Widget Click Service':
                $groupName = 'Service';
                break;

            case 'Widget Click Coupon':
                $groupName = 'Coupon';
                break;

            case 'Widget Click Lucky Draw':
                $groupName = 'Lucky Draw';
                break;

            case 'Widget Click Free Wifi':
                $groupName = 'Free Wifi';
                break;
        }

        $object = WidgetGroupName::get()->keyBy('widget_group_name')->get($groupName);
        $click->widget_group_name_id = is_object($object) ? $object->widget_group_name_id : '0';

        $return = $click->save();
    }

    /**
     * Save the user location
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @return void
     */
    protected function setUserLocation()
    {
        $location = NULL;
        $longitude = NULL;
        $latitude = NULL;

        $userLocationQueryStringName = Config::get('orbit.user_location.query_string.name');
        $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
        $userLocationCookieExpire = Config::get('orbit.user_location.cookie.expire', 3600);

        if (empty($userLocationQueryStringName) || empty($userLocationCookieName)) {
            // missing configuration, do not save
            return;
        }

        // get location from query string
        OrbitInput::get($userLocationQueryStringName, function($userLocationQueryString) use(&$location, &$longitude, &$latitude) {
            $userLocationQueryStringArray = explode('|', $userLocationQueryString);

            if (isset($userLocationQueryStringArray[0]) && isset($userLocationQueryStringArray[1])) {
                $location = $userLocationQueryStringArray;
                $longitude = $userLocationQueryStringArray[0];
                $latitude = $userLocationQueryStringArray[1];
            }
        });

        // use the location from cookie if empty
        if (empty($location)) {
            $userLocationCookieArray = isset($_COOKIE[$userLocationCookieName]) ? explode('|', $_COOKIE[$userLocationCookieName]) : NULL;

            if (! is_null($userLocationCookieArray) && isset($userLocationCookieArray[0]) && isset($userLocationCookieArray[1])) {
                $location = $userLocationCookieArray;
                $longitude = $userLocationCookieArray[0];
                $latitude = $userLocationCookieArray[1];
            }
        }

        // validate longitude value
        if (! preg_match('/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $longitude)) {
            return;
        }

        // validate latitude value
        if (! preg_match('/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/', $latitude)) {
            return;
        }

        setrawcookie($userLocationCookieName, implode('|', $location), time() + $userLocationCookieExpire, '/', Config::get('orbit.shop.main_domain'), FALSE, FALSE);

        $this->longitude = $longitude;
        $this->latitude = $latitude;
    }

    /**
     * Used to get the campaign group name id.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return string
     */
    private function campaignGroupNameIdFromActivityName()
    {
        $groupName = 'Unknown';

        switch ($this->activity_name) {
            case 'view_promotion':
                $groupName = 'Promotion';
                break;

            case 'view_coupon':
                $groupName = 'Coupon';
                break;

            case 'view_lucky_draw':
                $groupName = 'Lucky Draw';
                break;

            case 'view_event':
                $groupName = 'Event';
                break;

            case 'view_news':
                $groupName = 'News';
                break;

            case 'view_promotion_popup':
                $groupName = 'Promotion';
                break;

            case 'view_coupon_popup':
                $groupName = 'Coupon';
                break;

            case 'view_news_popup':
                $groupName = 'News';
                break;

            case 'click_promotion_popup':
                $groupName = 'Promotion';
                break;

            case 'click_coupon_popup':
                $groupName = 'Coupon';
                break;

            case 'click_news_popup':
                $groupName = 'News';
                break;
        }

        $object = CampaignGroupName::get()->keyBy('campaign_group_name')->get($groupName);

        return is_object($object) ? $object->campaign_group_name_id : '0';
    }
}
