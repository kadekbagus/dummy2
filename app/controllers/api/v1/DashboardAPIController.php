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
            $total_all_redeem = IssuedCoupon::where('status', 'redeemed')
                                            ->count();
            $coupons = Coupon::select('promotions.merchant_id as mall_id',
                                      'merchants.name as retailer_name',
                                      DB::raw("sum(total_redeemed) as all_redeemed, sum(total_redeemed)/{$total_all_redeem}*100 as percentage"),
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
                                                END as 'coupon_status'"))
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
                    'status'                    => 'promotions.status',
                    'all_redeemed'              => 'all_redeemed'
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
}
