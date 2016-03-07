<?php
/**
 * API to display several dashboard informations
 * Class DashboardAPIController
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use \Carbon\Carbon as Carbon;

class DashboardAPIController extends ControllerAPI
{

    protected $newsViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];
    protected $newsModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];

    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    /**
     * GET - TOP Tenant , Default data is 14 days before today
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `merchant_id`              (optional) - limit by merchant id
     * @param date    `start_date`               (optional) - filter date begin
     * @param date    `end_date`                 (optional) - filter date end
     * @param date    `previous_start_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */

    public function getTopTenant()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.activity.gettopten.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.activity.gettopten.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.activity.gettopten.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.activity.gettopten.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::get('merchant_id');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');
            $previous_start_date = OrbitInput::get('previous_start_date');
            $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));

            $validator = Validator::make(
                array(
                    'merchant_id'         => $merchant_id,
                    'start_date'          => $start_date,
                    'end_date'            => $end_date,
                    'previous_start_date' => $previous_start_date,
                ),
                array(
                    'merchant_id'         => 'orbit.empty.merchant',
                    'start_date'          => 'required|date_format:Y-m-d H:i:s',
                    'end_date'            => 'required|date_format:Y-m-d H:i:s',
                    'previous_start_date' => 'required|date_format:Y-m-d H:i:s',
                )
            );

            Event::fire('orbit.activity.gettopten.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.activity.gettopten.after.validation', array($this, $validator));

            // registrations from start to end grouped by date part and activity name long.
            // activity name long should include source.
            $tablePrefix = DB::getTablePrefix();

            $activities = DB::table('merchant_page_views')
                ->join('merchants', "merchant_page_views.merchant_id", '=', "merchants.merchant_id")
                ->select(
                    DB::raw("{$tablePrefix}merchant_page_views.merchant_id AS tenant_id"),
                    DB::raw("COUNT({$tablePrefix}merchant_page_views.activity_id) AS score"),
                    DB::raw("{$tablePrefix}merchants.name AS tenant_name"),
                    DB::raw("
                            COUNT({$tablePrefix}merchant_page_views.activity_id) / (
                                    SELECT COUNT({$tablePrefix}merchant_page_views.activity_id) FROM {$tablePrefix}merchant_page_views
                                WHERE 1=1
                                AND merchant_type = 'tenant'
                                AND location_id = " . DB::connection()->getPdo()->quote($merchant_id) . "
                                AND DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') >= " . DB::connection()->getPdo()->quote($start_date) . "
                                AND DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') <= " . DB::connection()->getPdo()->quote($end_date) . "
                            )*100 AS percentage
                    ")
                )
                ->where("merchant_page_views.merchant_type", '=', 'tenant')
                ->where("merchant_page_views.location_id", '=', $merchant_id)
                ->where("merchant_page_views.created_at", '>=', $start_date)
                ->where("merchant_page_views.created_at", '<=', $end_date)
                ->groupBy("merchant_page_views.merchant_id")
                ->orderBy('score','desc')
                ->take(10);

            $tenants = $activities->get();

            $this->response->data = [
                'this_period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'tenants' => $tenants,
                ]
            ];
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.activity.gettopten.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.activity.gettopten.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.activity.gettopten.query.error', array($this, $e));

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
            Event::fire('orbit.activity.gettopten.general.exception', array($this, $e));

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
        Event::fire('orbit.activity.gettopten.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Top 5 Customer View
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `merchant_id`   (optional) - mall id
     * @param integer `take`          (optional) - limit the result
     * @param string  `type`          (optional) - type of data : news, events, promotions.
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getTopCustomerView()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettopcustomerview.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettopcustomerview.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettopcustomerview.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            if (! in_array( strtolower($role->role_name), $this->newsViewRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.dashboard.gettopcustomerview.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $take = OrbitInput::get('take');
            $type = OrbitInput::get('type');
            $merchant_id = OrbitInput::get('merchant_id');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $flag_type = false;

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    'take' => $take,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ),
                array(
                    'merchant_id' => 'required|orbit.empty.mall',
                    'take' => 'numeric',
                    'start_date' => 'required',
                    'end_date' => 'required',
                )
            );

            Event::fire('orbit.dashboard.gettopcustomerview.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettopcustomerview.after.validation', array($this, $validator));

            if (empty($take)) {
                $take = 5;
            }

            $tablePrefix = DB::getTablePrefix();
            $quote = function($arg)
            {
                return DB::connection()->getPdo()->quote($arg);
            };

            switch ($type) {

                // show news
                case 'news':
                    $query = News::select(DB::raw("COUNT({$tablePrefix}campaign_page_views.campaign_page_view_id) as score,
                            CASE WHEN {$tablePrefix}news_translations.news_name !='' THEN {$tablePrefix}news_translations.news_name ELSE {$tablePrefix}news.news_name END as name,
                                {$tablePrefix}news.news_id as object_id,
                          count({$tablePrefix}campaign_page_views.campaign_page_view_id) / (select count(cp.campaign_page_view_id)
                                from {$tablePrefix}news ne
                                left join {$tablePrefix}campaign_page_views cp on cp.campaign_id = ne.news_id and ne.object_type = 'News'
                                left join {$tablePrefix}campaign_group_names cgn on cgn.campaign_group_name_id=cp.campaign_group_name_id
                                where cp.location_id = {$quote($merchant_id)} and cp.created_at between {$quote($start_date)} and {$quote($end_date)}) * 100 as percentage")
                            )
                            ->leftJoin('campaign_page_views', function($q) {
                                    $q->on('campaign_page_views.campaign_id', '=', 'news.news_id');
                                    $q->on('news.object_type', '=', DB::raw("'News'"));
                            })
                            ->leftJoin('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                            ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'news_translations.merchant_language_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                            ->where('languages.name', '=', 'en')
                            ->whereBetween('campaign_page_views.created_at', [$start_date, $end_date])
                            ->where('location_id', $merchant_id)
                            ->groupBy('news.news_id')
                            ->orderBy(DB::raw('1'), 'desc')
                            ->take($take);
                        $flag_type = true;
                        break;

                // show events
                case 'events':
                    $query = EventModel::select(DB::raw("COUNT({$tablePrefix}campaign_popup_views.campaign_popup_view_id) as score,
                                        CASE WHEN {$tablePrefix}event_translations.event_name !='' THEN {$tablePrefix}event_translations.event_name ELSE {$tablePrefix}events.event_name END as name,
                                            {$tablePrefix}events.event_id as object_id,
                                        COUNT({$tablePrefix}campaign_popup_views.campaign_popup_view_id) / (select count(cpv.campaign_popup_view_id)
                                        from {$tablePrefix}events ev
                                        left join {$tablePrefix}campaign_popup_views cpv on cpv.campaign_id = ev.event_id
                                        left join {$tablePrefix}campaign_group_names cgn on cgn.campaign_group_name_id = cpv.campaign_group_name_id and cgn.campaign_group_name = 'Event'
                                        where ev.merchant_id = {$quote($merchant_id)} and cpv.created_at between {$quote($start_date)} and {$quote($end_date)}) * 100 as percentage"
                            ))
                            ->leftJoin('campaign_popup_views', 'campaign_popup_views.campaign_id', '=', 'events.event_id')
                            ->leftJoin('campaign_group_names', function($q) use ($quote) {
                                    $q->on('campaign_group_names.campaign_group_name_id', '=', 'campaign_popup_views.campaign_group_name_id');
                                    $q->on('campaign_group_names.campaign_group_name', '=', DB::raw($quote('Event')));
                            })
                            ->leftJoin('event_translations', 'event_translations.event_id', '=', 'events.event_id')
                            ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'event_translations.merchant_language_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                            ->where('languages.name', '=', 'en')
                            ->whereBetween('campaign_popup_views.created_at', [$start_date, $end_date])
                            ->where('events.merchant_id', $merchant_id)
                            ->groupBy('events.event_id')
                            ->orderBy(DB::raw('1'), 'desc')
                            ->take($take);
                        $flag_type = true;
                        break;

                // show promotions
                case 'promotions':
                    $query = News::select(DB::raw("COUNT({$tablePrefix}campaign_page_views.campaign_page_view_id) as score,
                                CASE WHEN {$tablePrefix}news_translations.news_name !='' THEN {$tablePrefix}news_translations.news_name ELSE {$tablePrefix}news.news_name END as name,
                                {$tablePrefix}news.news_id as object_id,
                          count({$tablePrefix}campaign_page_views.campaign_page_view_id) / (select count(cp.campaign_page_view_id)
                                from {$tablePrefix}news ne
                                left join {$tablePrefix}campaign_page_views cp on cp.campaign_id = ne.news_id and ne.object_type = 'Promotion'
                                left join {$tablePrefix}campaign_group_names cgn on cgn.campaign_group_name_id=cp.campaign_group_name_id
                                where cp.location_id = {$quote($merchant_id)} and cp.created_at between {$quote($start_date)} and {$quote($end_date)}) * 100 as percentage")
                            )
                            ->leftJoin('campaign_page_views', function($q) {
                                    $q->on('campaign_page_views.campaign_id', '=', 'news.news_id');
                                    $q->on('news.object_type', '=', DB::raw("'Promotion'"));
                            })
                            ->leftJoin('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                            ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'news_translations.merchant_language_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                            ->where('languages.name', '=', 'en')
                            ->whereBetween('campaign_page_views.created_at', [$start_date, $end_date])
                            ->where('location_id', $merchant_id)
                            ->groupBy('news.news_id')
                            ->orderBy(DB::raw('1'), 'desc')
                            ->take($take);
                    $flag_type = true;
                    break;

                // show luckydraws
                case 'lucky_draws':
                    $query = LuckyDraw::select(DB::raw("COUNT({$tablePrefix}campaign_page_views.campaign_page_view_id) as score,
                                    CASE WHEN {$tablePrefix}lucky_draw_translations.lucky_draw_name !='' THEN {$tablePrefix}lucky_draw_translations.lucky_draw_name ELSE {$tablePrefix}lucky_draws.lucky_draw_name END as name,
                                    {$tablePrefix}lucky_draws.lucky_draw_id as object_id,
                                count({$tablePrefix}campaign_page_views.campaign_page_view_id) / (select count(cp.campaign_page_view_id)
                                from {$tablePrefix}lucky_draws ld
                                left join {$tablePrefix}campaign_page_views cp on cp.campaign_id = ld.lucky_draw_id
                                left join {$tablePrefix}campaign_group_names cgn on cgn.campaign_group_name_id=cp.campaign_group_name_id
                                where cp.location_id = {$quote($merchant_id)} and cp.created_at between {$quote($start_date)} and {$quote($end_date)}) * 100 as percentage")
                            )
                            ->leftJoin('campaign_page_views', 'campaign_page_views.campaign_id', '=', 'lucky_draws.lucky_draw_id')
                            ->leftJoin('campaign_group_names', function($q) use ($quote) {
                                    $q->on('campaign_group_names.campaign_group_name_id', '=', 'campaign_page_views.campaign_group_name_id');
                                    $q->on('campaign_group_names.campaign_group_name', '=', DB::raw($quote('Lucky Draw')));
                            })
                            ->leftJoin('lucky_draw_translations', 'lucky_draw_translations.lucky_draw_id', '=', 'lucky_draws.lucky_draw_id')
                            ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'lucky_draw_translations.merchant_language_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                            ->where('languages.name', '=', 'en')
                            ->whereBetween('campaign_page_views.created_at', [$start_date, $end_date])
                            ->where('lucky_draws.mall_id', $merchant_id)
                            ->groupBy('lucky_draws.lucky_draw_id')
                            ->orderBy(DB::raw('1'), 'desc')
                            ->take($take);
                        $flag_type = true;
                        break;

                // show coupon
                case 'coupons':
                    $query = Coupon::select(DB::raw("COUNT({$tablePrefix}campaign_page_views.campaign_page_view_id) as score,
                                    CASE WHEN {$tablePrefix}coupon_translations.promotion_name !='' THEN {$tablePrefix}coupon_translations.promotion_name ELSE {$tablePrefix}promotions.promotion_name END as name,
                                    {$tablePrefix}promotions.promotion_id as object_id,
                                count({$tablePrefix}campaign_page_views.campaign_page_view_id) / (select count(cp.campaign_page_view_id)
                                from {$tablePrefix}promotions pr
                                left join {$tablePrefix}campaign_page_views cp on cp.campaign_id = pr.promotion_id and pr.is_coupon = 'Y'
                                left join {$tablePrefix}campaign_group_names cgn on cgn.campaign_group_name_id=cp.campaign_group_name_id
                                where cp.location_id = {$quote($merchant_id)} and cp.created_at between {$quote($start_date)} and {$quote($end_date)}) * 100 as percentage")
                            )
                            ->leftJoin('campaign_page_views', 'campaign_page_views.campaign_id', '=', 'promotions.promotion_id')
                            ->leftJoin('campaign_group_names', function($q) use ($quote) {
                                    $q->on('campaign_group_names.campaign_group_name_id', '=', 'campaign_page_views.campaign_group_name_id');
                                    $q->on('campaign_group_names.campaign_group_name', '=', DB::raw($quote('Coupon')));
                            })
                            ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'coupon_translations.merchant_language_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                            ->where('languages.name', '=', 'en')
                            ->whereBetween('campaign_page_views.created_at', [$start_date, $end_date])
                            ->where('promotions.merchant_id', $merchant_id)
                            ->groupBy('promotions.promotion_id')
                            ->orderBy(DB::raw('1'), 'desc')
                            ->take($take);
                        $flag_type = true;
                        break;

                // by default do nothing
                default:
                     $query = null;
                     $flag_type = false;
            }

            if ($flag_type) {
                $result = $query->get();
            } else {
                $result = null;
            }

            if (empty($result)) {
                $this->response->message = Lang::get('statuses.orbit.nodata.object');
            }

            $this->response->data = $result;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettopcustomerview.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettopcustomerview.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettopcustomerview.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.gettopcustomerview.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettopcustomerview.before.render', array($this, &$output));

        return $output;
    }
    /**
     * GET - General Customer View
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `merchant_id`   (optional) - mall id
     * @param integer `take`          (optional) - limit the result
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getGeneralCustomerView()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getgeneralcustomerview.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getgeneralcustomerview.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getgeneralcustomerview.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            if (! in_array( strtolower($role->role_name), $this->newsViewRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.dashboard.getgeneralcustomerview.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.getgeneralcustomerview.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getgeneralcustomerview.after.validation', array($this, $validator));


            $tablePrefix = DB::getTablePrefix();
            $merchantId = OrbitInput::get('merchant_id', 0);

            $defaultBeginDate = date('Y-m-d 00:00:00', strtotime('-14 days'));
            $beginDate = OrbitInput::get('start_date', $defaultBeginDate);

            $defaultEndDate = date('Y-m-d 23:59:59', strtotime('tomorrow'));
            $endDate = OrbitInput::get('end_date', $defaultEndDate);

            // This is for event popup views, because event has no page view
            $event = CampaignGroupName::getPopupViewByLocation($merchantId, $beginDate, $endDate)
                                        ->get()
                                        ->keyBy('campaign_group_name')
                                        ->get('Event');

            // This is for another campaign
            $campaigns = CampaignGroupName::getPageViewByLocation($merchantId, $beginDate, $endDate)->get();

            $keys = [
                'Coupon' => 'coupons',
                'Event' => 'events',
                'Lucky Draw' => 'lucky_draws',
                'News' => 'news',
                'Promotion' => 'promotions'
            ];

            $objectKeys = [];
            foreach ($campaigns as $campaign) {
                if ($campaign->campaign_group_name === 'Event') {
                    continue;
                }

                $tmp = new stdClass();
                $tmp->label = $campaign->campaign_group_name;
                $tmp->total = $campaign->count;

                $theKey = $keys[$tmp->label];
                $objectKeys[$theKey] = $tmp;
            }
            $objectKeys['events'] = new stdClass();
            $objectKeys['events']->label = 'Event';
            $objectKeys['events']->total = $event->count;

            $data = new stdclass();
            $data->news = $objectKeys['news'];
            $data->events = $objectKeys['events'];
            $data->promotions = $objectKeys['promotions'];
            $data->lucky_draws = $objectKeys['lucky_draws'];
            $data->coupons = $objectKeys['coupons'];

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getgeneralcustomerview.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getgeneralcustomerview.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getgeneralcustomerview.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getgeneralcustomerview.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getgeneralcustomerview.before.render', array($this, &$output));

        return $output;
    }



    /**
     * GET - The detail of each item on top 5 Customer View
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `merchant_id`   (optional) - mall id
     * @param string  `type`          (optional) - type of data : news, events, promotions.
     * @param string  `object_id`     (optional) - id of news,events or promotion.
     * @param json    `periods`       (optional) - list of start date and end date
     * @param date    `start_date`    (optional) - filter start date
     * @param date    `end_date`      (optional) - filter end date
     * @return Illuminate\Support\Facades\Response
     */
    public function getDetailTopCustomerView()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getdetailtopcustomerview.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getdetailtopcustomerview.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getdetailtopcustomerview.before.auth', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getdetailtopcustomerview.auth.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.dashboard.getdetailtopcustomerview.after.auth', array($this, $user));

            $this->registerCustomValidation();

            // requesting single period
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');
            $merchant_id = OrbitInput::get('merchant_id');
            $type = OrbitInput::get('type');
            $object_id = OrbitInput::get('object_id');

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                    'type'          => $type,
                    'object_id'     => $object_id,
                    'start_date'    => $start_date,
                    'end_date'      => $end_date,
                ),
                array(
                    'merchant_id'  => 'required',
                    'type'         => 'required|in:news,promotions,lucky_draws,events,coupons',
                    'object_id'    => 'required',
                    'start_date'   => 'required|date_format:Y-m-d H:i:s',
                    'end_date'     => 'required|date_format:Y-m-d H:i:s'
                )
            );

            Event::fire('orbit.dashboard.getdetailtopcustomerview.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getdetailtopcustomerview.after.validation', array($this, $validator));

            $tablePrefix = DB::getTablePrefix();

            if ($type === 'news' || $type === 'promotions' || $type === 'lucky_draws' || $type === 'coupons') {
                switch ($type) {
                    case 'news':
                        $campaign_group_name = 'News';
                        break;
                    case 'promotions':
                        $campaign_group_name = 'Promotion';
                        break;
                    case 'lucky_draws':
                        $campaign_group_name = 'Lucky Draw';
                        break;
                    case 'coupons':
                        $campaign_group_name = 'Coupon';
                        break;
                }
                $tableName = 'campaign_page_views';
            } elseif ($type === 'events') {
                $campaign_group_name = 'Event';
                $tableName = 'campaign_popup_views';
            }

            $quote = function($arg)
            {
                return DB::connection()->getPdo()->quote($arg);
            };

            $date_diff = Carbon::parse($start_date)->diff(Carbon::parse($end_date)->addMinute())->days;
            $start_date_minus_one_hour = Carbon::parse($start_date)->subHour();

            switch ($date_diff) {
                case 1:
                    $interval = 3;
                    break;
                case 2:
                    $interval = 6;
                    break;
                case 3:
                    $interval = 6;
                    break;
                case 4:
                    $interval = 8;
                    break;
                case 5:
                    $interval = 12;
                    break;
                case 6:
                    $interval = 12;
                    break;
                case 7:
                    $interval = 12;
                    break;
                default:
                    $interval = 24;
                    break;
            }

            // Thomas sequence query
            $results = DB::select(DB::raw("
                    SELECT
                        p1.start_date,
                        p1.end_date,
                        SUM(IFNULL(p2.count_per_hour, 0)) AS score
                    FROM
                        (SELECT
                            IF(MOD(@running_id, {$interval}) <> 0, @grp_id := @grp_id, @grp_id := @grp_id + 1) AS grp_id,
                            (@running_id := @running_id + 1) AS running_id,
                            DATE_FORMAT(DATE_ADD('{$start_date_minus_one_hour}', INTERVAL sequence_number HOUR), '%Y-%m-%d %H:00:00') AS start_date,
                            DATE_FORMAT(DATE_ADD('{$start_date_minus_one_hour}', INTERVAL sequence_number+{$interval}-1 HOUR), '%Y-%m-%d %H:59:59') as end_date
                        FROM
                            (SELECT @running_id := 0, @grp_id := 0) AS init_q,
                            {$tablePrefix}sequence ts
                        WHERE
                            ts.sequence_number <= ({$date_diff} * 24)
                        ) AS p1
                    LEFT JOIN
                        (
                            SELECT
                                ocpv.campaign_id,
                                DATE_FORMAT(ocpv.created_at, '%Y-%m-%d %H:00:00') AS view_date,
                                COUNT(DATE_FORMAT(ocpv.created_at, '%Y-%m-%d %H:00:00')) AS count_per_hour
                            FROM {$tablePrefix}{$tableName} ocpv
                            LEFT JOIN {$tablePrefix}campaign_group_names ocgn ON ocgn.campaign_group_name_id = ocpv.campaign_group_name_id
                            WHERE
                                ocpv.created_at >= {$quote($start_date)}
                                AND ocpv.location_id = {$quote($merchant_id)}
                                AND ocpv.campaign_id = {$quote($object_id)}
                                AND ocgn.campaign_group_name = '{$campaign_group_name}'
                            GROUP BY view_date
                            ORDER BY view_date
                        ) AS p2
                    ON p1.start_date = p2.view_date
                    GROUP BY p1.grp_id
                    ORDER BY p1.start_date;
                "));

            $this->response->data = $results;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getdetailtopcustomerview.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getdetailtopcustomerview.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getdetailtopcustomerview.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getdetailtopcustomerview.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getdetailtopcustomerview.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - The expiring campaign (news, promotion, coupon)
     *
     * @author firmansyah <firmansyah@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `merchant_id`   (optional) - mall id
     * @param string  `now_date`      (optional) - now_date of mall
     * @return Illuminate\Support\Facades\Response
     */
    public function getExpiringCampaign()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getexpiringcampaign.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getexpiringcampaign.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getexpiringcampaign.before.authz', array($this, $user));

            // if (! ACL::create($user)->isAllowed('view_product')) {
            //     Event::fire('orbit.dashboard.getexpiringcampaign.authz.notallowed', array($this, $user));
            //     $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
            //     $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
            //     ACL::throwAccessForbidden($message);
            // }

            $role = $user->role;
            $validRoles = $this->newsViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.dashboard.getexpiringcampaign.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $current_mall = OrbitInput::get('current_mall');
            $now_date = OrbitInput::get('now_date');
            $take = OrbitInput::get('take');

            $validator = Validator::make(
                array(
                    'current_mall' => $current_mall,
                    'now_date' => $now_date
                ),
                array(
                    'current_mall' => 'required | orbit.empty.merchant',
                    'now_date' => 'required | date_format:Y-m-d H:i:s'
                )
            );

            Event::fire('orbit.dashboard.getexpiringcampaign.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.dashboard.getexpiringcampaign.after.validation', array($this, $validator));

            $tablePrefix = DB::getTablePrefix();

            $newsAndPromotions = DB::table('news')
                ->selectRaw("{$tablePrefix}news.news_id campaign_id,
                    CASE WHEN {$tablePrefix}news_translations.news_name !='' THEN {$tablePrefix}news_translations.news_name ELSE {$tablePrefix}news.news_name END as campaign_name,
                    DATEDIFF(end_date, {$this->quote($now_date)}) expire_days, object_type type,
                    CASE WHEN {$tablePrefix}news.end_date < {$this->quote($now_date)} THEN 'expired' ELSE {$tablePrefix}campaign_status.campaign_status_name END  AS campaign_status")
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                // Join translation for get english name
                ->leftJoin('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'news_translations.merchant_language_id')
                ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                ->where('languages.name', '=', 'en')
                ->where('end_date', '>', $now_date)
                ->where('mall_id', $current_mall)
                ->orderBy('expire_days','asc');

            $coupons = DB::table('promotions')
                ->selectRaw("{$tablePrefix}promotions.promotion_id campaign_id,
                    CASE WHEN {$tablePrefix}coupon_translations.promotion_name !='' THEN {$tablePrefix}coupon_translations.promotion_name ELSE {$tablePrefix}promotions.promotion_name END as campaign_name,
                    DATEDIFF(end_date, {$this->quote($now_date)}) expire_days, IF(is_coupon = 'Y','coupon', '') type,
                    CASE WHEN {$tablePrefix}promotions.end_date < {$this->quote($now_date)} THEN 'expired' ELSE {$tablePrefix}campaign_status.campaign_status_name END AS campaign_status")
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                // Join translation for get english name
                ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'coupon_translations.merchant_language_id')
                ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                ->where('languages.name', '=', 'en')
                ->where('is_coupon', '=', 'Y')
                ->where('end_date', '>', $now_date)
                ->where('promotions.merchant_id', $current_mall)
                ->orderBy('expire_days','asc');

            $expiringCampaign = $newsAndPromotions->unionAll($coupons);

            $sql = $expiringCampaign->toSql();
            foreach($expiringCampaign->getBindings() as $binding)
            {
              $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
              $sql = preg_replace('/\?/', $value, $sql, 1);
            }

            // Make union result subquery so that data can be ordering
            $expiringCampaign = DB::table(DB::raw('(' . $sql . ') as a'))
                ->whereNotIn('campaign_status', array('stopped', 'expired'))
                ->orderBy('expire_days','asc')
                ->take(10)
                ->get();

            $this->response->data = $expiringCampaign;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getexpiringcampaign.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getexpiringcampaign.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getexpiringcampaign.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getexpiringcampaign.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getexpiringcampaign.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP Product
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `skip`          (optional) - paging skip for limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param integer `merchant_id`   (optional) - limit by merchant id
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getTopProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettopproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettopproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettopproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.gettopproduct.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.gettopproduct.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $merchantId = OrbitInput::get('merchant_id');
            $validator = Validator::make(
                array(
                    'merchant_id' => $merchantId,
                    'take' => $take
                ),
                array(
                    'merchant_id' => 'required|array|min:0',
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.gettopproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettopproduct.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dashboard.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dashboard.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            $products = Product::select(
                            "products.product_id",
                            "products.product_code",
                            "products.product_name",
                            DB::raw("count(distinct {$tablePrefix}activities.activity_id) as view_count")
                        )
                        ->join("activities", function ($join) {
                            $join->on('products.product_id', '=', 'activities.product_id');
                            $join->where('activities.activity_name', '=', 'view_product');
                        })
                        ->groupBy('products.product_id');

            OrbitInput::get('merchant_id', function ($merchantId) use ($products) {
               $products->whereIn('products.merchant_id', $this->getArray($merchantId));
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($products) {
               $products->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($products) {
               $products->where('activities.created_at', '<=', $endDate);
            });

            $isReport = $this->builderOnly;
            $topNames = clone $products;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $products, $tablePrefix) {
                $isReport = !!$_isReport;
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_products = clone $products;

            $products->orderBy('view_count', 'desc');

            // Get the take args
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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $summary  = NULL;
            $lastPage = false;
            if ($isReport)
            {
                $_products->addSelect(
                    DB::raw("date({$tablePrefix}activities.created_at) as created_at_date")
                );
                $_products->groupBy('created_at_date');

                $productNames = $topNames
                    ->orderBy('view_count', 'desc')
                    ->orderBy('product_name', 'asc')
                    ->take(20)
                    ->get();
                $defaultSelect = [];
                $productIds    = [];
                $productTotal = 0;
                $productList  = [];

                foreach ($productNames as $product)
                {
                    array_push($productIds, $product->product_id);
                    if ($this->builderOnly) {
                        $name = $product->product_id;
                    } else {
                        $name = htmlspecialchars($product->product_name, ENT_QUOTES);
                    }
                    array_push($defaultSelect, DB::raw("ifnull(sum(case product_id when {$product->product_id} then view_count end), 0) as '{$name}'"));
                }

                if (count($productIds) > 0) {
                    $toSelect  = array_merge($defaultSelect, ['created_at_date']);

                    $_products->whereIn('activities.product_id', $productIds);

                    $productReportQuery = $_products->getQuery();
                    $productReport = DB::table(DB::raw("({$_products->toSql()}) as report"))
                        ->mergeBindings($productReportQuery)
                        ->select($toSelect)
                        ->whereIn('product_id', $productIds)
                        ->groupBy('created_at_date');
                    $summaryReport = DB::table(DB::raw("({$_products->toSql()}) as report"))
                        ->mergeBindings($productReportQuery)
                        ->select($defaultSelect);

                    $_productReport = clone $productReport;

                    $productReport->orderBy('created_at_date', 'desc');

                    if ($this->builderOnly)
                    {
                        return $this->builderObject($productReport, $summaryReport, [
                            'productNames' => $productNames
                        ]);
                    }

                    $productReport->take($take)->skip($skip);

                    $totalReport    = DB::table(DB::raw("({$_productReport->toSql()}) as total_report"))
                        ->mergeBindings($_productReport);

                    $productTotal = $totalReport->count();
                    $productList  = $productReport->get();

                    if (($productTotal - $take) <= $skip)
                    {
                        $summary  = $summaryReport->first();
                        $lastPage = true;
                    }
                } else {
                    if ($this->builderOnly) {
                        return $this->builderObject(NULL, NULL);
                    }
                }
            } else {
                $products->take(20);
                $productTotal = RecordCounter::create($_products)->count();
                $productList = $products->get();
            }

            $data = new stdclass();
            $data->total_records = $productTotal;
            $data->returned_records = count($productList);
            $data->last_page = $lastPage;
            $data->summary   = $summary;
            $data->records   = $productList;

            if ($productTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettopproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettopproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettopproduct.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.gettopproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettopproduct.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP Product Family
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `skip`          (optional) - paging skip for limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param integer `merchant_id`   (optional) - limit by merchant id
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getTopProductFamily()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettopproductfamily.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettopproductfamily.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettopproductfamily.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.gettopproductfamily.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.gettopproductfamily.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $merchantId = OrbitInput::get('merchant_id');
            $validator = Validator::make(
                array(
                    'merchant_id' => $merchantId,
                    'take' => $take
                ),
                array(
                    'merchant_id' => 'required|array|min:0',
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.gettopproductfamily.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettopproductfamily.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dashboard.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dashboard.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            $merchantIds = OrbitInput::get('merchant_id', []);

            $categories = Activity::considerCustomer($merchantIds)->select(
                    "categories.category_level",
                    DB::raw("count(distinct {$tablePrefix}activities.activity_id) as view_count")
                )
                ->join("categories", function ($join) {
                    $join->on('activities.object_id', '=', 'categories.category_id');
                    $join->where('activities.activity_name', '=', 'view_catalogue');
                    $join->where('activities.object_name', '=', 'Category');
                })
                ->groupBy('categories.category_level');

            $isReport = $this->builderOnly;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $categories, $tablePrefix) {
                $isReport = !!$_isReport;
            });

            OrbitInput::get('merchant_id', function ($merchantId) use ($categories) {
                $categories->whereIn('categories.merchant_id', $this->getArray($merchantId));
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($categories) {
                $categories->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($categories) {
                $categories->where('activities.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_categories = clone $categories;

            $categories->orderBy('view_count', 'desc');

            // Get the take args
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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $summary  = NULL;
            $lastPage = false;
            if ($isReport)
            {
                $_categories->addSelect(
                    DB::raw("date({$tablePrefix}activities.created_at) as created_at_date")
                );
                $_categories->groupBy('created_at_date');

                $defaultSelect = [
                    DB::raw("ifnull(sum(case category_level when 1 then view_count end), 0) as '1'"),
                    DB::raw("ifnull(sum(case category_level when 2 then view_count end), 0) as '2'"),
                    DB::raw("ifnull(sum(case category_level when 3 then view_count end), 0) as '3'"),
                    DB::raw("ifnull(sum(case category_level when 4 then view_count end), 0) as '4'"),
                    DB::raw("ifnull(sum(case category_level when 5 then view_count end), 0) as '5'"),
                    DB::raw("ifnull(sum(view_count), 0) as total")
                ];
                $toSelect = array_merge($defaultSelect, ['created_at_date']);

                $categoryReportQuery = $_categories->getQuery();
                $categoryReport = DB::table(DB::raw("({$_categories->toSql()}) as report"))
                    ->mergeBindings($categoryReportQuery)
                    ->select($toSelect)
                    ->groupBy('created_at_date');

                $summaryReport = DB::table(DB::raw("({$_categories->toSql()}) as report"))
                    ->mergeBindings($categoryReportQuery)
                    ->select($defaultSelect);

                $_categoryReport = clone $categoryReport;

                $categoryReport->orderBy('created_at_date', 'desc');

                if ($this->builderOnly)
                {
                    return $this->builderObject($categoryReport, $summaryReport);
                }

                $categoryReport->take($take)->skip($skip);

                $totalReport = DB::table(DB::raw("({$_categoryReport->toSql()}) as total_report"))
                    ->mergeBindings($_categoryReport);

                $categoryList  = $categoryReport->get();
                $categoryTotal = $totalReport->count();

                if (($categoryTotal - $take) <= $skip)
                {
                    $summary  = $summaryReport->first();
                    $lastPage = true;
                }
            } else {
                $categories->take($take);
                $categoryTotal = RecordCounter::create($_categories)->count();
                $categoryList = $categories->get();
                $summary = null;
            }

            $data = new stdclass();
            $data->total_records = $categoryTotal;
            $data->returned_records = count($categoryList);
            $data->last_page = $lastPage;
            $data->summary   = $summary;
            $data->records   = $categoryList;

            if ($categoryTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettopproductfamily.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettopproductfamily.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettopproductfamily.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.gettopproductfamily.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettopproductfamily.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP Widget Click
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `skip`          (optional) - paging skip for limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param integer `merchant_id`   (optional) - limit by merchant id
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getTopWidgetClick()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettopwidgetclick.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettopwidgetclick.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettopwidgetclick.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.gettopwidgetclick.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.gettopwidgetclick.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $merchantId = OrbitInput::get('merchant_id');
            $validator = Validator::make(
                array(
                    'merchant_id' => $merchantId,
                    'take' => $take
                ),
                array(
                    'merchant_id' => 'array|min:0',
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.gettopwidgetclick.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettopwidgetclick.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dashboard.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dashboard.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();
            $merchantId = OrbitInput::get('merchant_id', '0');

            $defaultBeginDate = date('Y-m-d 00:00:00');
            $defaultEndDate = date('Y-m-d 23:59:59');
            $beginDate = OrbitInput::get('begin_date', $defaultBeginDate);
            $endDate = OrbitInput::get('end_date', $defaultEndDate);

            $quote = function($arg)
            {
                return DB::connection()->getPdo()->quote($arg);
            };

            $widgets = WidgetGroupName::select('widget_group_names.widget_group_name as widget_type', DB::raw("count({$tablePrefix}widget_clicks.widget_id) as click_count"))
                        ->leftJoin('widget_clicks', function($join) use ($beginDate, $endDate, $merchantId, $quote, $tablePrefix) {
                            // We put the condition on join so the "left" table can appear as is and not filtered
                            $join->on('widget_clicks.widget_group_name_id', '=', 'widget_group_names.widget_group_name_id');
                            $join->on('widget_clicks.location_id', '=', DB::raw($quote( end($merchantId) )));
                            $join->on("widget_clicks.created_at", 'between', DB::raw("{$quote($beginDate)} and {$quote($endDate)}"));
                        })
                        ->groupBy('widget_group_names.widget_group_name_id');

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_widgets = clone $widgets;
            $_widgets->select('widget_group_names.widget_group_name_id');

            $widgets->orderBy('click_count', 'desc');

            // Get the take args
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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $widgets->take($take);
            $widgetTotal = RecordCounter::create($_widgets)->count();
            $widgetList  = $widgets->get();

            $data = new stdclass();
            $data->total_records = $widgetTotal;
            $data->returned_records = count($widgetList);
            $data->records   = $widgetList;

            if ($widgetTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.widget');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettopwidgetclick.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettopwidgetclick.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettopwidgetclick.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.gettopwidgetclick.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettopwidgetclick.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP User By Date
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `skip`          (optional) - paging skip for limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserLoginByDate()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getuserloginbydate.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getuserloginbydate.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getuserloginbydate.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getuserloginbydate.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.getuserloginbydate.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.getuserloginbydate.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getuserloginbydate.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dashboard.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dashboard.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            $merchantIds = OrbitInput::get('merchant_id', []);

            $users = Activity::considerCustomer($merchantIds)->select(
                    DB::raw("ifnull(date({$tablePrefix}activities.created_at), date({$tablePrefix}users.created_at)) as last_login"),
                    DB::raw("count(distinct {$tablePrefix}users.user_id) as user_count"),
                    DB::raw("(count(distinct {$tablePrefix}users.user_id) - count(distinct new_users.user_id)) as returning_user_count"),
                    DB::raw("count(distinct new_users.user_id) as new_user_count")
                )
                ->where(function ($jq) {
                    $jq->where('activities.activity_name', '=', 'login_ok');
                    $jq->orWhere('activities.activity_name', '=', 'registration_ok');
                })
                ->leftJoin('users', function ($join) {
                    $join->on('activities.user_id', '=', 'users.user_id');
                })
                ->leftJoin("users as new_users", function ($join) use ($tablePrefix) {
                    $join->on(DB::raw("new_users.user_id"), '=', 'users.user_id');
                    $join->on(DB::raw("date(new_users.created_at)"), '>=', DB::raw("ifnull(date({$tablePrefix}activities.created_at), date({$tablePrefix}users.created_at))"));
                })
                ->groupBy('last_login');

            $isReport = $this->builderOnly;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $users, $tablePrefix) {
                $isReport = !!$_isReport;
            });


            OrbitInput::get('begin_date', function ($beginDate) use ($users) {
                $users->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($users) {
                $users->where('activities.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_users = clone $users;

            $users->orderBy('last_login', 'desc');

            // Get the take args
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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $summary   = NULL;
            $lastPage  = false;
            $userTotal = RecordCounter::create($_users)->count();
            if ($isReport)
            {
                $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as total_report"))
                    ->mergeBindings($_users->getQuery())
                    ->select(
                        DB::raw("sum(new_user_count) as new_user_count"),
                        DB::raw("sum(returning_user_count) as returning_user_count"),
                        DB::raw("sum(user_count) as user_count")
                    );

                if ($this->builderOnly)
                {
                    return $this->builderObject($users, $summaryReport);
                }

                $users->take($take)
                    ->skip($skip);
                $userList  = $users->get();

                // Consider Last Page
                if (($userTotal - $take) <= $skip)
                {
                    $summary   = $summaryReport->first();
                    $lastPage = true;
                }
            } else {
                $users->take($take);
                $userList  = $users->get();
            }

            $data = new stdclass();
            $data->total_records = $userTotal;
            $data->returned_records = count($userList);
            $data->last_page = $lastPage;
            $data->summary   = $summary;
            $data->records   = $userList;

            if ($userTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getuserloginbydate.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getuserloginbydate.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getuserloginbydate.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getuserloginbydate.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getuserloginbydate.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP User By Gender
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `skip`          (optional) - paging skip for limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserByGender()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getuserbygender.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getuserbygender.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getuserbygender.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getuserbygender.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.getuserbygender.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.getuserbygender.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getuserbygender.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dashboard.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dashboard.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            $merchantIds = OrbitInput::get('merchant_id', []);

            $users = Activity::considerCustomer($merchantIds)->select(
                    DB::raw("(
                        case {$tablePrefix}details.gender
                            when 'f' then 'Female'
                            when 'm' then 'Male'
                            else 'Unknown'
                        end
                    ) as user_gender"),
                    DB::raw("count(distinct {$tablePrefix}activities.user_id) as user_count"),
                    DB::raw("date({$tablePrefix}activities.created_at) created_at_date")
                )
                ->where(function ($jq) {
                    $jq->where('activity_name', '=', 'login_ok');
                    $jq->orWhere('activity_name', '=', 'registration_ok');
                })
                ->leftJoin("user_details as {$tablePrefix}details", function ($join) {
                    $join->on('details.user_id', '=', 'activities.user_id');
                })
                ->groupBy('details.gender', 'created_at_date');

            $isReport = $this->builderOnly;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $users, $tablePrefix) {
                $isReport = !!$_isReport;
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($users) {
                $users->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($users) {
                $users->where('activities.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_users = clone $users;

            $users->orderBy('user_count', 'desc');

            // Get the take args
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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $defaultSelect = [
                DB::raw("ifnull(sum(case user_gender when 'Male' then user_count end), 0) as 'Male'"),
                DB::raw("ifnull(sum(case user_gender when 'Female' then user_count end), 0) as 'Female'"),
                DB::raw("ifnull(sum(case user_gender when 'Unknown' then user_count end), 0) as 'Unknown'"),
                DB::raw("ifnull(sum(user_count), 0) as 'total'")
            ];

            $summary   = NULL;
            $lastPage  = false;
            if ($isReport)
            {
                $toSelect = array_merge($defaultSelect, [
                    DB::raw("created_at_date")
                ]);

                $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                    ->mergeBindings($_users->getQuery())
                    ->select($defaultSelect);

                $userReportQuery = $_users->getQuery();
                $userReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                    ->mergeBindings($userReportQuery)
                    ->select($toSelect)
                    ->groupBy('created_at_date');

                $_userReport = clone $userReport;

                $userReport->orderBy('created_at_date', 'desc');

                if ($this->builderOnly)
                {
                    return $this->builderObject($userReport, $summaryReport);
                }

                $userReport->take($take)->skip($skip);

                $totalReport = DB::table(DB::raw("({$_userReport->toSql()}) as total_report"))
                    ->mergeBindings($_userReport);

                $userList  = $userReport->get();
                $userTotal = $totalReport->count();

                if (($userTotal - $take) <= $skip)
                {
                    $summary   = $summaryReport->first();
                    $lastPage  = true;
                }
            } else {
                $users->take($take);
                $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                    ->mergeBindings($_users->getQuery())
                    ->select($defaultSelect);
                $summary   = $summaryReport->first();
                $userTotal = RecordCounter::create($_users)->count();
                $userList  = $users->get();
            }

            $data = new stdclass();
            $data->total_records = $userTotal;
            $data->returned_records = count($userList);
            $data->summary   = static::calculateSummaryPercentage($summary);
            $data->last_page = $lastPage;
            $data->records   = $userList;

            if ($userTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getuserbygender.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getuserbygender.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getuserbygender.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getuserbygender.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getuserbygender.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP User By Age
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `skip`          (optional) - paging skip for limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserByAge()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getuserbyage.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getuserbyage.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getuserbyage.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getuserbyage.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.getuserbyage.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.getuserbyage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getuserbyage.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dashboard.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dashboard.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            $calculateAge = "(date_format(now(), '%Y') - date_format({$tablePrefix}details.birthdate, '%Y') -
                    (date_format(now(), '00-%m-%d') < date_format({$tablePrefix}details.birthdate, '00-%m-%d')))";

            $merchantIds = OrbitInput::get('merchant_id', []);

            $users = Activity::considerCustomer($merchantIds)->select(
                    DB::raw("(
                        case
                            when {$calculateAge} < 15 then 'Unknown'
                            when {$calculateAge} < 20 then '15-20'
                            when {$calculateAge} < 25 then '20-25'
                            when {$calculateAge} < 30 then '25-30'
                            when {$calculateAge} < 40 then '30-40'
                            when {$calculateAge} >= 40 then '40+'
                            else 'Unknown'
                        end) as user_age"),
                    DB::raw("count(distinct {$tablePrefix}activities.user_id) as user_count"),
                    DB::raw("date({$tablePrefix}activities.created_at) as created_at_date")
                )
                ->where(function ($jq) {
                    $jq->where('activities.activity_name', '=', 'registration_ok');
                    $jq->orWhere('activities.activity_name', '=', 'login_ok');
                })
                ->leftJoin("user_details as {$tablePrefix}details", function ($join) {
                    $join->on('details.user_id', '=', 'activities.user_id');
                })
                ->groupBy('user_age', 'created_at_date');

            $isReport = $this->builderOnly;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $users, $tablePrefix) {
                $isReport = !!$_isReport;
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($users) {
                $users->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($users) {
                $users->where('activities.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_users = clone $users;

            $users->orderBy('user_count', 'desc');

            // Get the take args
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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $defaultSelect = [
                DB::raw("ifnull(sum(case report.user_age when '15-20' then report.user_count end), 0) as '15-20'"),
                DB::raw("ifnull(sum(case report.user_age when '20-25' then report.user_count end), 0) as '20-25'"),
                DB::raw("ifnull(sum(case report.user_age when '25-30' then report.user_count end), 0) as '25-30'"),
                DB::raw("ifnull(sum(case report.user_age when '30-35' then report.user_count end), 0) as '30-35'"),
                DB::raw("ifnull(sum(case report.user_age when '35-40' then report.user_count end), 0) as '35-40'"),
                DB::raw("ifnull(sum(case report.user_age when '40+' then report.user_count end), 0) as '40+'"),
                DB::raw("ifnull(sum(case report.user_age when 'Unknown' then report.user_count end), 0) as 'Unknown'"),
                DB::raw("ifnull(sum(report.user_count), 0) as 'total'")
            ];

            $summary   = NULL;
            $lastPage  = false;
            if ($isReport) {
                $userReportQuery = $_users->getQuery();

                $toSelect = array_merge($defaultSelect, [
                    DB::raw('report.created_at_date as created_at_date')
                ]);

                $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                    ->mergeBindings($_users->getQuery())
                    ->select($defaultSelect);

                $userReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                    ->mergeBindings($userReportQuery)
                    ->select($toSelect)
                    ->groupBy('created_at_date')
                    ->orderBy('created_at_date', 'desc');

                $_userReport = clone $userReport;
                $userReport->orderBy('created_at_date', 'desc');

                if ($this->builderOnly)
                {
                    return $this->builderObject($userReport, $summaryReport);
                }

                $userReport->take($take)->skip($skip);

                $totalReport = DB::table(DB::raw("({$_userReport->toSql()}) as total_report"))
                    ->mergeBindings($_userReport);

                $userList = $userReport->get();
                $userTotal = $totalReport->count();

                if (($userTotal - $take) <= $skip)
                {
                    $summary    = $summaryReport->first();
                    $lastPage   = true;
                }
            } else {
                $summaryReport = DB::table(DB::raw("({$_users->toSql()}) as report"))
                    ->mergeBindings($_users->getQuery())
                    ->select($defaultSelect);
                $summary = $summaryReport->first();
                $users->take($take);
                $userList  = $users->get();
                $userTotal = RecordCounter::create($_users)->count();
            }

            $data = new stdclass();
            $data->total_records = $userTotal;
            $data->returned_records = count($userList);
            $data->last_page = $lastPage;
            $data->summary   = $summary ? static::calculateSummaryPercentage($summary) : $summary;
            $data->records   = $userList;

            if ($userTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getuserbyage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getuserbyage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getuserbyage.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getuserbyage.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getuserbyage.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - TOP Timed User Login
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `skip`          (optional) - paging skip for limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getHourlyUserLogin()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettimeduserlogin.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettimeduserlogin.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettimeduserlogin.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.gettimeduserlogin.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.gettimeduserlogin.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.gettimeduserlogin.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettimeduserlogin.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dashboard.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dashboard.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            $formatDate = "(date_format({$tablePrefix}activities.created_at, '%H'))";

            $merchantIds = OrbitInput::get('merchant_id', []);

            $activities = Activity::considerCustomer($merchantIds)->select(
                    DB::raw("(
                        case
                            when {$formatDate} < 10 then '9-10'
                            when {$formatDate} < 11 then '10-11'
                            when {$formatDate} < 12 then '11-12'
                            when {$formatDate} < 13 then '12-13'
                            when {$formatDate} < 14 then '13-14'
                            when {$formatDate} < 15 then '14-15'
                            when {$formatDate} < 16 then '15-16'
                            when {$formatDate} < 17 then '16-17'
                            when {$formatDate} < 18 then '17-18'
                            when {$formatDate} < 19 then '18-19'
                            when {$formatDate} < 20 then '19-20'
                            when {$formatDate} < 21 then '20-21'
                            when {$formatDate} < 22 then '21-22'
                            else '21-22'
                        end) as time_range"),
                    DB::raw("count(distinct {$tablePrefix}activities.session_id) as login_count")
                )
                ->where('activity_name', '=', 'login_ok')
                ->groupBy('time_range');

            $isReport = $this->builderOnly;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $activities) {
                $isReport = !!$_isReport;
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($activities) {
                $activities->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($activities) {
                $activities->where('activities.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_activities = clone $activities;

            $activities->orderBy('login_count', 'desc');

            // Get the take args
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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $summary = NULL;
            $lastPage = false;
            $defaultSelect = [
                DB::raw("ifnull(sum(report.login_count), 0) as 'total'")
            ];

            for ($x=9; $x<22; $x++)
            {
                $name = sprintf("%s-%s", $x, $x+1);
                array_push(
                    $defaultSelect,
                    DB::raw("ifnull(sum(case report.time_range when '{$name}' then report.login_count end), 0) as '{$name}'")
                );
            }

            $activityReportQuery = $_activities->getQuery();
            $summaryReport = DB::table(DB::raw("({$_activities->toSql()}) as report"))
                ->mergeBindings($activityReportQuery)
                ->select($defaultSelect);

            if ($isReport)
            {
                $_activities->addSelect(
                    DB::raw("date({$tablePrefix}activities.created_at) as created_at_date")
                );
                $_activities->groupBy('created_at_date');

                $toSelect = array_merge($defaultSelect, [
                    DB::raw("report.created_at_date")
                ]);


                $activityReport = DB::table(DB::raw("({$_activities->toSql()}) as report"))
                    ->mergeBindings($activityReportQuery)
                    ->select($toSelect)
                    ->groupBy('created_at_date')
                    ->orderBy('created_at_date', 'desc');

                $_activityReport = clone $activityReport;

                $activityReport->orderBy('created_at_date', 'desc');

                if ($this->builderOnly)
                {
                    return $this->builderObject($activityReport, $summaryReport);
                }

                $activityReport->take($take)->skip($skip);

                $totalReport   = DB::table(DB::raw("({$_activityReport->toSql()}) as total_report"))
                                    ->mergeBindings($_activityReport);

                $activityList  = $activityReport->get();
                $activityTotal = $totalReport->count();
                if (($activityTotal - $take) <= $skip)
                {
                    $summary  = $summaryReport->first();
                    $lastPage = true;
                }
            } else {
                $activityList  = $activities->get();
                $summary = $summaryReport->first();
                $activityTotal = RecordCounter::create($_activities)->count();
            }

            $data = new stdclass();
            $data->total_records = $activityTotal;
            $data->returned_records = count($activityList);
            $data->records   = $activityList;
            $data->last_page = $lastPage;
            $data->summary   = $summary;

            if ($activityTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettimeduserlogin.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettimeduserlogin.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettimeduserlogin.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.gettimeduserlogin.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettimeduserlogin.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - User Connect Time
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `skip`          (optional) - paging skip for limit
     * @param boolean `is_report`     (optional) - display graphical or tabular data
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserConnectTime()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getuserconnecttime.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getuserconnecttime.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getuserconnecttime.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getuserconnecttime.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.getuserconnecttime.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.getuserconnecttime.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getuserconnecttime.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dashboard.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dashboard.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            $merchantIds = OrbitInput::get('merchant_id', []);

            $userActivities = Activity::considerCustomer($merchantIds)->select(
                    DB::raw("
                        timestampdiff(
                            MINUTE,
                            min(case activity_name when 'login_ok' then {$tablePrefix}activities.created_at end),
                            max(case activity_name when 'logout_ok' then {$tablePrefix}activities.created_at end)
                        ) as minute_connect
                    "),
                    DB::raw("date({$tablePrefix}activities.created_at) as created_at_date"),
                    DB::raw("count(distinct {$tablePrefix}activities.user_id) as user_count")
                )
                ->where(function ($q) {
                    $q->where('activity_name', '=', 'login_ok');
                    $q->orWhere('activity_name', '=', 'logout_ok');
                })
                ->groupBy('created_at_date', 'session_id');


            $activities = DB::table(DB::raw("({$userActivities->toSql()}) as {$tablePrefix}timed"))
                            ->select(
                                DB::raw("avg(
                                    case
                                        when {$tablePrefix}timed.minute_connect < 60 then {$tablePrefix}timed.minute_connect
                                        else 4
                                    end) as average_time_connect"
                                )
                            )
                            ->mergeBindings($userActivities->getQuery());

            $isReport = $this->builderOnly;
            OrbitInput::get('is_report', function ($_isReport) use ($activities, &$isReport, $tablePrefix) {
                $isReport = !!$_isReport;
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($activities) {
                $activities->where('timed.created_at_date', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($activities) {
                $activities->where('timed.created_at_date', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_activities = clone $activities;

            // Get the take args
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

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $averageTimeConnect = false;
            $summary  = null;
            $lastPage = false;
            if ($isReport)
            {

                $_activities->select(
                    DB::raw("
                            case
                                  when minute_connect < 5 then '<5'
                                  when minute_connect < 10 then '5-10'
                                  when minute_connect < 20 then '10-20'
                                  when minute_connect < 30 then '20-30'
                                  when minute_connect < 40 then '30-40'
                                  when minute_connect < 50 then '40-50'
                                  when minute_connect < 60 then '50-60'
                                  when minute_connect >= 60 then '60+'
                                  else '<5'
                            end as time_range"),
                    DB::raw("sum(ifnull(user_count, 0)) as user_count"),
                    "created_at_date"
                );

                $_activities->groupBy('created_at_date', 'time_range');

                $defaultSelect = [
                    DB::raw("ifnull(sum(case time_range when '<5' then user_count end), 0) as '<5'"),
                    DB::raw("ifnull(sum(case time_range when '5-10' then user_count end), 0) as '5-10'"),
                    DB::raw("ifnull(sum(case time_range when '10-20' then user_count end), 0) as '10-20'"),
                    DB::raw("ifnull(sum(case time_range when '20-30' then user_count end), 0) as '20-30'"),
                    DB::raw("ifnull(sum(case time_range when '30-40' then user_count end), 0) as '30-40'"),
                    DB::raw("ifnull(sum(case time_range when '40-50' then user_count end), 0) as '40-50'"),
                    DB::raw("ifnull(sum(case time_range when '50-60' then user_count end), 0) as '50-60'"),
                    DB::raw("ifnull(sum(case time_range when '60+' then user_count end), 0) as '60+'"),
                    DB::raw("ifnull(sum(user_count), 0) as 'total'")
                ];

                $toSelect = array_merge($defaultSelect, ['created_at_date']);
                $activityReport = DB::table(DB::raw("({$_activities->toSql()}) as report"))
                    ->mergeBindings($_activities)
                    ->select($toSelect)
                    ->groupBy('created_at_date');

                $summaryReport  = DB::table(DB::raw("({$_activities->toSql()}) as report"))
                    ->mergeBindings($_activities)
                    ->select($defaultSelect);

                $_activityReport = clone $activityReport;

                $activityReport->orderBy('created_at_date', 'desc');

                if ($this->builderOnly)
                {
                    return $this->builderObject($activityReport, $summaryReport);
                }

                $activityReport->take($take)->skip($skip);

                $totalReport = DB::table(DB::raw("({$_activityReport->toSql()}) as total_report"))
                    ->mergeBindings($_activityReport);

                $activityList  = $activityReport->get();
                $activityTotal = $totalReport->count();

                if (($activityTotal - $take) <= $skip)
                {
                    $summary  = $summaryReport->first();
                    $lastPage = true;
                }
            } else {
                $averageTimeConnect = $activities->first()->average_time_connect;
                $activityTotal = 0;
                $activityList  = NULL;
            }

            $data = new stdclass();
            $data->total_records    = $activityTotal;
            $data->returned_records = count($activityList);
            $data->records          = $activityList;

            if ($averageTimeConnect) {
                $data->average_time_connect = $averageTimeConnect;
            } else {
                $data->last_page = $lastPage;
                $data->summary   = $summary;
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getuserconnecttime.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getuserconnecttime.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getuserconnecttime.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getuserconnecttime.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getuserconnecttime.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Customer Last Visit Dashboard
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`          (optional) - Per Page limit
     * @param integer `skip`          (optional) - paging skip for limit
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserLastVisit()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getuserlastvisit.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getuserlastvisit.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getuserlastvisit.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getuserlastvisit.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.getuserlastvisit.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $validator = Validator::make(
                array(
                    'take' => $take
                ),
                array(
                    'take' => 'numeric'
                )
            );

            Event::fire('orbit.dashboard.getuserlastvisit.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getuserlastvisit.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dashboard.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dashboard.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();
            $monthlyActivity = Activity::select(
                    DB::raw("max(created_at) as created_at"),
                    'user_id',
                    'location_id',
                    DB::raw("date_format(created_at, '%Y-%m') as created_at_month"),
                    DB::raw("count(distinct activity_id) as visit_count")
                )
                ->where('activity_name', '=', 'login_ok')
                ->where('activities.user_id', '=', $this->api->getUserId())
                ->whereNotNull('activities.location_id')
                ->orderBy('activities.created_at', 'desc')
                ->groupBy('activities.user_id', 'created_at_month');

            $retailerCounter = Activity::select(
                    'user_id',
                    DB::raw('count(distinct location_id) as unique_visit_count')
                )
                ->where('activity_name', '=', 'login_ok')
                ->whereNotNull('activities.location_id')
                ->groupBy('activities.user_id');

            $lastTransactions = Transaction::select(
                    DB::raw("date(created_at) as created_at_date"),
                    DB::raw("sum(total_to_pay) as total_to_pay"),
                    DB::raw("max(created_at) as created_at"),
                    'retailer_id'
                )
                ->where('customer_id', '=', $this->api->getUserId())
                ->orderBy('created_at', 'desc')
                ->groupBy('retailer_id', 'created_at_date');

            $transactionSavingsByUser = Transaction::select(
                    "customer_id",
                    DB::raw("sum(ifnull(c.value_after_percentage, 0)) + sum(ifnull(p.value_after_percentage, 0)) as total_saving")
                )
                ->leftJoin('transaction_detail_coupons as c', DB::raw('c.transaction_id'), '=', 'transactions.transaction_id')
                ->leftJoin('transaction_detail_promotions as p', DB::raw('p.transaction_id'), '=', 'transactions.transaction_id')
                ->groupBy('customer_id');


            $activities = DB::table(DB::raw("({$monthlyActivity->toSql()}) as {$tablePrefix}activities"))
                ->mergeBindings($monthlyActivity->getQuery())
                ->select(
                    DB::raw("date(max({$tablePrefix}activities.created_at)) as last_visit_date"),
                    DB::raw("{$tablePrefix}last_transactions.total_to_pay as total_spent"),
                    DB::raw("round(avg(distinct visit_count)) as monthly_merchant_count"),
                    DB::raw("unique_visit_count as merchant_count"),
                    'merchants.name as last_visit_merchant_name',
                    'merchants.merchant_id as last_visit_merchant_id',
                    'total_saving'
                )
                ->join(DB::raw("({$retailerCounter->toSql()}) as counter"), function ($join) {
                    $join->on(DB::raw('counter.user_id'), '=', 'activities.user_id');
                })
                ->mergeBindings($retailerCounter->getQuery())
                ->join('user_details', 'user_details.user_id', '=', 'activities.user_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'user_details.last_visit_shop_id')
                ->leftJoin(DB::raw("({$lastTransactions->toSql()}) as {$tablePrefix}last_transactions"), function ($join) use ($tablePrefix) {
                    $join->on('last_transactions.retailer_id', '=', 'user_details.last_visit_shop_id');
                    $join->on('last_transactions.created_at', '>=', DB::raw("date({$tablePrefix}activities.created_at)"));
                })
                ->mergeBindings($lastTransactions->getQuery())
                ->leftJoin(DB::raw("({$transactionSavingsByUser->toSql()}) as transactions"), function ($join) {
                    $join->on(DB::raw('transactions.customer_id'), '=', 'user_details.user_id');
                })
                ->mergeBindings($transactionSavingsByUser->getQuery());



            OrbitInput::get('begin_date', function ($beginDate) use ($activities) {
                $activities->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($activities) {
                $activities->where('activities.created_at', '<=', $endDate);
            });

            $activity = $activities->first();

            $data = new stdclass();
            $data->total_records = 1;
            $data->returned_records = 1;
            $data->records   = $activity;

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getuserlastvisit.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getuserlastvisit.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getuserlastvisit.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getuserlastvisit.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getuserlastvisit.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Customer Purchase Summary Dashboard
     *
     * @author Yudi Rahono <yudi.rahono@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `take`               (optional) - Per Page limit
     * @param integer `skip`               (optional) - paging skip for limit
     * @param string  `retailer_name_like` (optional) filter by retailer name
     * @param integer `transaction_count`  (optional) transaction count filter
     * @param integer `transaction_total`  (optional) transaction total filter
     * @param date    `begin_date`         (optional) - filter date begin
     * @param date    `end_date`           (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserMerchantSummary()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getusermerchantsummary.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getusermerchantsummary.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getusermerchantsummary.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getusermerchantsummary.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.dashboard.getusermerchantsummary.after.authz', array($this, $user));

            $take = OrbitInput::get('take');
            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'take' => $take,
                    'sort_by' => $sort_by
                ),
                array(
                    'take' => 'numeric',
                    'sort_by' => 'in:retailer_name,transaction_count,transaction_total,visit_count,last_visit'
                )
            );

            Event::fire('orbit.dashboard.getusermerchantsummary.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getusermerchantsummary.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dashboard.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dashboard.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            $locationActivities = Activity::select(
                    DB::raw("max(created_at) as created_at"),
                    'user_id',
                    'location_id',
                    DB::raw("date_format(created_at, '%Y-%m') as created_at_month"),
                    DB::raw("count(distinct location_id) as unique_visit_count"),
                    DB::raw("count(distinct activity_id) as visit_count")
                )
                ->where('activity_name', '=', 'login_ok')
                ->where('activities.user_id', '=', $this->api->getUserId())
                ->orderBy('activities.created_at', 'desc')
                ->groupBy('activities.user_id', 'created_at_month', 'location_id');

            $activities = DB::table(DB::raw("({$locationActivities->toSql()}) as {$tablePrefix}activities"))->select(
                    'merchants.merchant_id as retailer_id',
                    'merchants.name as retailer_name',
                    'transactions.currency',
                    'transactions.currency_symbol',
                    DB::raw("ifnull({$tablePrefix}media.path, {$tablePrefix}merchants.logo) as retailer_logo"),
                    DB::raw("count(distinct {$tablePrefix}transactions.transaction_id) as transaction_count"),
                    DB::raw("ifnull(sum({$tablePrefix}transactions.total_to_pay),0)as transaction_total"),
                    DB::raw("sum(distinct {$tablePrefix}activities.visit_count) as visit_count"),
                    DB::raw("max({$tablePrefix}activities.created_at) as last_visit")
                )
                ->mergeBindings($locationActivities->getQuery())
                ->leftJoin('transactions', function($join) {
                    $join->on('transactions.customer_id', '=', 'activities.user_id');
                    $join->on('transactions.retailer_id', '=', 'activities.location_id');
                })
                ->join('merchants', 'merchants.merchant_id', '=', 'activities.location_id')
                ->leftJoin('media', function($join) {
                    $join->on('merchants.merchant_id', '=', 'media.object_id');
                })
                ->where('media.object_name', 'mall')
                ->leftJoin('merchants as parent', DB::raw('parent.merchant_id'), '=', 'merchants.parent_id')
                ->groupBy('activities.location_id');

            OrbitInput::get('begin_date', function ($beginDate) use ($activities) {
                $activities->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($activities) {
                $activities->where('activities.created_at', '<=', $endDate);
            });

            OrbitInput::get('retailer_name_like', function($retailerName) use ($activities) {
                $activities->where('merchants.name', 'like', "%{$retailerName}%");
            });

            OrbitInput::get('transaction_count', function($trxCount) use ($activities) {
                $activities->having('transaction_count', 'like', "%{$trxCount}%");
            });

            $transactionFilterMapping = [
                '1M' => sprintf('< %s', 1e6),
                '2M' => sprintf('between %s and %s', 1e6, 2e6),
                '3M' => sprintf('between %s and %s', 2e6, 3e6),
                '4M' => sprintf('between %s and %s', 3e6, 4e6),
                '5M' => sprintf('between %s and %s', 4e6, 5e6),
                '6M' => sprintf('> %s', 5e6),
            ];
            OrbitInput::get('transaction_total_range', function ($trxTotal) use ($activities, $transactionFilterMapping) {
                $range  = $transactionFilterMapping[$trxTotal];
                $activities->havingRaw('transaction_total ' . $range);
            });

            OrbitInput::get('transaction_total_gte', function ($trxTotal) use ($activities) {
               $activities->having('transaction_total', '>=', $trxTotal);
            });

            OrbitInput::get('transaction_total_lte', function ($trxTotal) use ($activities) {
                $activities->having('transaction_total', '<=', $trxTotal);
            });

            $_activities = clone $activities;

            // Get the take args
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

            $activities->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            $activities->skip($skip);

            // Default sort by
            $sortBy = 'activities.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'retailer_name'      => 'retailer_name',
                    'transaction_count'  => 'transaction_count',
                    'transaction_total'  => 'transaction_total',
                    'visit_count'        => 'visit_count',
                    'last_visit'         => 'last_visit'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $activities->orderBy($sortBy, $sortMode);

            $transactionTotal = DB::table(DB::raw("({$_activities->toSql()}) as sub_total"))
                ->mergeBindings($_activities)
                ->count();
            $transactionList = $activities->get();

            $data = new stdclass();
            $data->total_records    = $transactionTotal;
            $data->returned_records = count($transactionList);
            $data->records          = $transactionList;

            if ($transactionTotal === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getusermerchantsummary.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getusermerchantsummary.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getusermerchantsummary.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getusermerchantsummary.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getusermerchantsummary.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Coupon Issues vs Redeemed Report List
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - Column order by. Valid value: promotion_name, total_issued, total_redeemed.
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponIssuedVSRedeemed()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getcouponissuedvsredeemed.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getcouponissuedvsredeemed.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getcouponissuedvsredeemed.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_coupon_report')) {
                Event::fire('orbit.dashboard.getcouponissuedvsredeemed.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon_report');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.dashboard.getcouponissuedvsredeemed.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');

            $configMallId = OrbitInput::get('merchant_id', OrbitInput::get('mall_id'));
            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'merchant_id' => $configMallId,
                    'sort_by' => $sort_by,
                ),
                array(
                    'merchant_id' => 'required|orbit.empty.mall',
                    'sort_by' => 'in:promotion_name,total_issued,total_redeemed',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.dashboardissuedvsredeemed_sortby'),
                )
            );
            Event::fire('orbit.dashboard.getcouponissuedvsredeemed.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getcouponissuedvsredeemed.after.validation', array($this, $validator));

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

            // Builder object
            $now = date('Y-m-d H:i:s');
            $prefix = DB::getTablePrefix();
            $take_top = OrbitInput::get('take_top');
            $start_date = OrbitInput::get('start_date', date('Y-m-d 00:00:00'));
            $end_date = OrbitInput::get('end_date', date('Y-m-d 23:59:59'));

            if (empty($take_top)) {
                $take_top = 0;

            }

            $coupons = Coupon::select(
                    'promotions.promotion_name',
                    'issued_coupons.issued_coupon_id',
                    DB::raw("sum(case
                        when {$prefix}issued_coupons.status in ('active') then 1
                        else 0
                        end) as total_issued"),
                    DB::raw("sum(case
                        when {$prefix}issued_coupons.status in ('redeemed') then 1
                        else 0
                        end) as total_redeemed")
                )
                ->join('issued_coupons','issued_coupons.promotion_id','=','promotions.promotion_id')
                ->where('promotions.merchant_id','=',$configMallId)
                ->where(function($q) use ($start_date, $end_date) {
                        $q->where(function($q2) use ($start_date, $end_date) {
                            $q2->where('issued_date', '>=', $start_date);
                            $q2->where('issued_date', '<=', $end_date);
                        });
                        $q->orWhere(function($q3) use ($start_date, $end_date) {
                            $q3->Where('redeemed_date', '>=', $start_date);
                            $q3->Where('redeemed_date', '<=', $end_date);
                        });
                })
                ->groupBy('promotions.promotion_name');

            // Filter by Promotion Name
            OrbitInput::get('promotion_name_like', function($name) use ($coupons) {
                $coupons->where('promotion_name', 'like', "%$name%");
            });

            // Filter by Retailer name
            OrbitInput::get('retailer_name_like', function($name) use ($coupons) {
                $coupons->where('retailer_name', 'like', "%$name%");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;
            $_coupons->select('issued_coupon_id');

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take_top', function ($_take) use (&$take, $maxRecord) {
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

            // Default sort by
            $sortBy = 'total_issued';

            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'promotion_name'       => 'promotion_name',
                    'total_issued'         => 'total_issued',
                    'total_redeemed'       => 'total_redeemed',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $coupons->orderBy($sortBy, $sortMode);

            $totalCoupons = $_coupons->count();
            $listOfCoupons = $coupons->get();

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getcouponissuedvsredeemed.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getcouponissuedvsredeemed.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getcouponissuedvsredeemed.query.error', array($this, $e));

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
            Event::fire('orbit.dashboard.getcouponissuedvsredeemed.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getcouponissuedvsredeemed.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Top Tenant Redeem
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - Column order by. Valid value: issued_coupon_id,promotion_id,transaction_id,issued_coupon_code,user_id,expired_date,issued_date,redeemed_date,issuer_retailer_id,redeem_retailer_id,redeem_verification_code,status,created_at,updated_at.
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getTopTenantRedeem()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettoptenantreedem.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettoptenantreedem.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettoptenantreedem.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_coupon_report')) {
                Event::fire('orbit.dashboard.gettoptenantreedem.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon_report');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.dashboard.gettoptenantreedem.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');

            $configMallId = OrbitInput::get('merchant_id', OrbitInput::get('mall_id'));

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'merchant_id' => $configMallId,
                    'sort_by' => $sort_by,
                ),
                array(
                    'merchant_id' => 'required|orbit.empty.mall',
                    'sort_by' => 'in:mall_id,retailer_name,issued_coupon_id,promotion_id,transaction_id,issued_coupon_code,user_id,expired_date,issued_date,redeemed_date,issuer_retailer_id,redeem_retailer_id,redeem_verification_code,status,created_at,updated_at,precentage',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.couponredeemedreportgeneral_sortby'),
                )
            );
            Event::fire('orbit.dashboard.gettoptenantreedem.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettoptenantreedem.after.validation', array($this, $validator));

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

            // Builder object
            $now = date('Y-m-d H:i:s');
            $prefix = DB::getTablePrefix();
            $total_all_redeem = IssuedCoupon::join('merchants', 'issued_coupons.redeem_retailer_id', '=', 'merchants.merchant_id')
                                            ->where('issued_coupons.status', 'redeemed')
                                            ->where('merchants.parent_id', $configMallId)
                                            ->count();

            $coupon_redeemed = IssuedCoupon::select('redeem_retailer_id',
                                                    'merchants.parent_id as mall_id' ,
                                                    'merchants.name as retailer_name',
                                                    'merchants.logo as tenant_logo',
                                                    DB::raw("count(redeem_retailer_id) as total_redeemed, count(redeem_retailer_id)/{$total_all_redeem}*100 as percentage"))
                                             ->with('redeemRetailerMedia')
                                             ->join('merchants', 'merchants.merchant_id', '=', 'issued_coupons.redeem_retailer_id')
                                             ->groupBy('issued_coupons.redeem_retailer_id');

            // Filter by mall id
            OrbitInput::get('merchant_id', function($mallId) use ($coupon_redeemed) {
                $coupon_redeemed->where('merchants.parent_id', $mallId);
            });

            // Filter by Retailer name
            OrbitInput::get('retailer_name_like', function($name) use ($coupon_redeemed) {
                $coupon_redeemed->where('merchants.name', 'like', "%$name%");
            });

            // Filter by date
            // Greater Than Equals
            OrbitInput::get('start_date', function($date) use ($coupon_redeemed) {
                $coupon_redeemed->where('issued_coupons.redeemed_date', '>=', $date);
            });

            // Less Than Equals
            OrbitInput::get('end_date', function($date) use ($coupon_redeemed) {
                $coupon_redeemed->where('issued_coupons.redeemed_date', '<=', $date);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupon_redeemed = clone $coupon_redeemed;
            $_coupon_redeemed->select('issued_coupons.issued_coupon_id')->groupBy('issued_coupons.redeem_retailer_id');

            // Get the take args
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
            $coupon_redeemed->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $coupon_redeemed)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $coupon_redeemed->skip($skip);

            // Default sort by
            $sortBy = 'total_redeemed';

            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'mall_id'                       => 'mall_id',
                    'retailer_name'                 => 'retailer_name',
                    'issued_coupon_id'              => 'issued.issued_coupon_id',
                    'promotion_id'                  => 'issued_coupons.promotion_id',
                    'transaction_id'                => 'issued_coupons.transaction_id',
                    'issued_coupon_code'            => 'issued_coupons.issued_coupon_code',
                    'user_id'                       => 'issued_coupons.user_id',
                    'expired_date'                  => 'issued_coupons.expired_date',
                    'issued_date'                   => 'issued_coupons.issued_date',
                    'redeemed_date'                 => 'issued_coupons.redeemed_date',
                    'issuer_retailer_id'            => 'issued_coupons.issuer_retailer_id',
                    'redeem_retailer_id'            => 'issued_coupons.redeem_retailer_id',
                    'redeem_verification_code'      => 'issued_coupons.redeem_verification_code',
                    'status'                        => 'issued_coupons.status',
                    'total_redeemed'                => 'total_redeemed',
                    'precentage'                    => 'precentage'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            // sort by status first
            if ($sortBy !== 'total_redeemed') {
                $coupon_redeemed->orderBy('total_redeemed', 'desc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });

            $coupon_redeemed->orderBy($sortBy, $sortMode);
            $coupon_redeemed->groupBy('redeem_retailer_id');

            // also to sort tenant name
            if ($sortBy !== 'retailer_name') {
                $coupon_redeemed->orderBy('retailer_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $coupon_redeemed, 'count' => RecordCounter::create($_coupon_redeemed)->count()];
            }

            $totalCoupons = RecordCounter::create($_coupon_redeemed)->count();
            $listOfCoupons = $coupon_redeemed->get();

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettoptenantreedem.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettoptenantreedem.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettoptenantreedem.query.error', array($this, $e));

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
            Event::fire('orbit.dashboard.gettoptenantreedem.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettoptenantreedem.before.render', array($this, &$output));

        return $output;
    }

    public static function calculateSummaryPercentage($summary = array(), $totalField = 'total')
    {
        if (! ($summary && property_exists((object) $summary, $totalField)))
        {
            return $summary;
        }

        $summary = (array) $summary;

        $total      = $summary[$totalField];
        foreach ($summary as $name => $value)
        {
            $percent = 0;
            if ($total > 0) {
                $percent = floor(($value / $total) * 1e4) / 100;
            }
            $summary[$name.'_percentage'] = "{$percent} %";
        }

        return (object) $summary;
    }

    /**
     * @param mixed $mixed
     * @return array
     */
    private function getArray($mixed)
    {
        $arr = [];
        if (is_array($mixed)) {
            $arr = array_merge($arr, $mixed);
        } else {
            array_push($arr, $mixed);
        }

        return $arr;
    }

    protected function registerCustomValidation()
    {
        $user = $this->api->user;
        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) use ($user){
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->where('is_mall', 'yes')
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check the existence of object type, object type allowed is news, event, promotion, lucky draw
        Validator::extend('orbit.empty.detail_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $objectTypes = array('promotion', 'news', 'event', 'promotion', 'luck_draw');
            foreach ($objectTypes as $objectType) {
                if($value === $objectType) $valid = $valid || TRUE;
            }

            return $valid;
        });

    }

	/**
     * GET - Estimated Total Cost
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `current_mall`   (optional) - mall id
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getEstimateTotalCost()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getgeneralcustomerview.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.activity.gettopten.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.activity.gettopten.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newsViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.activity.gettopten.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::get('current_mall');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $validator = Validator::make(
                array(
                    'merchant_id'         => $merchant_id,
                    'start_date'          => $start_date,
                    'end_date'            => $end_date,
                ),
                array(
                    'merchant_id'         => 'orbit.empty.merchant',
                    'start_date'          => 'required|date_format:Y-m-d H:i:s',
                    'end_date'            => 'required|date_format:Y-m-d H:i:s',
                )
            );

            Event::fire('orbit.activity.gettopten.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.activity.gettopten.after.validation', array($this, $validator));

            // registrations from start to end grouped by date part and activity name long.
            // activity name long should include source.

            $tablePrefix = DB::getTablePrefix();
            $timezone = $this->getTimezone($merchant_id);
            $timezoneOffset = $this->getTimezoneOffset($timezone);

            $startConvert = Carbon::createFromFormat('Y-m-d H:i:s', $start_date, 'UTC');
            $startConvert->setTimezone($timezone);

            $endConvert = Carbon::createFromFormat('Y-m-d H:i:s', $end_date, 'UTC');
            $endConvert->setTimezone($timezone);

            $start_date = $startConvert->toDateString();
            $end_date = $endConvert->toDateString();

            //get total cost news
            $news = DB::table('news')->selectraw(DB::raw("COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF( {$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS total"))
                        ->join('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->where('merchants.status', '=', 'active')
                        ->where('news.mall_id', '=', $merchant_id)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->WhereRaw("DATE_FORMAT({$tablePrefix}news.begin_date, '%Y-%m-%d') >= DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') and DATE_FORMAT({$tablePrefix}news.begin_date, '%Y-%m-%d') <= DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d')")
                              ->orWhereRaw("DATE_FORMAT({$tablePrefix}news.end_date, '%Y-%m-%d') >= DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') and DATE_FORMAT({$tablePrefix}news.end_date, '%Y-%m-%d') <= DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d')")
                              ->orWhereRaw("DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') >= DATE_FORMAT({$tablePrefix}news.begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') <= DATE_FORMAT({$tablePrefix}news.end_date, '%Y-%m-%d')")
                              ->orWhereRaw("DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') >= DATE_FORMAT({$tablePrefix}news.begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') <= DATE_FORMAT({$tablePrefix}news.end_date, '%Y-%m-%d')")
                              ->orWhereRaw("DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') <= DATE_FORMAT({$tablePrefix}news.begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') >= DATE_FORMAT({$tablePrefix}news.end_date, '%Y-%m-%d')");
                        })
                        ->where('campaign_price.campaign_type', '=', 'news')
                        ->where('news.object_type', '=', 'news')
                        ->groupBy('news.news_id');

            $promotions = DB::table('news')->selectraw(DB::raw("COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS total"))
                                ->join('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                ->join('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                                ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                ->where('merchants.status', '=', 'active')
                                ->where('news.mall_id', '=', $merchant_id)
                                ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                                    $q->WhereRaw("DATE_FORMAT({$tablePrefix}news.begin_date, '%Y-%m-%d') >= DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') and DATE_FORMAT({$tablePrefix}news.begin_date, '%Y-%m-%d') <= DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d')")
                                      ->orWhereRaw("DATE_FORMAT({$tablePrefix}news.end_date, '%Y-%m-%d') >= DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') and DATE_FORMAT({$tablePrefix}news.end_date, '%Y-%m-%d') <= DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d')")
                                      ->orWhereRaw("DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') >= DATE_FORMAT({$tablePrefix}news.begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') <= DATE_FORMAT({$tablePrefix}news.end_date, '%Y-%m-%d')")
                                      ->orWhereRaw("DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') >= DATE_FORMAT({$tablePrefix}news.begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') <= DATE_FORMAT({$tablePrefix}news.end_date, '%Y-%m-%d')")
                                      ->orWhereRaw("DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') <= DATE_FORMAT({$tablePrefix}news.begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') >= DATE_FORMAT({$tablePrefix}news.end_date, '%Y-%m-%d')");
                                })
                                ->where('campaign_price.campaign_type', '=', 'promotion')
                                ->where('news.object_type', '=', 'promotion')
                                ->groupBy('news.news_id');

            $coupons = DB::table('promotions')->selectraw(DB::raw("COUNT({$tablePrefix}promotion_retailer.promotion_retailer_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}promotions.end_date, {$tablePrefix}promotions.begin_date) + 1) AS total"))
                        ->join('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'promotions.promotion_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                        ->where('merchants.status', '=', 'active')
                        ->where('promotions.merchant_id', '=', $merchant_id)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->WhereRaw("DATE_FORMAT({$tablePrefix}promotions.begin_date, '%Y-%m-%d') >= DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') and DATE_FORMAT({$tablePrefix}promotions.begin_date, '%Y-%m-%d') <= DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d')")
                              ->orWhereRaw("DATE_FORMAT({$tablePrefix}promotions.end_date, '%Y-%m-%d') >= DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') and DATE_FORMAT({$tablePrefix}promotions.end_date, '%Y-%m-%d') <= DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d')")
                              ->orWhereRaw("DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') >= DATE_FORMAT({$tablePrefix}promotions.begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') <= DATE_FORMAT({$tablePrefix}promotions.end_date, '%Y-%m-%d')")
                              ->orWhereRaw("DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') >= DATE_FORMAT({$tablePrefix}promotions.begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') <= DATE_FORMAT({$tablePrefix}promotions.end_date, '%Y-%m-%d')")
                              ->orWhereRaw("DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') <= DATE_FORMAT({$tablePrefix}promotions.begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') >= DATE_FORMAT({$tablePrefix}promotions.end_date, '%Y-%m-%d')");
                        })
                        ->where('campaign_price.campaign_type', '=', 'coupon')
                        ->groupBy('promotions.promotion_id');

            $data = $news->unionAll($promotions)->unionAll($coupons);
            $sql = $data->toSql();
            foreach($data->getBindings() as $binding)
            {
              $value = is_numeric($binding) ? $binding : "'".$binding."'";
              $sql = preg_replace('/\?/', $value, $sql, 1);
            }

            $grandtotal['estimated_total_cost'] = DB::table(DB::raw('(' . $sql . ') as a'))->sum('total');

            $this->response->data = $grandtotal;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getgeneralcustomerview.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getgeneralcustomerview.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getgeneralcustomerview.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getgeneralcustomerview.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getgeneralcustomerview.before.render', array($this, &$output));

        return $output;
    }

	/**
     * Get campaign status
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     * @author Irianto <irianto@dominopos.com>
     * @return \OrbitShop\API\v1\ResponseProvider | string
     * @todo Validations
     */
    public function getCampaignStatus()
    {
        // $date = OrbitInput::get('date');
        $mallId = OrbitInput::get('current_mall');

        $mall = Mall::excludeDeleted()->where('merchant_id', $mallId)->first();
        $mallTime = Carbon::now($mall->timezone->timezone_name);

        // Promotions
        $notStartedPromotionCount = News::ofMallId($mallId)->isPromotion()->campaignStatus('not started', $mallTime)->count();
        $ongoingPromotionCount = News::ofMallId($mallId)->isPromotion()->campaignStatus('ongoing', $mallTime)->count();
        $pausedPromotionCount = News::ofMallId($mallId)->isPromotion()->campaignStatus('paused', $mallTime)->count();
        $stoppedPromotionCount = News::ofMallId($mallId)->isPromotion()->campaignStatus('stopped', $mallTime)->count();
        $expiredPromotionCount = News::ofMallId($mallId)->isPromotion()->campaignStatus('expired', $mallTime)->count();

        // News
        $notStartedNewsCount = News::ofMallId($mallId)->isNews()->campaignStatus('not started', $mallTime)->count();
        $ongoingNewsCount = News::ofMallId($mallId)->isNews()->campaignStatus('ongoing', $mallTime)->count();
        $pausedNewsCount = News::ofMallId($mallId)->isNews()->campaignStatus('paused', $mallTime)->count();
        $stoppedNewsCount = News::ofMallId($mallId)->isNews()->campaignStatus('stopped', $mallTime)->count();
        $expiredNewsCount = News::ofMallId($mallId)->isNews()->campaignStatus('expired', $mallTime)->count();

        // Coupons
        $notStartedCouponCount = Coupon::ofMerchantId($mallId)->campaignStatus('not started', $mallTime)->count();
        $ongoingCouponCount = Coupon::ofMerchantId($mallId)->campaignStatus('ongoing', $mallTime)->count();
        $pausedCouponCount = Coupon::ofMerchantId($mallId)->campaignStatus('paused', $mallTime)->count();
        $stoppedCouponCount = Coupon::ofMerchantId($mallId)->campaignStatus('stopped', $mallTime)->count();
        $expiredCouponCount = Coupon::ofMerchantId($mallId)->campaignStatus('expired', $mallTime)->count();

        $this->response->data = [
            'promotions_not_started'    => $notStartedPromotionCount,
            'promotions_ongoing'  => $ongoingPromotionCount,
            'promotions_paused'  => $pausedPromotionCount,
            'promotions_stopped'  => $stoppedPromotionCount,
            'promotions_expired'  => $expiredPromotionCount,
            'news_not_started'          => $notStartedNewsCount,
            'news_ongoing'          => $ongoingNewsCount,
            'news_paused'          => $pausedNewsCount,
            'news_stopped'          => $stoppedNewsCount,
            'news_expired'        => $expiredNewsCount,
            'coupons_not_started'       => $notStartedCouponCount,
            'coupons_ongoing'       => $ongoingCouponCount,
            'coupons_paused'       => $pausedCouponCount,
            'coupons_stopped'       => $stoppedCouponCount,
            'coupons_expired'     => $expiredCouponCount,
        ];

        return $this->render(200);
    }



    /**
     * GET - Total page views
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param string  `current_mall`  (optional) - mall id
     * @param date    `start_date`    (optional) - filter date start
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getTotalPageView()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.gettotalpageview.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.gettotalpageview.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.gettotalpageview.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            if (! in_array( strtolower($role->role_name), $this->newsViewRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.dashboard.gettotalpageview.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $current_mall = OrbitInput::get('current_mall');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $validator = Validator::make(
                array(
                    'current_mall' => $current_mall,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'start_date' => 'required',
                    'end_date' => 'required',
                )
            );

            Event::fire('orbit.dashboard.gettotalpageview.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.gettotalpageview.after.validation', array($this, $validator));

            $campaignList = ['Coupon', 'News', 'Promotion'];

            $campaigns = CampaignGroupName::getPageViewByLocation($current_mall, $start_date, $end_date)->get();

            $total = 0;

            foreach ($campaigns as $key => $value)
            {
                if( in_array($campaigns[$key]->campaign_group_name, $campaignList) )
                {
                    $total = $total+$campaigns[$key]->count;
                }
            }

            if (empty($campaigns)) {
                $this->response->message = Lang::get('statuses.orbit.nodata.object');
            }

            $data = new stdclass();
            $data->total_page_view = $total;

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.gettotalpageview.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.gettotalpageview.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.gettotalpageview.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.gettotalpageview.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.gettotalpageview.before.render', array($this, &$output));

        return $output;
    }


    /**
     * GET - Unique users
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param string  `current_mall`  (optional) - mall id
     * @param date    `start_date`    (optional) - filter date start
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getUniqueUsers()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getuniqueusers.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getuniqueusers.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getuniqueusers.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            if (! in_array( strtolower($role->role_name), $this->newsViewRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.dashboard.getuniqueusers.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $current_mall = OrbitInput::get('current_mall');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $validator = Validator::make(
                array(
                    'current_mall' => $current_mall,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'start_date' => 'required',
                    'end_date' => 'required',
                )
            );

            Event::fire('orbit.dashboard.getuniqueusers.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getuniqueusers.after.validation', array($this, $validator));

            $tablePrefix = DB::getTablePrefix();


            $query = DB::select("select date_format(created_at, '%Y-%m-%d') as days, count(distinct user_id) as unique_visit_perday
                        from {$tablePrefix}user_signin
                        where location_id = ?
                            and created_at between ? and ?
                        group by 1
                        order by 1
                        ", array($current_mall, $start_date, $end_date));

            $total_unique_visit = 0;

            if ( !empty($query) ) {
                foreach ($query as $key => $value) {
                    $total_unique_visit += $query[$key]->unique_visit_perday;
                }
            }

            $data = new stdclass();
            $data->unique_users = $total_unique_visit;

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getuniqueusers.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getuniqueusers.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getuniqueusers.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getuniqueusers.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getuniqueusers.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Campaign Spending
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param integer `current_mall`   (optional) - mall id
     * @param date    `begin_date`    (optional) - filter date begin
     * @param date    `end_date`      (optional) - filter date end
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignSpending()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getgeneralcustomerview.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.activity.gettopten.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.activity.gettopten.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newsViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.activity.gettopten.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::get('current_mall');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');
            $without = OrbitInput::get('without');

            $validator = Validator::make(
                array(
                    'merchant_id'         => $merchant_id,
                    'start_date'          => $start_date,
                    'end_date'            => $end_date,
                ),
                array(
                    'merchant_id'         => 'orbit.empty.merchant',
                    'start_date'          => 'required|date_format:Y-m-d H:i:s',
                    'end_date'            => 'required|date_format:Y-m-d H:i:s',
                )
            );

            Event::fire('orbit.activity.gettopten.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.activity.gettopten.after.validation', array($this, $validator));

            // registrations from start to end grouped by date part and activity name long.
            // activity name long should include source.

            $tablePrefix = DB::getTablePrefix();
            $mall = App::make('orbit.empty.merchant');
            $timezone = $this->getTimezone($merchant_id);
            $timezoneOffset = $this->getTimezoneOffset($timezone);

            $startConvert = Carbon::createFromFormat('Y-m-d H:i:s', $start_date, 'UTC');
            $startConvert->setTimezone($timezone);

            $endConvert = Carbon::createFromFormat('Y-m-d H:i:s', $end_date, 'UTC');
            $endConvert->setTimezone($timezone);

            $start_date = $startConvert->toDateString();
            $end_date = $endConvert->toDateString();

            $totalnews = DB::select( DB::raw("SELECT SUM(IFNULL(fnc_campaign_cost(news_id, 'news', {$this->quote($start_date)}, {$this->quote($end_date)}, {$this->quote($timezoneOffset)}), 0.00)) AS campaign_total_cost
                                            FROM {$tablePrefix}news
                                            WHERE DATE_FORMAT(begin_date,'%Y-%m-%d') <= {$this->quote($end_date)}
                                                AND DATE_FORMAT(end_date,'%Y-%m-%d') >= {$this->quote($start_date)}
                                                AND object_type = 'news'
                                                AND mall_id = {$this->quote($merchant_id)}
                                            "));

            $totalpromotion = DB::select( DB::raw("SELECT SUM(IFNULL(fnc_campaign_cost(news_id, 'promotion', {$this->quote($start_date)}, {$this->quote($end_date)}, {$this->quote($timezoneOffset)}), 0.00)) AS campaign_total_cost
                                            FROM {$tablePrefix}news
                                            WHERE DATE_FORMAT(begin_date,'%Y-%m-%d') <= {$this->quote($end_date)}
                                                AND DATE_FORMAT(end_date,'%Y-%m-%d') >= {$this->quote($start_date)}
                                                AND object_type = 'promotion'
                                                AND mall_id = {$this->quote($merchant_id)}
                                            "));

            $totalcoupon = DB::select( DB::raw("SELECT SUM(IFNULL(fnc_campaign_cost(promotion_id, 'coupon', {$this->quote($start_date)}, {$this->quote($end_date)}, {$this->quote($timezoneOffset)}), 0.00)) AS campaign_total_cost
                                            FROM {$tablePrefix}promotions
                                            WHERE DATE_FORMAT(begin_date,'%Y-%m-%d') <= {$this->quote($end_date)}
                                                AND DATE_FORMAT(end_date,'%Y-%m-%d') >= {$this->quote($start_date)}
                                                AND is_coupon = 'Y'
                                                AND merchant_id = {$this->quote($merchant_id)}
                                            "));

            $news = floatval($totalnews[0]->campaign_total_cost);
            $promotions = floatval($totalpromotion[0]->campaign_total_cost);
            $coupon = floatval($totalcoupon[0]->campaign_total_cost);

            $total = $news + $promotions + $coupon ;

            if (empty($without)) {
                if($total != 0) {
                    $data['records'] = array (
                        array('campaign_type'=>'news', 'campaign_spending'=>$news , 'percentage'=>number_format(($news/$total*100),2)),
                        array('campaign_type'=>'promotions', 'campaign_spending'=>$promotions , 'percentage'=>number_format(($promotions/$total*100),2)),
                        array('campaign_type'=>'coupons', 'campaign_spending'=>$coupon , 'percentage'=>number_format(($coupon/$total*100),2))
                    );
                } else {
                    $data['records'] = array (
                        array('campaign_type'=>'news', 'campaign_spending'=>$news, 'percentage'=>0),
                        array('campaign_type'=>'promotions', 'campaign_spending'=>$promotions, 'percentage'=>0),
                        array('campaign_type'=>'coupons', 'campaign_spending'=>$coupon, 'percentage'=>0)
                    );
                }

            }

            $data['total'] = $total;
            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getgeneralcustomerview.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getgeneralcustomerview.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getgeneralcustomerview.query.error', array($this, $e));

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
            $httpCode = 500;
            Event::fire('orbit.dashboard.getgeneralcustomerview.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getgeneralcustomerview.before.render', array($this, &$output));

        return $output;
    }

    protected function getTimezone($current_mall)
    {
        $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
            ->where('merchants.merchant_id','=', $current_mall)
            ->first();

        return $timezone->timezone_name;
    }

    protected function getTimezoneOffset($timezone)
    {
        $dt = new DateTime('now', new DateTimeZone($timezone));

        return $dt->format('P');
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}