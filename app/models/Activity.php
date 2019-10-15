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
use Orbit\Helper\Util\UserAgent;
use Orbit\Helper\MongoDB\Client as MongoClient;

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
    public $fromQueue = false;
    public $currentUrl = '';
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

        if (! is_object($location) && $this->group === 'mobile-ci') {
            $this->location_id = 0;
            $this->location_name = 'gtm';
        }

        return $this;
    }

    /**
     * Set the value of object_display_name
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function setObjectDisplayName($name = NULL)
    {
        $this->object_display_name = $name;

        return $this;
    }

    /**
     * Set the value of object_name manually
     * @author Irianto <irianto@dominopos.com>
     */
    public function setObjectName($name = NULL)
    {
        $this->object_name = $name;

        return $this;
    }

    /**
     * Set the value of object_name manually
     * @author Shelgi <shelgi@dominopos.com>
     */
    public function setObjectId($value = NULL)
    {
        $this->object_id = $value;

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
                    if (strtolower($object->object_type) === 'promotion') {
                        $this->object_name = "Promotion";
                    } elseif (strtolower($object->object_type) === 'pokestop') {
                        $this->object_name = "Pokestop";
                    }
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

                case 'Widget':
                    $widgetGroupName = WidgetGroupName::where('widget_group_name_id', $object->widget_group_name_id)->first();
                    if (is_object($widgetGroupName)) {
                        $this->object_display_name = $widgetGroupName->widget_group_name;
                    }
                    break;

                case 'Category':
                    $this->object_display_name = $object->category_name;
                    break;

                case 'Partner':
                    $this->object_display_name = $object->partner_name;
                    break;

                case 'Advert':
                    $this->object_display_name = $object->advert_name;
                    break;

                case 'Language':
                    $this->object_display_name = $object->name_long;
                    break;

                case 'PaymentProvider':
                    $this->object_display_name = $object->payment_name;
                    break;

                case 'Article':
                    $this->object_display_name = $object->title;
                    break;

                case 'PaymentTransaction':
                    if ($object->payment_method === 'midtrans') {
                        $this->object_display_name = $object->details->count() > 0 ?
                                                        $object->details->first()->object_name :
                                                        'Can not get payment detail record.';

                    }
                    else {
                        $paymentProvider = PaymentProvider::where('payment_provider_id', '=', $object->payment_provider_id)->first();
                        if (is_object($paymentProvider)) {
                            $this->object_display_name = $paymentProvider->payment_name;
                        }
                    }
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
        } else {
            $this->coupon_id = null;
            $this->coupon_name = null;
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
        // skip activity save for crawler bots
        $fallbackUARules = ['browser' => [], 'platform' => [], 'device_model' => [], 'bot_crawler' => []];
        $detectUA = new UserAgent();
        $detectUA->setRules(Config::get('orbit.user_agent_rules', $fallbackUARules));
        $detectUA->setUserAgent($this->user_agent);
        if ($detectUA->isBotCrawler()) {
            return;
        }

        if (Config::get('memory:do_not_save_activity', FALSE)) {
            return;
        }

        $recordActivity = strtolower(OrbitInput::get('record_activity', 'y'));
        if ($recordActivity !== 'y') {
            return;
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
        }

        $notificationToken = OrbitInput::post('notification_token', OrbitInput::get('notification_token', NULL));

        $this->setUserLocation();

        $this->setClickPushNotification();

        $this->notes = $this->removeEmoji($this->notes);

        $result = parent::save($options);

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

        // Save to additional activities table
        $activityQueue = Queue::push('Orbit\\Queue\\Activity\\AdditionalActivityQueue', [
            'activity_id' => $this->activity_id,
            'datetime' => date('Y-m-d H:i:s'),
            'referer' => substr($referer, 0, 2048),
            'orbit_referer' => substr($orbitReferer, 0, 2048),
            'current_url' => ($this->fromQueue) ? $this->currentUrl : Request::fullUrl(),
            'merchant_id' => OrbitInput::post('merchant_id', NULL),
            'notification_token' => $notificationToken
        ]);

        // Format -> JOB_ID;EXTENDED_ACTIVITY_ID;ACTIVITY_ID;MESSAGE
        $dataLog = sprintf("%s;%s;\n", $activityQueue, $this->activity_id);

        // Write the error log to dedicated file so it is easy to investigate and
        // easy to replay because the log is structured
        file_put_contents(storage_path() . '/logs/activity-model.log', $dataLog, FILE_APPEND);

        // Save to object page views table
        Queue::push('Orbit\\Queue\\Activity\\ObjectPageViewActivityQueue', [
            'activity_id' => $this->activity_id
        ], 'object_page_view_prod');

        $this->saveEmailToMailchimp();

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
     * Add the email to subscriber list in the Mailchimp.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @return void
     */
    protected function saveEmailToMailchimp()
    {
        if ($this->activity_name !== 'activation_ok') {
            return;
        }

        Queue::push('Orbit\\Queue\\Mailchimp\\MailchimpSubscriberAddQueue', [
            'activity_id' => $this->activity_id
        ]);
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
     * set click from push notification
     *
     * @author Shelgi <shelgi@dominopos.com>
     * @return void
     */
    protected function setClickPushNotification()
    {
        if ($this->activity_name !== 'click_push_notification') {
            return;
        }

        $notifId = $this->notes;
        if (empty($notifId)) {
            return;
        }

        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);
        $notification = $mongoClient->setEndPoint("notifications/$notifId")->request('GET');

        if (empty($notification->data)) {
            return;
        }

        $this->object_display_name = $notification->data->title;
        $this->object_name = 'Notification';
    }

    protected function removeEmoji($text){
        return preg_replace('/([0-9|#][\x{20E3}])|[\x{00ae}|\x{00a9}|\x{203C}|\x{2047}|\x{2048}|\x{2049}|\x{3030}|\x{303D}|\x{2139}|\x{2122}|\x{3297}|\x{3299}][\x{FE00}-\x{FEFF}]?|[\x{2190}-\x{21FF}][\x{FE00}-\x{FEFF}]?|[\x{2300}-\x{23FF}][\x{FE00}-\x{FEFF}]?|[\x{2460}-\x{24FF}][\x{FE00}-\x{FEFF}]?|[\x{25A0}-\x{25FF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{FE00}-\x{FEFF}]?|[\x{2900}-\x{297F}][\x{FE00}-\x{FEFF}]?|[\x{2B00}-\x{2BF0}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F6FF}][\x{FE00}-\x{FEFF}]?/u', '', $text);
    }

    public function setCurrentUrl($url)
    {
        if (!empty($url) && $url !== '') {
            $this->fromQueue = true;
            $this->currentUrl = $url;
        }

        return $this;
    }
}
