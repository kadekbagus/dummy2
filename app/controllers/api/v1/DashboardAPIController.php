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

class DashboardAPIController extends ControllerAPI
{
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

            $activities = DB::table('activities')
                ->join('merchants', "activities.object_id", '=', "merchants.merchant_id")
                ->select(
                    DB::raw("{$tablePrefix}activities.object_id as tenant_id"),                    
                    DB::raw("COUNT({$tablePrefix}activities.activity_id) as score"),
                    DB::raw("{$tablePrefix}merchants.name as tenant_name"),
                    DB::raw("
                                count({$tablePrefix}activities.activity_id) / (
                                        SELECT count({$tablePrefix}activities.activity_id) FROM {$tablePrefix}activities
                                    where 1=1
                                    and activity_name = 'view_retailer' 
                                    and activity_type = 'view' 
                                    and object_name = 'Tenant' 
                                    and `group` = 'mobile-ci' 
                                    and role = 'Consumer' 
                                    and location_id = '" . $merchant_id . "'
                                    and DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') >= '" . $start_date . "'
                                    and DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') <= '" . $end_date . "'
                                )*100 as percentage
                        ")
                )
                ->where("activities.activity_name", '=', 'view_retailer')
                ->where("activities.activity_type", '=', 'view')
                ->where("activities.object_name", '=', 'Tenant')
                ->where("activities.group", '=', 'mobile-ci')
                ->where("activities.role", '=', 'Consumer')
                ->where("activities.location_id", '=', $merchant_id)
                ->where("activities.created_at", '>=', $start_date)
                ->where("activities.created_at", '<=', $end_date)
                ->groupBy("activities.object_id")
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

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.gettopcustomerview.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
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

            if(empty($take)) {
                $take = 5;
            }

            $tablePrefix = DB::getTablePrefix();

            switch ($type) {

                // show news
                case 'news':
                        $query = News::select(
                            DB::raw("count(distinct {$tablePrefix}activities.activity_id)/ (
                                select
                                    count(ac.activity_id) as total
                                from
                                    {$tablePrefix}news ne
                                        inner join
                                    {$tablePrefix}activities ac ON ne.news_id = ac.news_id
                                where ac.module_name = 'News'
                                and ac.activity_name = 'view_news'
                                and ac.activity_type = 'view'
                                and ac.role = 'Consumer'
                                and ac.group = 'mobile-ci'
                                and ac.location_id = '{$merchant_id}'
                                and DATE_FORMAT(ac.created_at, '%Y-%m-%d %H:%i:%s') >= '{$start_date}'
                                and DATE_FORMAT(ac.created_at, '%Y-%m-%d %H:%i:%s') <= '{$end_date}'
                            ) * 100 as percentage"),
                            DB::raw("count(distinct {$tablePrefix}activities.activity_id) as score"),
                            "news.news_name as name",
                            "news.news_id as object_id"
                        )
                        ->join("activities", function ($join) use ($merchant_id, $start_date, $end_date) {
                            $join->on('news.news_id', '=', 'activities.news_id');
                            $join->where('news.object_type', '=', 'news'); 
                            $join->where('activities.activity_name', '=', 'view_news');
                            $join->where('activities.module_name', '=', 'News');
                            $join->where('activities.activity_type', '=', 'view');
                            $join->where('activities.role', '=', 'Consumer');
                            $join->where('activities.group', '=', 'mobile-ci');
                            $join->where('activities.location_id', '=', $merchant_id);
                            $join->where("activities.created_at", '>=', $start_date);
                            $join->where("activities.created_at", '<=', $end_date);
                        })
                        ->groupBy('news.news_id')
                        ->orderBy('score', 'DESC')
                        ->take($take);
                        $flag_type = true;
                        break;

                // show events
                case 'events':
                        $query = EventModel::select(
                            DB::raw("count(distinct {$tablePrefix}activities.activity_id)/ (
                                select
                                    count(ac.activity_id) as total
                                from
                                    {$tablePrefix}events ev
                                        inner join
                                    {$tablePrefix}activities ac ON ev.event_id = ac.event_id
                                where ac.module_name = 'Event'
                                and ac.activity_name = 'event_view'
                                and ac.activity_type = 'view'
                                and ac.role = 'Consumer'
                                and ac.group = 'mobile-ci'
                                and ac.location_id = '{$merchant_id}'
                                and DATE_FORMAT(ac.created_at, '%Y-%m-%d %H:%i:%s') >= '{$start_date}'
                                and DATE_FORMAT(ac.created_at, '%Y-%m-%d %H:%i:%s') <= '{$end_date}'
                            ) * 100 as percentage"),
                            DB::raw("count(distinct {$tablePrefix}activities.activity_id) as score"),
                            "events.event_name as name",
                            "events.event_id as object_id"
                        )
                        ->join("activities", function ($join) use ($merchant_id, $start_date, $end_date) {
                            $join->on('events.event_id', '=', 'activities.event_id');
                            $join->where('activities.activity_name', '=', 'event_view');
                            $join->where('activities.module_name', '=', 'Event');
                            $join->where('activities.activity_type', '=', 'view');
                            $join->where('activities.role', '=', 'Consumer');
                            $join->where('activities.group', '=', 'mobile-ci');
                            $join->where('activities.location_id', '=', $merchant_id);
                            $join->where("activities.created_at", '>=', $start_date);
                            $join->where("activities.created_at", '<=', $end_date);
                        })
                        ->groupBy('events.event_id')
                        ->orderBy('score', 'DESC')
                        ->take($take);
                        $flag_type = true;
                        break;

                // show promotions
                case 'promotions':
                        $query = News::select(
                            DB::raw("count(distinct {$tablePrefix}activities.activity_id)/ (
                                select
                                    count(ac.activity_id) as total
                                from
                                    {$tablePrefix}news ne
                                        inner join
                                    {$tablePrefix}activities ac ON ne.news_id = ac.news_id
                                where ac.module_name = 'Promotion'
                                and ac.activity_name = 'view_promotion'
                                and ac.activity_type = 'view'
                                and ac.role = 'Consumer'
                                and ac.group = 'mobile-ci'
                                and ac.location_id = '{$merchant_id}'
                                and DATE_FORMAT(ac.created_at, '%Y-%m-%d %H:%i:%s') >= '{$start_date}'
                                and DATE_FORMAT(ac.created_at, '%Y-%m-%d %H:%i:%s') <= '{$end_date}'
                            ) * 100 as percentage"),
                            DB::raw("count(distinct {$tablePrefix}activities.activity_id) as score"),
                            "news.news_name as name",
                            "news.news_id as object_id"
                        )
                        ->join("activities", function ($join) use ($merchant_id, $start_date, $end_date) {
                            $join->on('news.news_id', '=', 'activities.news_id');
                            $join->where('news.object_type', '=', 'promotion');
                            $join->where('activities.activity_name', '=', 'view_promotion'); 
                            $join->where('activities.module_name', '=', 'Promotion');
                            $join->where('activities.activity_type', '=', 'view');
                            $join->where('activities.role', '=', 'Consumer');
                            $join->where('activities.group', '=', 'mobile-ci');
                            $join->where('activities.location_id', '=', $merchant_id);
                            $join->where("activities.created_at", '>=', $start_date);
                            $join->where("activities.created_at", '<=', $end_date);
                        })
                        ->groupBy('news.news_id')
                        ->orderBy('score', 'DESC')
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

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.dashboard.getgeneralcustomerview.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
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

            $news = Activity::select(DB::raw("count(distinct activity_id) as total"))
                            ->where('activities.activity_name', '=', 'view_news')
                            ->where('activities.module_name', '=', 'News')
                            ->where('activities.activity_type', '=', 'view')
                            ->where('activities.role', '=', 'Consumer')
                            ->where('activities.group', '=', 'mobile-ci');

            $events = Activity::select(DB::raw("count(distinct activity_id) as total"))
                            ->where('activities.activity_name', '=', 'event_view')
                            ->where('activities.module_name', '=', 'Event')
                            ->where('activities.activity_type', '=', 'view')
                            ->where('activities.role', '=', 'Consumer')
                            ->where('activities.group', '=', 'mobile-ci');

            $promotions = Activity::select(DB::raw("count(distinct activity_id) as total"))
                            ->where('activities.activity_name', '=', 'view_promotion')
                            ->where('activities.module_name', '=', 'Promotion')
                            ->where('activities.activity_type', '=', 'view')
                            ->where('activities.role', '=', 'Consumer')
                            ->where('activities.group', '=', 'mobile-ci');

            // for now lucky draws is not count
            $empty_lucky_draws = new stdclass();
            $empty_lucky_draws->total = 0;
            $lucky_draws = $empty_lucky_draws;

            OrbitInput::get('merchant_id', function ($merchant_id) use ($news, $promotions, $events) {
                $news->where('activities.location_id', '=', $merchant_id);
                $promotions->where('activities.location_id', '=', $merchant_id);
                $events->where('activities.location_id', '=', $merchant_id);
            });

            OrbitInput::get('start_date', function ($beginDate) use ($news, $promotions, $events) {
                $news->where('activities.created_at', '>=', $beginDate);
                $promotions->where('activities.created_at', '>=', $beginDate);
                $events->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($news, $promotions, $events) {
                $news->where('activities.created_at', '<=', $endDate);
                $promotions->where('activities.created_at', '<=', $endDate);
                $events->where('activities.created_at', '<=', $endDate);
            });

            $news = $news->first();
            $events = $events->first();
            $promotions = $promotions->first();

            $news->label = 'News';
            $events->label = 'Events';
            $promotions->label = 'Promotions';
            $lucky_draws->label = 'Lucky Draws';

            $data = new stdclass();
            $data->news = $news;
            $data->events = $events;
            $data->promotions = $promotions;
            $data->lucky_draws = $lucky_draws;

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

            $multiple_period = false;

            if (OrbitInput::get('periods', null) !== null) {
                $multiple_period = true;
                $validator = null;
                $periods_json = OrbitInput::get('periods', '{}');
                $periods = @json_decode($periods_json, JSON_OBJECT_AS_ARRAY);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument('invalid json for periods');
                }
                foreach ($periods as $dates) {

                    $rules = 'required|date_format:Y-m-d H:i:s';
                    $merchant_id = OrbitInput::get('merchant_id');
                    $type = OrbitInput::get('type');
                    $object_id = OrbitInput::get('object_id');

                    $validator = Validator::make(
                        array(
                            'merchant_id' => $merchant_id,
                            'type'        => $type,
                            'object_id'   => $object_id,
                            'start_date'  => $dates['start_date'],
                            'end_date'    => $dates['end_date'],
                        ),
                        array(
                            'merchant_id' => 'required',
                            'type'        => 'required',
                            'object_id'   => 'required',
                            'start_date'  => $rules,
                            'end_date'    => $rules,
                        )
                    );
                }

            } else {
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
                        'type'         => 'required',
                        'object_id'    => 'required',
                        'start_date'   => 'required|date_format:Y-m-d H:i:s',
                        'end_date'     => 'required|date_format:Y-m-d H:i:s'
                    )
                );
                $periods = [
                    [
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                    ]
                ];
            }

            Event::fire('orbit.dashboard.getdetailtopcustomerview.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getdetailtopcustomerview.after.validation', array($this, $validator));

            $tablePrefix = DB::getTablePrefix();

            //dd($periods);
            $responses = [];
            switch ($type) {

                // show news
                case 'news':
                        foreach ($periods as $period) {

                            $start_date = $period['start_date'];
                            $end_date = $period['end_date'];

                            $query = Activity::select(DB::raw("count(distinct {$tablePrefix}activities.activity_id) as score"))
                                ->where('activities.activity_name', '=', 'view_news')
                                ->where('activities.module_name', '=', 'News')
                                ->where('activities.activity_type', '=', 'view')
                                ->where('activities.role', '=', 'Consumer')
                                ->where('activities.group', '=', 'mobile-ci')
                                ->where('activities.location_id', '=', $merchant_id)
                                ->where('activities.object_id', '=', $object_id)
                                ->where("activities.created_at", '>=', $start_date)
                                ->where("activities.created_at", '<=', $end_date)
                                ->first();

                            $result = (int)$query->score;

                            $responses[] = [
                                'start_date' => $start_date,
                                'end_date' => $end_date,
                                'score' => $result
                            ];
                        }
                        break;

                // show events
                case 'events':
                        foreach ($periods as $period) {

                            $start_date = $period['start_date'];
                            $end_date = $period['end_date'];

                            $query = Activity::select(DB::raw("count(distinct {$tablePrefix}activities.activity_id) as score"))
                                ->where('activities.activity_name', '=', 'event_view')
                                ->where('activities.module_name', '=', 'Event')
                                ->where('activities.activity_type', '=', 'view')
                                ->where('activities.role', '=', 'Consumer')
                                ->where('activities.group', '=', 'mobile-ci')
                                ->where('activities.location_id', '=', $merchant_id)
                                ->where('activities.object_id', '=', $object_id)
                                ->where("activities.created_at", '>=', $start_date)
                                ->where("activities.created_at", '<=', $end_date)
                                ->first();

                            $result = (int)$query->score;

                            $responses[] = [
                                'start_date' => $start_date,
                                'end_date' => $end_date,
                                'score' => $result
                            ];
                        }
                        break;

                // show promotions
                case 'promotions':
                        foreach ($periods as $period) {

                            $start_date = $period['start_date'];
                            $end_date = $period['end_date'];

                            $query = Activity::select(DB::raw("count(distinct {$tablePrefix}activities.activity_id) as score"))
                                ->where('activities.activity_name', '=', 'view_promotion') 
                                ->where('activities.module_name', '=', 'Promotion')
                                ->where('activities.activity_type', '=', 'view')
                                ->where('activities.role', '=', 'Consumer')
                                ->where('activities.group', '=', 'mobile-ci')
                                ->where('activities.location_id', '=', $merchant_id)
                                ->where('activities.object_id', '=', $object_id)
                                ->where("activities.created_at", '>=', $start_date)
                                ->where("activities.created_at", '<=', $end_date)
                                ->first();

                            $result = (int)$query->score;

                            $responses[] = [
                                'start_date' => $start_date,
                                'end_date' => $end_date,
                                'score' => $result
                            ];
                        }
                        break;

                // by default do nothing
                default:
                    $this->response->message = Lang::get('statuses.orbit.nodata.object');
                    $responses = null;
            }


            if ($multiple_period) {
                $this->response->data = $responses;
            } else {
                $this->response->data = $responses[0];
            }

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

            $widgets = Activity::considerCustomer()->select(
                    "widgets.widget_type",
                    DB::raw("count(distinct {$tablePrefix}activities.activity_id) as click_count")
                )
                ->join('widgets', 'widgets.widget_id', '=', 'activities.object_id' )
                ->groupBy('widgets.widget_type');

            $isReport = $this->builderOnly;
            OrbitInput::get('is_report', function ($_isReport) use (&$isReport, $widgets, $tablePrefix) {
                $isReport = !!$_isReport;
            });

            OrbitInput::get('merchant_id', function ($merchantId) use ($widgets) {
                $widgets->whereIn('activities.location_id', $this->getArray($merchantId));
            });

            OrbitInput::get('begin_date', function ($beginDate) use ($widgets) {
                $widgets->where('activities.created_at', '>=', $beginDate);
            });

            OrbitInput::get('end_date', function ($endDate) use ($widgets) {
                $widgets->where('activities.created_at', '<=', $endDate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_widgets = clone $widgets;

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

            $summary  = NULL;
            $lastPage = false;
            if ($isReport)
            {
                $_widgets->addSelect(
                    DB::raw("date({$tablePrefix}activities.created_at) as created_at_date")
                );
                $_widgets->groupBy('created_at_date');

                $widgetReportQuery = $_widgets->getQuery();

                $defaultSelect = [
                    DB::raw("ifnull(sum(case widget_type when 'tenant' then click_count end), 0) as 'tenant'"),
                    DB::raw("ifnull(sum(case widget_type when 'coupon' then click_count end), 0) as 'coupon'"),
                    DB::raw("ifnull(sum(case widget_type when 'news' then click_count end), 0) as 'news'"),
                    DB::raw("ifnull(sum(case widget_type when 'promotion' then click_count end), 0) as 'promotion'"),
                    DB::raw("ifnull(sum(click_count), 0) as 'total'")
                ];

                $toSelect     = array_merge($defaultSelect, ["created_at_date"]);
                $widgetReport = DB::table(DB::raw("({$_widgets->toSql()}) as report"))
                    ->mergeBindings($widgetReportQuery)
                    ->select($toSelect)
                    ->groupBy('created_at_date');

                $_widgetReport = clone $widgetReport;

                $widgetReport->orderBy('created_at_date', 'desc');
                $summaryReport = DB::table(DB::raw("({$_widgets->toSql()}) as report"))
                    ->mergeBindings($widgetReportQuery)
                    ->select($defaultSelect);

                if ($this->builderOnly)
                {
                    return $this->builderObject($widgetReport, $summaryReport);
                }

                $widgetReport->take($take)->skip($skip);


                $totalReport = DB::table(DB::raw("({$_widgetReport->toSql()}) as total_report"))
                    ->mergeBindings($_widgetReport);

                $widgetTotal = $totalReport->count();
                $widgetList  = $widgetReport->get();

                // Consider Last Page
                if (($widgetTotal - $take) <= $skip)
                {
                    $summary  = $summaryReport->first();
                    $lastPage = true;
                }

            } else {
                $widgets->take($take);
                $widgetTotal = RecordCounter::create($_widgets)->count();
                $widgetList  = $widgets->get();
            }


            $data = new stdclass();
            $data->total_records = $widgetTotal;
            $data->returned_records = count($widgetList);
            $data->last_page = $lastPage;
            $data->summary   = $summary;
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
     * @param string   `sortby`                (optional) - Column order by. Valid value: registered_date, promotion_name, promotion_type, description, begin_date, end_date, status.
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
                    'sort_by' => 'in:promotion_id,promotion_name,begin_date,end_date,is_auto_issue_on_signup,retailer_name,total_redeemed,total_issued,coupon_status,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.couponreportgeneral_sortby'),
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
            $coupons = Coupon::select('promotions.promotion_id', 'promotions.merchant_id as mall_id', 'promotions.is_coupon', 'promotions.promotion_name',
                                      'promotions.begin_date', 'promotions.end_date', 'merchants.name as retailer_name',
                                      DB::raw("CASE {$prefix}promotion_rules.rule_type WHEN 'auto_issue_on_signup' THEN 'Y' ELSE 'N' END as 'is_auto_issue_on_signup'"),
                                      DB::raw("issued.*"),
                                      DB::raw("redeemed.*"),
                                      DB::raw("CASE WHEN {$prefix}promotions.end_date IS NOT NULL THEN
                                                    CASE WHEN
                                                        DATE_FORMAT({$prefix}promotions.end_date, '%Y-%m-%d %H:%i:%s') = '0000-00-00 00:00:00' THEN {$prefix}promotions.status
                                                    WHEN
                                                        {$prefix}promotions.end_date < '{$now}' THEN 'expired'
                                                    ELSE
                                                        {$prefix}promotions.status
                                                    END
                                                ELSE
                                                    {$prefix}promotions.status
                                                END as 'coupon_status'"), 'promotions.status')
                            ->join('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin(DB::raw("(select ic.promotion_id, count(ic.promotion_id) as total_issued
                                              from {$prefix}issued_coupons ic
                                              where ic.status = 'active' or ic.status = 'redeemed'
                                              group by promotion_id) issued"),
                            // On
                            DB::raw('issued.promotion_id'), '=', 'promotions.promotion_id')

                            ->join(DB::raw("(select promotion_id, redeem_retailer_id, count(promotion_id) as total_redeemed
                                                from {$prefix}issued_coupons ic
                                                where ic.status = 'redeemed'
                                                group by promotion_id, redeem_retailer_id) redeemed"),
                            // On
                            DB::raw('redeemed.promotion_id'), '=', 'promotions.promotion_id')

                            ->join('merchants', 'merchants.merchant_id', '=', DB::raw('redeemed.redeem_retailer_id'));

            // Filter by mall id
            OrbitInput::get('mall_id', function($mallId) use ($coupons, $configMallId) {
                $coupons->where('promotions.merchant_id', $mallId);
            });

            // Filter by Promotion ID
            OrbitInput::get('promotion_id', function($pid) use ($coupons) {
                $pid = (array)$pid;
                $coupons->whereIn('promotions.promotion_id', $pid);
            });

            // Filter by is coupon flag
            OrbitInput::get('is_coupon', function($isCoupon) use ($coupons) {
                $isCoupon = (array)$isCoupon;
                $coupons->whereIn('promotions.is_coupon', $isCoupon);
            });

            // Filter by Promotion Name
            OrbitInput::get('promotion_name_like', function($name) use ($coupons) {
                $coupons->where('promotions.promotion_name', 'like', "%$name%");
            });

            // Filter by Retailer name
            OrbitInput::get('retailer_name_like', function($name) use ($coupons) {
                $coupons->where('merchants.name', 'like', "%$name%");
            });

            // Filter by auto issue on sign up
            OrbitInput::get('is_auto_issue_on_signup', function($auto) use ($coupons, $prefix) {
                $auto = (array)$auto;
                $coupons->whereIn(DB::raw("CASE {$prefix}promotion_rules.rule_type WHEN 'auto_issue_on_signup' THEN 'Y' ELSE 'N' END"), $auto);
            });

            // Filter by coupon status with expired
            OrbitInput::get('coupon_status', function($status) use ($coupons, $prefix, $now) {
                $status = (array)$status;
                $coupons->whereIn(DB::raw("CASE WHEN {$prefix}promotions.end_date IS NOT NULL THEN
                                                    CASE WHEN
                                                        DATE_FORMAT({$prefix}promotions.end_date, '%Y-%m-%d %H:%i:%s') = '0000-00-00 00:00:00' THEN {$prefix}promotions.status
                                                    WHEN
                                                        {$prefix}promotions.end_date < '{$now}' THEN 'expired'
                                                    ELSE
                                                        {$prefix}promotions.status
                                                    END
                                                ELSE
                                                    {$prefix}promotions.status
                                                END"), $status);
            });

            // Filter by coupon campaign status
            OrbitInput::get('status', function($status) use ($coupons) {
                $status = (array)$status;
                $coupons->whereIn('promotions.status', $status);
            });

            // Filter by date
            // Less Than Equals
            OrbitInput::get('begin_date_lte', function($date) use ($coupons) {
                $coupons->where('promotions.begin_date', '<=', $date);
            });

            // Greater Than Equals
            OrbitInput::get('begin_date_gte', function($date) use ($coupons) {
                $coupons->where('promotions.begin_date', '>=', $date);
            });

            // Less Than Equals
            OrbitInput::get('end_date_lte', function($date) use ($coupons) {
                $coupons->where('promotions.end_date', '<=', $date);
            });

            // Greater Than Equals
            OrbitInput::get('end_date_gte', function($date) use ($coupons) {
                $coupons->where('promotions.end_date', '>=', $date);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;
            $_coupons->select('promotions.promotion_id');

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
            $sortBy = 'coupon_status';

            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'promotion_id'              => 'promotions.promotion_id',
                    'mall_id'                   => 'mall_id',
                    'is_coupon'                 => 'is_coupon',
                    'promotion_name'            => 'promotions.promotion_name',
                    'begin_date'                => 'promotions.begin_date',
                    'end_date'                  => 'promotions.end_date',
                    'is_auto_issue_on_signup'   => 'is_auto_issue_on_signup',
                    'total_issued'              => 'total_issued',
                    'redeem_retailer_id'        => 'redeem_retailer_id',
                    'retailer_name'             => 'retailer_name',
                    'total_redeemed'            => 'total_redeemed',
                    'coupon_status'             => 'coupon_status',
                    'status'                    => 'promotions.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            // sort by status first
            if ($sortBy !== 'coupon_status') {
                $coupons->orderBy('coupon_status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $coupons->orderBy($sortBy, $sortMode);

            // also to sort tenant name
            if ($sortBy !== 'retailer_name') {
                $coupons->orderBy('retailer_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $coupons, 'count' => RecordCounter::create($_coupons)->count()];
            }

            $totalCoupons = RecordCounter::create($_coupons)->count();
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
     * @param string   `sortby`                (optional) - Column order by. Valid value: registered_date, promotion_name, promotion_type, description, begin_date, end_date, status.
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
                    'sort_by' => 'in:promotion_id,promotion_name,begin_date,end_date,is_auto_issue_on_signup,retailer_name,total_redeemed,total_issued,coupon_status,status,all_redeemed',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.couponreportgeneral_sortby'),
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
                                            ->where('merchants.parent_id', $configMallId);

            $coupons = Coupon::select('promotions.merchant_id as mall_id',
                                      'merchants.name as retailer_name',
                                      'merchants.logo as tenant_logo',
                                      DB::raw("sum(total_redeemed) as all_redeemed, sum(total_redeemed)/{$total_all_redeem}*100 as percentage"))
                                    ->join('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                                    ->leftJoin(DB::raw("(select ic.promotion_id, count(ic.promotion_id) as total_issued
                                                      from {$prefix}issued_coupons ic
                                                      where ic.status = 'active' or ic.status = 'redeemed'
                                                      group by promotion_id) issued"),
                                                // On
                                                DB::raw('issued.promotion_id'), '=', 'promotions.promotion_id')

                                                ->join(DB::raw("(select promotion_id, redeem_retailer_id, count(promotion_id) as total_redeemed
                                                                    from {$prefix}issued_coupons ic
                                                                    where ic.status = 'redeemed'
                                                                    group by promotion_id, redeem_retailer_id) redeemed"),
                                                // On
                                                DB::raw('redeemed.promotion_id'), '=', 'promotions.promotion_id')

                                                ->join('merchants', 'merchants.merchant_id', '=', DB::raw('redeemed.redeem_retailer_id'));

            // Filter by mall id
            OrbitInput::get('merchant_id', function($mallId) use ($coupons, $configMallId) {
                $coupons->where('promotions.merchant_id', $mallId);
            });

            // Filter by is coupon flag
            OrbitInput::get('is_coupon', function($isCoupon) use ($coupons) {
                $isCoupon = (array)$isCoupon;
                $coupons->whereIn('promotions.is_coupon', $isCoupon);
            });

            // Filter by Retailer name
            OrbitInput::get('retailer_name_like', function($name) use ($coupons) {
                $coupons->where('merchants.name', 'like', "%$name%");
            });

            // Filter by auto issue on sign up
            OrbitInput::get('is_auto_issue_on_signup', function($auto) use ($coupons, $prefix) {
                $auto = (array)$auto;
                $coupons->whereIn(DB::raw("CASE {$prefix}promotion_rules.rule_type WHEN 'auto_issue_on_signup' THEN 'Y' ELSE 'N' END"), $auto);
            });

            // Filter by coupon campaign status
            OrbitInput::get('status', function($status) use ($coupons) {
                $status = (array)$status;
                $coupons->whereIn('promotions.status', $status);
            });

            // Filter by date
            // Less Than Equals
            OrbitInput::get('begin_date_lte', function($date) use ($coupons) {
                $coupons->where('promotions.begin_date', '<=', $date);
            });

            // Greater Than Equals
            OrbitInput::get('begin_date_gte', function($date) use ($coupons) {
                $coupons->where('promotions.begin_date', '>=', $date);
            });

            // Less Than Equals
            OrbitInput::get('end_date_lte', function($date) use ($coupons) {
                $coupons->where('promotions.end_date', '<=', $date);
            });

            // Greater Than Equals
            OrbitInput::get('end_date_gte', function($date) use ($coupons) {
                $coupons->where('promotions.end_date', '>=', $date);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;
            $_coupons->select('promotions.promotion_id')->groupBy('redeem_retailer_id');

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
            $sortBy = 'all_redeemed';

            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'promotion_id'              => 'promotions.promotion_id',
                    'mall_id'                   => 'mall_id',
                    'is_coupon'                 => 'is_coupon',
                    'promotion_name'            => 'promotions.promotion_name',
                    'begin_date'                => 'promotions.begin_date',
                    'end_date'                  => 'promotions.end_date',
                    'is_auto_issue_on_signup'   => 'is_auto_issue_on_signup',
                    'total_issued'              => 'total_issued',
                    'redeem_retailer_id'        => 'redeem_retailer_id',
                    'retailer_name'             => 'retailer_name',
                    'total_redeemed'            => 'total_redeemed',
                    'status'                    => 'promotions.status',
                    'all_redeemed'              => 'all_redeemed'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            // sort by status first
            if ($sortBy !== 'all_redeemed') {
                $coupons->orderBy('all_redeemed', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $coupons->orderBy($sortBy, $sortMode);
            $coupons->groupBy('redeem_retailer_id');

            // also to sort tenant name
            if ($sortBy !== 'retailer_name') {
                $coupons->orderBy('retailer_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $coupons, 'count' => RecordCounter::create($_coupons)->count()];
            }

            $totalCoupons = RecordCounter::create($_coupons)->count();
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
                        ->allowedForUser($user)
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
}