<?php
/**
 * An API controller for managing Coupon report.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Carbon\Carbon as Carbon;

class CouponReportAPIController extends ControllerAPI
{
    protected $couponReportViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'mall customer service', 'campaign admin'];
    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    /**
     * GET - Coupon Report List
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Tian <tian@dominopos.com>
     *
     */
    public function getCouponReport()
    {
        $reportType = OrbitInput::get('report_type');

        switch ($reportType)
        {
            case 'by-coupon-name':
                return $this->getCouponReportByCouponName();
                break;

            case 'by-tenant':
                return $this->getCouponReportByTenant();
                break;

            case 'issued-coupon':
                return $this->getIssuedCouponReport();
                break;

            case 'coupon-summary':
                return $this->getCouponSummaryReport();
                break;

            case 'list-coupon':
            default:
                return $this->getCouponReportGeneral();
                break;
        }
    }

    /**
     * GET - Coupon Report List
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
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
    public function getCouponReportGeneral()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.couponreport.getcouponreportgeneral.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.couponreport.getcouponreportgeneral.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.couponreport.getcouponreportgeneral.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_coupon_report')) {
                Event::fire('orbit.couponreport.getcouponreportgeneral.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon_report');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponReportViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.couponreport.getcouponreportgeneral.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $current_mall = OrbitInput::get('current_mall');
            $start_validity_date = OrbitInput::get('start_validity_date');
            $end_validity_date = OrbitInput::get('end_validity_date');


            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'current_mall' => $current_mall,
                    'sort_by' => $sort_by,
                ),

                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'sort_by' => 'in:promotion_id,promotion_name,begin_date,total_location,coupon_validity_in_date,total_tenant,mall_name,rule_type,total_issued,total_redeemed,campaign_status,order',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.couponreportgeneral_sortby'),
                )
            );

            Event::fire('orbit.couponreport.getcouponreportgeneral.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.couponreport.getcouponreportgeneral.after.validation', array($this, $validator));

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

            $mall = App::make('orbit.empty.mall');
            $now = Carbon::now($mall->timezone->timezone_name);
            $now_ymd = $now->toDateString();

            $timezone = $this->getTimezone($mall->merchant_id);

            // Get now date with timezone
            $timezoneOffset = $this->getTimezoneOffset($timezone);

            // get prefix DB
            $prefix = DB::getTablePrefix();

            $coupons = Coupon::allowedForPMPUser($user, 'coupon')->select(
                                        'promotions.promotion_id',
                                        'promotions.promotion_id as campaign_id',
                                        'promotions.promotion_name',
                                        'promotions.begin_date',
                                        'promotions.end_date',
                                        'promotions.coupon_validity_in_date',
                                        'promotion_rules.rule_type',
                                        DB::raw("'coupon' as campaign_type"),
                                        DB::raw("IFNULL(issued.total_issued, 0) AS total_issued"),
                                        DB::raw("IFNULL(redeemed.total_redeemed, 0) AS total_redeemed"),
                                        DB::raw("IFNULL(coupons_code.total_coupons, 0) as available"),
                                        'promotions.updated_at',
                                        DB::raw("(select GROUP_CONCAT(IF({$prefix}merchants.object_type = 'tenant', CONCAT({$prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ',{$prefix}merchants.name)) separator ', ') from {$prefix}promotion_retailer
                                        left join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}promotion_retailer.retailer_id
                                        left join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                        where {$prefix}promotion_retailer.promotion_id = {$prefix}promotions.promotion_id) as campaign_location_names"),
                                        DB::raw("COUNT(DISTINCT {$prefix}promotion_retailer.promotion_retailer_id) as total_location"),
                                        DB::raw("CASE WHEN {$prefix}promotions.end_date < {$this->quote($now)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END AS campaign_status"),
                                        'campaign_status.order'
                                        )
                                        // Join rules
                                        ->join('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                                        // Left Join for get campaign_status
                                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                        // Left Join for get campaign_status
                                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                                        // Left Join for get total issued
                                        ->leftJoin(DB::raw("(select ic_issued.promotion_id, count(ic_issued.promotion_id) as total_issued
                                                          FROM {$prefix}issued_coupons ic_issued
                                                          WHERE ic_issued.status = 'issued'
                                                          GROUP BY promotion_id) issued"),
                                            // On
                                            DB::raw('issued.promotion_id'), '=', 'promotions.promotion_id')
                                        // Left Join for get total redeem
                                        ->leftJoin(DB::raw("(select ic_redeemed.promotion_id, count(ic_redeemed.promotion_id) as total_redeemed
                                                            FROM {$prefix}issued_coupons ic_redeemed
                                                            WHERE ic_redeemed.status = 'redeemed'
                                                            and (ic_redeemed.redeem_retailer_id != 'NULL' OR ic_redeemed.redeem_user_id != 'NULL')
                                                            GROUP BY promotion_id
                                                        ) redeemed"),
                                            // On
                                            DB::raw('redeemed.promotion_id'), '=', 'promotions.promotion_id')
                                        // Left Join for get total coupon code
                                        ->leftJoin(DB::raw("(select ic_code.promotion_id, count(ic_code.issued_coupon_code) as total_coupons
                                                          FROM {$prefix}issued_coupons ic_code
                                                          WHERE ic_code.status = 'available'
                                                          GROUP BY promotion_id) coupons_code"),
                                            // On
                                            DB::raw('coupons_code.promotion_id'), '=', 'promotions.promotion_id');

            // Filter by Promotion Name
            OrbitInput::get('promotion_name', function($name) use ($coupons) {
                $coupons->where('promotions.promotion_name', 'like', "%$name%");
            });

            // Filter by Tenant Name
            OrbitInput::get('tenant_name', function($tenant_name) use ($coupons) {
                $coupons->whereHas('linkToTenants', function($q) use ($tenant_name) {
                    $q->where('merchants.name', 'like', "%$tenant_name%");
                });
            });

            // Filter coupon merchants by mall name
            // There is laravel bug regarding nested whereHas on the same table like in this case
            // news->tenant->mall : whereHas('tenant', function($q) { $q->whereHas('mall' ...)}) this is not gonna work
            OrbitInput::get('mall_name', function ($mall_name) use ($coupons, $prefix) {
                $quote = function($arg)
                {
                    return DB::connection()->getPdo()->quote($arg);
                };
                $mall_name = "%" . $mall_name . "%";
                $mall_name = $quote($mall_name);
                $coupons->whereRaw(DB::raw("
                ((
                    (select count(mtenant.merchant_id) from {$prefix}merchants mtenant
                    inner join {$prefix}promotion_retailer opr on mtenant.merchant_id = opr.retailer_id
                    where mtenant.object_type = 'tenant' and opr.promotion_id = {$prefix}promotions.promotion_id and (
                        select count(mtenant.merchant_id) from {$prefix}merchants mmall
                        where mmall.object_type = 'mall' and
                        mtenant.parent_id = mmall.merchant_id and
                        mmall.name like {$mall_name} and
                        mmall.object_type = 'mall'
                    ) >= 1 and
                    mtenant.object_type = 'tenant' and
                    mtenant.is_mall = 'no' and
                    opr.object_type = 'tenant') >= 1
                )
                OR
                (
                    select count(mmallx.merchant_id) from {$prefix}merchants mmallx
                    inner join {$prefix}promotion_retailer oprx on mmallx.merchant_id = oprx.retailer_id
                    where mmallx.object_type = 'mall' and
                    oprx.promotion_id = {$prefix}promotions.promotion_id and
                    mmallx.name like {$mall_name} and
                    mmallx.object_type = 'mall'
                ))
                "));
            });


            //Filter With Checkbox
            //Filter by Campaign Status
            OrbitInput::get('campaign_status', function ($statuses) use ($coupons, $prefix, $now) {
                $coupons->whereIn(DB::raw("CASE WHEN {$prefix}promotions.end_date < {$this->quote($now)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END"), $statuses);
            });

            //Filter by Coupon Rule
            OrbitInput::get('rule_type', function($rule_type) use ($coupons) {
                $coupons->whereIn('rule_type', (array)$rule_type);
            });

            // Filter by mall id
            OrbitInput::get('mall_id', function($mallId) use ($coupons) {
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

            // Filter by coupon campaign status
            OrbitInput::get('status', function($status) use ($coupons) {
                $status = (array)$status;
                $coupons->whereIn('promotions.status', $status);
            });

            // Filter by validate date
            if ($start_validity_date != '' && $end_validity_date != '') {
                $coupons->whereRaw("coupon_validity_in_date between ? and ?", [$start_validity_date, $end_validity_date]);
            }

            // Grouping after filter
            $coupons->groupBy('promotions.promotion_id');


            OrbitInput::get('with', function ($with) use ($coupons) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'tenants') {
                        $coupons->with('tenants');
                    } elseif ($relation === 'tenants.mall') {
                        $coupons->with('tenants.mall');
                    } elseif ($relation === 'campaignLocations') {
                        $coupons->with('campaignLocations');
                    } elseif ($relation === 'campaignLocations.mall') {
                        $coupons->with('campaignLocations.mall');
                    }
                }
            });


            // Clone the query builder which still does not include the take,
            $_coupons = clone $coupons;

            // Need to sub select after group by
            $_coupons_sql = $_coupons->toSql();

            //Cek exist binding
            if (count($coupons->getBindings()) > 0) {
                foreach($coupons->getBindings() as $binding)
                {
                  $value = is_numeric($binding) ? $binding : $this->quote($binding);
                  $_coupons_sql = preg_replace('/\?/', $value, $_coupons_sql, 1);
                }
            }

            $_coupons = DB::table(DB::raw('(' . $_coupons_sql . ') as b'));

            $query_sum = array(
                'COUNT(promotion_id) AS total_record',
                'SUM(total_redeemed) AS total_redeemed',
                'SUM(total_issued) AS total_issued'
            );

            $total = $_coupons->selectRaw(implode(',', $query_sum))->get();

            // Get total issued
            $totalIssued = isset($total[0]->total_issued)?$total[0]->total_issued:0;
            // Get total redeemed
            $totalRedeemed = isset($total[0]->total_redeemed)?$total[0]->total_redeemed:0;
            // Get total record
            $totalRecord = (int) isset($total[0]->total_record)?$total[0]->total_record:0;


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

            // skip, and order by
            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $coupons)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            // If request page from export (print/csv), showing without page limitation
            $export = OrbitInput::get('export');

            if (!isset($export)) {
                $coupons->take($take);
                $coupons->skip($skip);
            }

            // Default sort by
            $sortBy = 'promotions.promotion_name';

            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'promotion_id'            => 'promotions.promotion_id',
                    'promotion_name'          => 'promotions.promotion_name',
                    'begin_date'              => 'promotions.begin_date',
                    'coupon_validity_in_date' => 'promotions.coupon_validity_in_date',
                    'total_tenant'            => 'total_tenant',
                    'mall_name'               => 'mall_name',
                    'total_location'          => 'total_location',
                    'rule_type'               => 'rule_type',
                    'total_issued'            => 'total_issued',
                    'total_redeemed'          => 'total_redeemed',
                    'campaign_status'         => 'campaign_status',
                    'order'                   => 'order',
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

            // also to sort tenant name
            if ($sortBy !== 'promotion_name') {
                $coupons->orderBy('promotion_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return [
                            'builder' => $coupons,
                            'count' => $totalRecord,
                            'total_issued' => $totalIssued,
                            'total_redeemed' => $totalRedeemed,
                        ];
            }

            $listOfCoupons = $coupons->get();

            $data = new stdclass();
            $data->total_records = $totalRecord;
            $data->returned_records = count($listOfCoupons);
            $data->total_redeemed = $totalRedeemed;
            $data->total_issued = $totalIssued;
            $data->records = $listOfCoupons;

            if ($totalRecord == 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.couponreport.getcouponreportgeneral.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.couponreport.getcouponreportgeneral.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.couponreport.getcouponreportgeneral.query.error', array($this, $e));

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
            Event::fire('orbit.couponreport.getcouponreportgeneral.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.couponreport.getcouponreportgeneral.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Coupon Summary Report
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - Column order by. Valid value: .
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponSummaryReport()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.couponreport.getcouponsummaryreport.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.couponreport.getcouponsummaryreport.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.couponreport.getcouponsummaryreport.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_coupon_report')) {
                Event::fire('orbit.couponreport.getcouponsummaryreport.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon_report');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponReportViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.couponreport.getcouponsummaryreport.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $configMallId = OrbitInput::get('current_mall');

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'current_mall' => $configMallId,
                    'sort_by' => $sort_by,
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'sort_by' => 'in:promotion_id,promotion_name,begin_date,end_date,is_auto_issue_on_signup,total_redeemed,total_issued,coupon_status,status,campaign_status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.couponsummaryreport_sortby'),
                )
            );

            Event::fire('orbit.couponreport.getcouponsummaryreport.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.couponreport.getcouponsummaryreport.after.validation', array($this, $validator));

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

            // create date filter
            $summaryBeginDate = trim(OrbitInput::get('summary_begin_date'));
            $summaryEndDate = trim(OrbitInput::get('summary_end_date'));

            // prevent sql injection
            $beginDate = DB::connection()->getPdo()->quote($summaryBeginDate);
            $endDate = DB::connection()->getPdo()->quote($summaryEndDate);

            // create date filter for issued
            $dateFilterForIssued = '';
            if (($summaryBeginDate !== '') && ($summaryEndDate !== '')) {
                $dateFilterForIssued = 'and (ic.issued_date >= ' . $beginDate . ' and ic.issued_date <= ' . $endDate . ')';
            } elseif (($summaryBeginDate !== '') && ($summaryEndDate === '')) {
                $dateFilterForIssued = 'and (ic.issued_date >= ' . $beginDate . ')';
            }

            // create date filter for redeemed
            $dateFilterForRedeemed = '';
            if (($summaryBeginDate !== '') && ($summaryEndDate !== '')) {
                $dateFilterForRedeemed = 'and (ic.redeemed_date >= ' . $beginDate . ' and ic.redeemed_date <= ' . $endDate . ')';
            } elseif (($summaryBeginDate !== '') && ($summaryEndDate === '')) {
                $dateFilterForRedeemed = 'and (ic.redeemed_date >= ' . $beginDate . ')';
            }

            $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                ->where('merchants.merchant_id','=', $configMallId)
                ->first();

            $now = Carbon::now($timezone->timezone_name);

            // Builder object
            $now = date('Y-m-d H:i:s');
            $prefix = DB::getTablePrefix();
            $coupons = Coupon::select('promotions.promotion_id', 'promotions.merchant_id as mall_id', 'promotions.is_coupon', 'promotions.promotion_name',
                                      'promotions.begin_date', 'promotions.end_date', 'campaign_status.order',
                                      DB::raw("CASE WHEN {$prefix}promotions.end_date < {$this->quote($now)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END  AS campaign_status"),
                                      DB::raw("CASE {$prefix}promotion_rules.rule_type WHEN 'auto_issue_on_signup' THEN 'Y' ELSE 'N' END as 'is_auto_issue_on_signup'"),
                                      DB::raw("CASE WHEN issued.total_issued IS NULL THEN 0 ELSE issued.total_issued END AS total_issued"),
                                      DB::raw("CASE WHEN redeemed.total_redeemed IS NULL THEN 0 ELSE redeemed.total_redeemed END AS total_redeemed"),
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
                            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                            ->leftJoin(DB::raw("(select ic.promotion_id AS promotionid, count(ic.promotion_id) as total_issued
                                                from {$prefix}issued_coupons ic
                                                left join {$prefix}users u on ic.issuer_user_id = u.user_id
                                                left join {$prefix}employees e on u.user_id = e.user_id
                                                left join {$prefix}employee_retailer er on e.employee_id = er.employee_id
                                                left join {$prefix}merchants mall on ic.issuer_retailer_id = mall.merchant_id
                                                where (ic.status = 'active' or ic.status = 'redeemed')
                                                AND (
                                                    er.retailer_id = {$this->quote($configMallId)}
                                                    OR
                                                    mall.merchant_id = {$this->quote($configMallId)}
                                                )
                                                " . $dateFilterForIssued .
                                                " group by ic.promotion_id) issued"),
                            // On
                            DB::raw('issued.promotionid'), '=', 'promotions.promotion_id')

                            ->leftJoin(DB::raw("(select ic.promotion_id AS promotionid, redeem_retailer_id, count(promotion_id) as total_redeemed, redeem_user_id
                                                from {$prefix}issued_coupons ic
                                                left join {$prefix}users u on ic.redeem_user_id = u.user_id
                                                left join {$prefix}employees e on u.user_id = e.user_id
                                                left join {$prefix}employee_retailer er on e.employee_id = er.employee_id
                                                left join {$prefix}merchants tenant on ic.redeem_retailer_id = tenant.merchant_id
                                                where ic.status = 'redeemed'
                                                AND (
                                                    er.retailer_id = {$this->quote($configMallId)}
                                                    OR
                                                    tenant.parent_id = {$this->quote($configMallId)}
                                                )
                                                " . $dateFilterForRedeemed .
                                                " group by ic.promotion_id) redeemed"),
                            // On
                            DB::raw('redeemed.promotionid'), '=', 'promotions.promotion_id')
                            ->leftJoin('promotion_retailer as pr', DB::Raw('pr.promotion_id'), '=', 'promotions.promotion_id')
                            ->leftJoin('merchants as m', DB::Raw('m.merchant_id'), '=', DB::Raw('pr.retailer_id'))
                            ->where(function($q) {
                                $q->whereNotNull('total_issued')
                                  ->orWhereNotNull('total_redeemed');
                            });

            // Filter by mall id
            OrbitInput::get('mall_id', function($mallId) use ($coupons, $configMallId) {
                // $coupons->where('promotions.merchant_id', $mallId);
                $coupons->where(function ($q) use ($mallId) {
                    $q->where(DB::Raw('m.merchant_id'), '=', $mallId)
                      ->orWhere(DB::Raw('m.parent_id'), '=', $mallId);
                });
            });

            // Disabled, so coupon that not linked to current mall but redemeed on this mall could be displayed
            // OrbitInput::get('merchant_id', function($mallId) use ($coupons, $configMallId) {
            //     $coupons->where(function ($q) use ($mallId) {
            //         $q->where(DB::Raw('m.merchant_id'), '=', $mallId)
            //           ->orWhere(DB::Raw('m.parent_id'), '=', $mallId);
            //     });
            // });

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

            // Filter by coupon status
            OrbitInput::get('status', function($status) use ($coupons) {
                $status = (array)$status;
                $coupons->whereIn('promotions.status', $status);
            });

            // Filter by coupon by campaign_status
            OrbitInput::get('campaign_status', function ($statuses) use ($coupons, $prefix, $now) {
                $statuses = (array)$statuses;
                $coupons->whereIn(DB::raw("CASE WHEN {$prefix}promotions.end_date < {$this->quote($now)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END"), $statuses);
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
            $_coupons->select('promotions.promotion_id')->groupBy('promotions.promotion_id');


            $_couponsCountReddem = clone $coupons;
            $_couponsCountIssued = clone $coupons;

            $_coupons->select('promotions.promotion_id');

            $_couponsCountReddem = $_couponsCountReddem->get();
            $_couponsCountIssued = $_couponsCountIssued->groupBy('promotions.promotion_id')->get();

            // Get total reddem
            $totalRedeemed = 0 ;
            if (isset($_couponsCountReddem) && count($_couponsCountReddem) > 0){
                foreach ($_couponsCountReddem as $key => $valReddem) {
                    $totalRedeemed = $totalRedeemed + $valReddem->total_redeemed;
                }
            }

            // Get total issued coupon
            $totalIssued = 0;
            if (isset($_couponsCountIssued) && count($_couponsCountIssued) > 0){
                foreach ($_couponsCountIssued as $key => $valIssued) {
                    $totalIssued = $totalIssued + $valIssued->total_issued;
                }
            }

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
            $sortBy = 'promotions.status';

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
                    'total_redeemed'            => 'total_redeemed',
                    'coupon_status'             => 'coupon_status',
                    'status'                    => 'campaign_status',
                    'campaign_status'           => 'campaign_status',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $coupons->groupBy('promotions.promotion_id');
            $coupons->orderBy($sortBy, $sortMode);

            // sort by status first
            if ($sortBy !== 'promotion_name') {
                $coupons->orderBy('promotion_name', 'asc');
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
            $data->total_redeemed = $totalRedeemed;
            $data->total_issued = $totalIssued;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.couponreport.getcouponsummaryreport.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.couponreport.getcouponsummaryreport.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.couponreport.getcouponsummaryreport.query.error', array($this, $e));

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
            Event::fire('orbit.couponreport.getcouponsummaryreport.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.couponreport.getcouponsummaryreport.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Coupon Report List
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Tian <tian@dominopos.com>
     * @author Irianto Pratama <irianto@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - Column order by. Valid value: registered_date, promotion_name, promotion_type, description, begin_date, end_date, status.
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param string   `redeemed_by            (optional) - Filtering redeemed by cs or tenant
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponReportByCouponName()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.couponreport.getcouponreportbycouponname.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.couponreport.getcouponreportbycouponname.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.couponreport.getcouponreportbycouponname.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_coupon_report')) {
                Event::fire('orbit.couponreport.getcouponreportbycouponname.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon_report');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponReportViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.couponreport.getcouponreportbycouponname.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');

            $configMallId = OrbitInput::get('current_mall');

            $redeemedBy = OrbitInput::get('redeemed_by');

            $validator = Validator::make(
                array(
                    'current_mall' => $configMallId,
                    'sort_by' => $sort_by
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'sort_by' => 'in:redeem_retailer_name,total_redeemed,issued_coupon_code,user_email,redeemed_date,redeem_verification_code',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.couponreportbycouponname_sortby'),
                )
            );

            Event::fire('orbit.couponreport.getcouponreportbycouponname.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.couponreport.getcouponreportbycouponname.after.validation', array($this, $validator));

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
            // $coupons = null;

            if ($redeemedBy === 'tenant') {
                $coupons = IssuedCoupon::select('issued_coupons.*', 'merchants.name AS redeem_retailer_name', 'users.user_email',
                                          DB::raw("issued.*"),
                                          DB::raw("redeemed.*"))
                                ->join('users', 'users.user_id', '=', 'issued_coupons.user_id')
                                ->join('promotions', 'promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                                ->leftJoin(DB::raw("(select ic.promotion_id, count(ic.promotion_id) as total_issued
                                                  from {$prefix}issued_coupons ic
                                                  where ic.status = 'active' or ic.status = 'redeemed'
                                                  group by promotion_id) issued"),
                                // On
                                DB::raw('issued.promotion_id'), '=', 'issued_coupons.promotion_id')
                                ->join(DB::raw("(select promotion_id, redeem_retailer_id, user_id, count(promotion_id) as total_redeemed
                                                    from {$prefix}issued_coupons ic
                                                    where ic.status = 'redeemed'
                                                    and ic.redeem_retailer_id IS NOT NULL
                                                    group by promotion_id, redeem_retailer_id, user_id) redeemed"), function($join) {
                                                        // On
                                                        $join->on(DB::raw('redeemed.promotion_id'), '=', 'issued_coupons.promotion_id')
                                                             ->on(DB::raw('redeemed.redeem_retailer_id'), '=', 'issued_coupons.redeem_retailer_id')
                                                             ->on(DB::raw('redeemed.user_id'), '=', 'issued_coupons.user_id');
                                                    })

                                ->join('merchants', 'merchants.merchant_id', '=', 'issued_coupons.redeem_retailer_id')
                                ->where('issued_coupons.status', 'redeemed')
                                ->whereNotNull('issued_coupons.redeem_retailer_id');

            } elseif ($redeemedBy === 'cs') {
                $coupons = IssuedCoupon::select('issued_coupons.*', 'users.user_email',
                                        DB::raw('cs.user_firstname AS redeem_retailer_name'),
                                        DB::raw("issued.*"),
                                        DB::raw("redeemed.user_id"))
                                ->join('users', 'users.user_id', '=', 'issued_coupons.user_id')
                                ->join('users as cs', DB::raw('cs.user_id'), '=', 'issued_coupons.redeem_user_id')
                                ->join('promotions', 'promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                                ->leftJoin(DB::raw("(select ic.promotion_id, count(ic.promotion_id) as total_issued
                                                  from {$prefix}issued_coupons ic
                                                  where ic.status = 'active' or ic.status = 'redeemed'
                                                  group by promotion_id) issued"),
                                // On
                                DB::raw('issued.promotion_id'), '=', 'issued_coupons.promotion_id')
                                ->join(DB::raw("(select promotion_id, redeem_user_id, user_id, count(promotion_id) as total_redeemed
                                                    from {$prefix}issued_coupons ic
                                                    where ic.status = 'redeemed'
                                                    and ic.redeem_user_id IS NOT NULL
                                                    group by promotion_id, redeem_user_id, user_id) redeemed"), function($join) {
                                                        // On
                                                        $join->on(DB::raw('redeemed.promotion_id'), '=', 'issued_coupons.promotion_id')
                                                             ->on(DB::raw('redeemed.user_id'), '=', 'issued_coupons.user_id');
                                                    })
                                ->where('issued_coupons.status', 'redeemed')
                                ->whereNotNull('issued_coupons.redeem_user_id')
                                ->groupBy('issued_coupons.issued_coupon_id');
            }

            if ($user->isSuperAdmin()) {
                // Filter by mall id
                OrbitInput::get('mall_id', function($mallId) use ($coupons) {
                    $coupons->where('promotions.merchant_id', $mallId);
                });

                OrbitInput::get('merchant_id', function($mallId) use ($coupons) {
                    $coupons->where('promotions.merchant_id', $mallId);
                });
            } else {
                $coupons->where('promotions.merchant_id', $configMallId);
            }

            // Filter by Promotion ID
            OrbitInput::get('promotion_id', function($pid) use ($coupons) {
                $pid = (array)$pid;
                $coupons->whereIn('issued_coupons.promotion_id', $pid);
            });

            // Filter by Promotion Name
            OrbitInput::get('promotion_name_like', function($name) use ($coupons) {
                $coupons->where('promotions.promotion_name', 'like', "%$name%");
            });

            // Filter by Retailer name
            OrbitInput::get('redeem_retailer_name_like', function($name) use ($coupons, $redeemedBy) {
                if ($redeemedBy === 'tenant') {
                    $coupons->where('merchants.name', 'like', "%$name%");
                } elseif ($redeemedBy === 'cs') {
                    $coupons->where(DB::raw('cs.user_firstname'), 'like', "%$name%");
                }
            });

            // Filter by Retailer name
            OrbitInput::get('retailer_name_like', function($name) use ($coupons, $redeemedBy) {
                if ($redeemedBy === 'tenant') {
                    $coupons->where('merchants.name', 'like', "%$name%");
                } elseif ($redeemedBy === 'cs') {
                    $coupons->where(DB::raw('cs.user_firstname'), 'like', "%$name%");
                }
            });

            // Filter by Coupon Code
            OrbitInput::get('issued_coupon_code', function($code) use ($coupons) {
                $coupons->where('issued_coupons.issued_coupon_code', 'like', "%$code%");
            });

            // Filter by Verification Code
            OrbitInput::get('redeem_verification_code', function($code) use ($coupons) {
                $coupons->where('issued_coupons.redeem_verification_code', 'like', "%$code%");
            });

            // Filter by Email
            OrbitInput::get('user_email', function($email) use ($coupons) {
                $coupons->where('users.user_email', 'like', "%$email%");
            });

            // Filter by Redeemed date
            // Greater Than Equals
            OrbitInput::get('redeemed_date_gte', function($date) use ($coupons) {
                $coupons->where('issued_coupons.redeemed_date', '>=', $date);
            });
            // Less Than Equals
            OrbitInput::get('redeemed_date_lte', function($date) use ($coupons) {
                $coupons->where('issued_coupons.redeemed_date', '<=', $date);
            });

            // Filter by total_issued
            OrbitInput::get('total_issued', function($data) use ($coupons) {
                $coupons->where('total_issued', $data);
            });

            // Filter by total_redeemed
            OrbitInput::get('total_redeemed', function($data) use ($coupons) {
                $coupons->where('total_redeemed', $data);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;
            $_coupons->select('issued_coupons.issued_coupon_id');

            // if not printing / exporting data then do pagination.
            if (! $this->returnBuilder) {
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
            }

            // Default sort by
            $sortBy = 'redeem_retailer_name';

            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy, $redeemedBy)
            {
                if ($redeemedBy === 'tenant') {
                    // Map the sortby request to the real column name
                    $sortByMapping = array(
                        'redeem_retailer_name'      => 'merchants.name',
                        'redeemed_date'             => 'issued_coupons.redeemed_date',
                        'redeem_verification_code'  => 'issued_coupons.redeem_verification_code',
                        'issued_coupon_code'        => 'issued_coupons.issued_coupon_code',
                        'user_email'                => 'users.user_email',
                        'total_issued'              => 'total_issued',
                        'total_redeemed'            => 'total_redeemed'
                    );
                } elseif ($redeemedBy === 'cs') {
                    $sortByMapping = array(
                        'redeem_retailer_name'      => 'redeem_retailer_name',
                        'redeemed_date'             => 'issued_coupons.redeemed_date',
                        'redeem_verification_code'  => 'issued_coupons.redeem_verification_code',
                        'issued_coupon_code'        => 'issued_coupons.issued_coupon_code',
                        'user_email'                => 'users.user_email',
                        'total_issued'              => 'total_issued',
                        'total_redeemed'            => 'total_redeemed'
                    );
                }

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $coupons->orderBy($sortBy, $sortMode);

            // include sorting user_email
            if ($sortBy !== 'users.user_email') {
                $coupons->orderBy('users.user_email', 'asc');
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
            Event::fire('orbit.couponreport.getcouponreportbycouponname.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.couponreport.getcouponreportbycouponname.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.couponreport.getcouponreportbycouponname.query.error', array($this, $e));

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
            Event::fire('orbit.couponreport.getcouponreportbycouponname.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.couponreport.getcouponreportbycouponname.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Coupon Report By Tenant
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - Column order by. Valid value: registered_date, promotion_name, promotion_type, description, begin_date, end_date, status.
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param string   `redeemed_by            (optional) - Filtering redeemed by cs or tenant
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponReportByTenant()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.couponreport.getcouponreportbytenant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.couponreport.getcouponreportbytenant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.couponreport.getcouponreportbytenant.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_coupon_report')) {
                Event::fire('orbit.couponreport.getcouponreportbytenant.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon_report');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponReportViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.couponreport.getcouponreportbytenant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');

            $configMallId = OrbitInput::get('current_mall');

            $redeemedBy = OrbitInput::get('redeemed_by');

            $validator = Validator::make(
                array(
                    'current_mall' => $configMallId,
                    'sort_by' => $sort_by
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'sort_by' => 'in:promotion_id,promotion_name,begin_date,end_date,user_email,issued_coupon_code,redeemed_date,issued_date,redeem_verification_code,total_issued,total_redeemed,gender,age,redemption_place,status,user_type,user_email',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.couponreportbytenant_sortby'),
                )
            );

            Event::fire('orbit.couponreport.getcouponreportbytenant.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.couponreport.getcouponreportbytenant.after.validation', array($this, $validator));

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
            //$now = date('Y-m-d H:i:s');
            $prefix = DB::getTablePrefix();

            $mall = App::make('orbit.empty.mall');
            $timezone = $this->getTimezone($mall->merchant_id);

            // Change Now Date to Mall Time
            $now = Carbon::now($mall->timezone->timezone_name);
            $now = $now->toDateString();

            if ($redeemedBy === 'tenant') {
                $coupons = IssuedCoupon::select('issued_coupons.*', 'promotions.promotion_name', 'merchants.name AS redeem_retailer_name', 'users.user_email',
                                      DB::raw("issued.*"),
                                      DB::raw("redeemed.*"))
                            ->join('users', 'users.user_id', '=', 'issued_coupons.user_id')
                            ->join('promotions', 'promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                            ->join('merchants', 'merchants.merchant_id', '=', 'issued_coupons.redeem_retailer_id')
                            ->leftJoin(DB::raw("(select ic.promotion_id, count(ic.promotion_id) as total_issued
                                              from {$prefix}issued_coupons ic
                                              where ic.status = 'active' or ic.status = 'redeemed'
                                              group by promotion_id) issued"),
                            // On
                            DB::raw('issued.promotion_id'), '=', 'issued_coupons.promotion_id')

                            ->join(DB::raw("(select promotion_id, redeem_retailer_id, user_id, count(promotion_id) as total_redeemed
                                                from {$prefix}issued_coupons ic
                                                where ic.status = 'redeemed'
                                                group by promotion_id, redeem_retailer_id, user_id) redeemed"), function($join) {

                                                    // On
                                                    $join->on(DB::raw('redeemed.promotion_id'), '=', 'issued_coupons.promotion_id')
                                                         ->on(DB::raw('redeemed.redeem_retailer_id'), '=', 'issued_coupons.redeem_retailer_id')
                                                         ->on(DB::raw('redeemed.user_id'), '=', 'issued_coupons.user_id');
                                                })
                            ->where('issued_coupons.status', 'redeemed');
            } elseif ($redeemedBy === 'cs') {
                $coupons = IssuedCoupon::select('issued_coupons.*', 'promotions.promotion_name', 'users.user_email',
                                    DB::raw('cs.user_firstname AS redeem_retailer_name'),
                                    DB::raw("issued.*"),
                                    DB::raw("redeemed.user_id"))
                            ->join('users', 'users.user_id', '=', 'issued_coupons.user_id')
                            ->join('promotions', 'promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                            ->join('users as cs', DB::raw('cs.user_id'), '=', 'issued_coupons.redeem_user_id')
                            ->leftJoin(DB::raw("(select ic.promotion_id, count(ic.promotion_id) as total_issued
                                              from {$prefix}issued_coupons ic
                                              where ic.status = 'active' or ic.status = 'redeemed'
                                              group by promotion_id) issued"),
                            // On
                            DB::raw('issued.promotion_id'), '=', 'issued_coupons.promotion_id')
                            ->join(DB::raw("(select promotion_id, redeem_retailer_id, user_id, count(promotion_id) as total_redeemed
                                                from {$prefix}issued_coupons ic
                                                where ic.status = 'redeemed'
                                                and ic.redeem_user_id IS NOT NULL
                                                group by promotion_id, redeem_retailer_id, user_id) redeemed"), function($join) {

                                                    // On
                                                    $join->on(DB::raw('redeemed.promotion_id'), '=', 'issued_coupons.promotion_id')
                                                         ->on(DB::raw('redeemed.user_id'), '=', 'issued_coupons.user_id');
                                                })
                            ->where('issued_coupons.status', 'redeemed');
            } elseif ($redeemedBy === 'all') {
                $coupons = IssuedCoupon::select('issued_coupons.*', 'promotions.begin_date', 'promotions.end_date',
                                            DB::raw("
                                                        CASE
                                                        WHEN {$prefix}user_details.gender = 'f' THEN 'female'
                                                        WHEN {$prefix}user_details.gender = 'm' THEN 'male'
                                                        WHEN ({$prefix}user_details.gender IS NULL AND {$prefix}issued_coupons.user_id IS NOT NULL) THEN 'unknown'
                                                        ELSE null END AS gender
                                             "),
                                            DB::raw("
                                                        CASE
                                                        WHEN {$prefix}roles.role_name = 'Consumer'
                                                        THEN 'User' ELSE {$prefix}roles.role_name
                                                        END AS user_type
                                                "),
                                            DB::raw("
                                                        CASE
                                                        WHEN (TIMESTAMPDIFF(YEAR, {$prefix}user_details.birthdate, CURDATE()) IS NOT NULL AND {$prefix}issued_coupons.user_id IS NOT NULL)THEN TIMESTAMPDIFF(YEAR, {$prefix}user_details.birthdate, CURDATE())
                                                        WHEN (TIMESTAMPDIFF(YEAR, {$prefix}user_details.birthdate, CURDATE()) IS NULL AND {$prefix}issued_coupons.user_id IS NOT NULL ) THEN 'unknown'
                                                        ELSE null
                                                        END AS age
                                                    "),
                                            DB::raw("
                                                        CASE WHEN {$prefix}issued_coupons.redeem_user_id IS NOT NULL THEN CONCAT({$prefix}users.user_firstname, ' ', {$prefix}users.user_lastname, ' at ', om_employee.name)
                                                        ELSE CONCAT({$prefix}merchants.name, ' at ', om_parent.name)
                                                        END AS redemption_place
                                                "))
                                       ->join('promotions', 'promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                                       ->leftJoin('user_details', 'user_details.user_id', '=', 'issued_coupons.user_id')
                                       ->leftJoin('merchants', 'merchants.merchant_id', '=', 'issued_coupons.redeem_retailer_id')
                                       ->leftJoin('users', 'users.user_id', '=', 'issued_coupons.redeem_user_id')

                                        // join for get <redemtion_place>_<at>_<mall_name>
                                       ->leftJoin('merchants as om_parent', DB::raw('om_parent.merchant_id'), '=', 'merchants.parent_id')
                                       ->leftJoin('employees as oe', DB::raw('oe.user_id'), '=', 'issued_coupons.redeem_user_id')
                                       ->leftJoin('employee_retailer as oer', DB::raw('oer.employee_id'), '=', DB::raw('oe.employee_id'))
                                       ->leftJoin('merchants as om_employee', DB::raw('om_employee.merchant_id'), '=', DB::raw('oer.retailer_id'))

                                       // joint for get user role(guest or customer) and user_email
                                       ->leftJoin('users as ou', DB::raw('ou.user_id'), '=', 'issued_coupons.user_id')
                                       ->leftJoin('roles', 'roles.role_id', '=', DB::raw('ou.user_role_id'));
            }

            //GET active days from campaign histories
            $couponId = OrbitInput::get('promotion_id');
            $promoId = $couponId[0];

            // Calculate campaign activate days from campaign daily spending
            $activeDays = CampaignDailySpending::select(DB::raw("count(distinct date) as total"))
                ->where('campaign_id', '=', $couponId)
                ->where('campaign_status', 'activate');

            if ($user->isSuperAdmin()) {
                // Filter by mall id
                OrbitInput::get('mall_id', function($mallId) use ($coupons) {
                    $coupons->where('promotions.merchant_id', $mallId);
                });

                OrbitInput::get('merchant_id', function($mallId) use ($coupons) {
                    $coupons->where('promotions.merchant_id', $mallId);
                });
            }

            // Filter by Promotion ID
            OrbitInput::get('promotion_id', function($pid) use ($coupons) {
                $pid = (array)$pid;
                $coupons->whereIn('issued_coupons.promotion_id', $pid);
            });

            // Filter by Promotion Name
            OrbitInput::get('promotion_name_like', function($name) use ($coupons) {
                $coupons->where('promotions.promotion_name', 'like', "%$name%");
            });

            // Filter by redeem_retailer_id
            OrbitInput::get('redeem_retailer_id', function($data) use ($coupons) {
                $data = (array)$data;
                $coupons->whereIn('issued_coupons.redeem_retailer_id', $data);
            });

            // Filter by redeem_retailer_id
            OrbitInput::get('redeem_user_id', function($data) use ($coupons) {
                $data = (array)$data;
                $coupons->whereIn('issued_coupons.redeem_user_id', $data);
            });

            // Filter by Retailer name
            OrbitInput::get('retailer_name_like', function($name) use ($coupons) {
                $coupons->where('merchants.name', 'like', "%$name%");
            });

            // Filter by Retailer name
            OrbitInput::get('redeem_retailer_name_like', function($name) use ($coupons) {
                $coupons->where('merchants.name', 'like', "%$name%");
            });

            // Filter by Coupon Code
            OrbitInput::get('issued_coupon_code', function($code) use ($coupons) {
                $coupons->where('issued_coupons.issued_coupon_code', 'like', "%$code%");
            });

            // Filter by Verification Code
            OrbitInput::get('redeem_verification_code', function($code) use ($coupons) {
                $coupons->where('issued_coupons.redeem_verification_code', 'like', "%$code%");
            });

            // Filter by Email
            OrbitInput::get('user_email', function($email) use ($coupons) {
                $coupons->where('users.user_email', 'like', "%$email%");
            });

            // Filter by Redeemed date
            // Greater Than Equals
            OrbitInput::get('issued_date_gte', function($date) use ($coupons, $timezone, $activeDays) {
                $coupons->where('issued_coupons.issued_date', '>=', $date);
                $activeDays->where('date', '>=', date("Y-m-d", strtotime($date)));
            });
            // Less Than Equals
            OrbitInput::get('issued_date_lte', function($date) use ($coupons, $timezone, $activeDays) {
                $coupons->where('issued_coupons.issued_date', '<=', $date);
                $activeDays->where('date', '<=', date("Y-m-d", strtotime($date)));
            });

            // Filter by Redeemed date
            // Greater Than Equals
            OrbitInput::get('redeemed_date_gte', function($date) use ($coupons, $timezone, $activeDays) {
                $coupons->where('issued_coupons.redeemed_date', '>=', $date);
                $activeDays->where('date', '>=', date("Y-m-d", strtotime($date)));
            });
            // Less Than Equals
            OrbitInput::get('redeemed_date_lte', function($date) use ($coupons, $timezone, $activeDays) {
                $coupons->where('issued_coupons.redeemed_date', '<=', $date);
                $activeDays->where('date', '<=', date("Y-m-d", strtotime($date)));
            });

            // Filter by total_issued
            OrbitInput::get('total_issued', function($data) use ($coupons) {
                $coupons->where('total_issued', $data);
            });

            // Filter by total_redeemed
            OrbitInput::get('total_redeemed', function($data) use ($coupons) {
                $coupons->where('total_redeemed', $data);
            });

            // Filter by age
            OrbitInput::get('customer_age', function($age) use ($coupons, $prefix) {
                $coupons->where(DB::raw("IFNULL(timestampdiff(year, {$prefix}user_details.birthdate, curdate()), 'unknown')"), $age);
            });

            // Filter by redemption place
            OrbitInput::get('redemption_place', function($place) use ($coupons, $prefix) {
                $coupons->whereRaw("(
                                        CONCAT({$prefix}users.user_firstname, ' ', {$prefix}users.user_lastname, ' at ', om_employee.name) like '%{$place}%'
                                        OR
                                        CONCAT({$prefix}merchants.name, ' at ', om_parent.name) like '%{$place}%'
                                    )");
            });

            // Filter by gender
            OrbitInput::get('customer_gender', function($gender) use ($coupons, $prefix) {
                $coupons->whereIn(DB::raw("CASE WHEN {$prefix}user_details.gender = 'f' THEN 'female' WHEN {$prefix}user_details.gender = 'm' THEN 'male' ELSE 'unknown' END"), $gender);
            });

            // Filter by status
            OrbitInput::get('status', function($data) use ($coupons) {
                $coupons->where('issued_coupons.status', $data);
            });

            // Clone the query builder which still does not include the take,
            $_coupons = clone $coupons;

            // Need to sub select after group by
            $_coupons_sql = $_coupons->toSql();

            //Cek exist binding
            if (count($coupons->getBindings()) > 0) {
                foreach($coupons->getBindings() as $binding)
                {
                  $value = is_numeric($binding) ? $binding : $this->quote($binding);
                  $_coupons_sql = preg_replace('/\?/', $value, $_coupons_sql, 1);
                }
            }

            $_coupons = DB::table(DB::raw('(' . $_coupons_sql . ') as b'));

            $listActiveDays = $activeDays->first();

            $query_sum = array(
                "COUNT(issued_coupon_id) AS total_record",
                "COUNT(DISTINCT(user_id)) AS total_acquiring_customers",
                "COUNT(DISTINCT(redemption_place)) AS total_redemption_place"
            );

            $total = $_coupons->selectRaw(implode(',', $query_sum))->get();

            // Get total record
            $totalRecord = isset($total[0]->total_record)?$total[0]->total_record:0;
            // Get total acquiring customers
            $totalAcquiringCustomers = isset($total[0]->total_acquiring_customers)?$total[0]->total_acquiring_customers:0;
            // Get total active days
            $totalActiveDays = isset($listActiveDays->total)?$listActiveDays->total:0;
            // Get total redemption place
            $totalRedemptionPlace = isset($total[0]->total_redemption_place)?$total[0]->total_redemption_place:0;

            // if not printing / exporting data then do pagination.
            if (! $this->returnBuilder) {
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
            }

            // Default sort by
            $sortBy = 'issued_coupons.issued_date';

            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'promotion_id'             => 'promotions.promotion_id',
                    'promotion_name'           => 'promotions.promotion_name',
                    'redeem_retailer_name'     => 'merchants.name',
                    'redeemed_date'            => 'issued_coupons.redeemed_date',
                    'issued_date'              => 'issued_coupons.issued_date',
                    'redeem_verification_code' => 'issued_coupons.redeem_verification_code',
                    'issued_coupon_code'       => 'issued_coupons.issued_coupon_code',
                    'user_email'               => 'users.user_email',
                    'total_issued'             => 'total_issued',
                    'total_redeemed'           => 'total_redeemed',
                    'gender'                   => 'gender',
                    'age'                      => 'age',
                    'redemption_place'         => 'redemption_place',
                    'user_type'                => 'user_type',
                    'user_email'               => DB::raw("ou.user_email"),
                    'status'                   => 'issued_coupons.status',
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

            // include sorting coupon code
            if ($sortBy !== 'issued_coupons.issued_coupon_code') {
                $coupons->orderBy('issued_coupons.issued_coupon_code', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return [
                            'builder' => $coupons,
                            'count' => $totalRecord,
                            'total_coupons' => $totalRecord,
                            'total_acquiring_customers' => $totalAcquiringCustomers,
                            'total_active_days' => $totalActiveDays,
                            'total_redemption_place' => $totalRedemptionPlace,
                        ];
            }

            $listOfCoupons = $coupons->get();

            $data = new stdclass();
            $data->total_records = $totalRecord;
            $data->returned_records = count($listOfCoupons);
            $data->total_coupons = $totalRecord;
            $data->total_acquiring_customers = $totalAcquiringCustomers;
            $data->total_active_days = $totalActiveDays;
            $data->total_redemption_place = $totalRedemptionPlace;
            $data->records = $listOfCoupons;

            if ($totalRecord === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.couponreport.getcouponreportbytenant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.couponreport.getcouponreportbytenant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.couponreport.getcouponreportbytenant.query.error', array($this, $e));

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
            Event::fire('orbit.couponreport.getcouponreportbytenant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.couponreport.getcouponreportbytenant.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Issued Coupon Report
     *
     * @author Tian <tian@dominopos.com>
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
    public function getIssuedCouponReport()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.couponreport.getissuedcouponreport.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.couponreport.getissuedcouponreport.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.couponreport.getissuedcouponreport.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_coupon_report')) {
                Event::fire('orbit.couponreport.getissuedcouponreport.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon_report');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponReportViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.couponreport.getissuedcouponreport.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');

            $configMallId = OrbitInput::get('current_mall');

            $validator = Validator::make(
                array(
                    'current_mall' => $configMallId,
                    'sort_by' => $sort_by
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'sort_by' => 'in:promotion_id,promotion_name,begin_date,end_date,is_auto_issue_on_signup,user_email,issued_coupon_code,issued_date,total_issued,maximum_issued_coupon,coupon_status,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.issuedcouponreport_sortby'),
                )
            );

            Event::fire('orbit.couponreport.getissuedcouponreport.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.couponreport.getissuedcouponreport.after.validation', array($this, $validator));

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
            $coupons = IssuedCoupon::select('issued_coupons.*', 'promotions.*', 'users.user_email',
                                  DB::raw("CASE {$prefix}promotion_rules.rule_type WHEN 'auto_issue_on_signup' THEN 'Y' ELSE 'N' END as 'is_auto_issue_on_signup'"),
                                  DB::raw("issued.*"),
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
                                            END as 'coupon_status'")
                                  )
                        ->join('users', 'users.user_id', '=', 'issued_coupons.user_id')
                        ->join('promotions', 'promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                        ->join('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin(DB::raw("(select ic.promotion_id, count(ic.promotion_id) as total_issued
                                          from {$prefix}issued_coupons ic
                                          where ic.status = 'active' or ic.status = 'redeemed'
                                          group by promotion_id) issued"),
                            // On
                            DB::raw('issued.promotion_id'), '=', 'issued_coupons.promotion_id')
                        ->where(function($q) {
                            $q->where('issued_coupons.status', 'active')
                              ->orWhere('issued_coupons.status', 'redeemed');
                        })
                        ;

            if ($user->isSuperAdmin()) {
                // Filter by mall id
                OrbitInput::get('mall_id', function($mallId) use ($coupons) {
                    $coupons->where('promotions.merchant_id', $mallId);
                });
            } else {
                $coupons->where('promotions.merchant_id', $configMallId);
            }

            // Filter by Promotion ID
            OrbitInput::get('promotion_id', function($pid) use ($coupons) {
                $pid = (array)$pid;
                $coupons->whereIn('issued_coupons.promotion_id', $pid);
            });

            // Filter by Promotion Name
            OrbitInput::get('promotion_name_like', function($name) use ($coupons) {
                $coupons->where('promotions.promotion_name', 'like', "%$name%");
            });

            // Filter by promotion begin date and end date
            // Greater Than Equals
            OrbitInput::get('begin_date_gte', function($date) use ($coupons) {
                $coupons->where('promotions.begin_date', '>=', $date);
            });

            // Less Than Equals
            OrbitInput::get('begin_date_lte', function($date) use ($coupons) {
                $coupons->where('promotions.begin_date', '<=', $date);
            });

            // Greater Than Equals
            OrbitInput::get('end_date_gte', function($date) use ($coupons) {
                $coupons->where('promotions.end_date', '>=', $date);
            });

            // Less Than Equals
            OrbitInput::get('end_date_lte', function($date) use ($coupons) {
                $coupons->where('promotions.end_date', '<=', $date);
            });

            // Filter by redeem_retailer_id
            OrbitInput::get('redeem_retailer_id', function($data) use ($coupons) {
                $data = (array)$data;
                $coupons->whereIn('issued_coupons.redeem_retailer_id', $data);
            });

            // Filter by Coupon Code
            OrbitInput::get('issued_coupon_code', function($code) use ($coupons) {
                $coupons->where('issued_coupons.issued_coupon_code', 'like', "%$code%");
            });

            // Filter by Verification Code
            OrbitInput::get('redeem_verification_code', function($code) use ($coupons) {
                $coupons->where('issued_coupons.redeem_verification_code', 'like', "%$code%");
            });

            // Filter by Email
            OrbitInput::get('user_email', function($email) use ($coupons) {
                $coupons->where('users.user_email', 'like', "%$email%");
            });

            // Filter by Issued date
            // Greater Than Equals
            OrbitInput::get('issued_date_gte', function($date) use ($coupons) {
                $coupons->where('issued_coupons.issued_date', '>=', $date);
            });
            // Less Than Equals
            OrbitInput::get('issued_date_lte', function($date) use ($coupons) {
                $coupons->where('issued_coupons.issued_date', '<=', $date);
            });

            // Filter by Redeemed date
            // Greater Than Equals
            OrbitInput::get('redeemed_date_gte', function($date) use ($coupons) {
                $coupons->where('issued_coupons.redeemed_date', '>=', $date);
            });
            // Less Than Equals
            OrbitInput::get('redeemed_date_lte', function($date) use ($coupons) {
                $coupons->where('issued_coupons.redeemed_date', '<=', $date);
            });

            // Filter by total_issued
            OrbitInput::get('total_issued', function($data) use ($coupons) {
                $coupons->where('total_issued', $data);
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

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;
            $_coupons->select('issued_coupons.issued_coupon_id');

            // if not printing / exporting data then do pagination
            if (! $this->returnBuilder) {
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
            }

            // Default sort by
            $sortBy = 'promotions.promotion_name';

            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'promotion_id'              => 'promotions.promotion_id',
                    'promotion_name'            => 'promotions.promotion_name',
                    'begin_date'                => 'promotions.begin_date',
                    'end_date'                  => 'promotions.end_date',
                    'maximum_issued_coupon'     => 'promotions.maximum_issued_coupon',
                    'is_auto_issue_on_signup'   => 'is_auto_issue_on_signup',
                    'issued_date'               => 'issued_coupons.issued_date',
                    'redeemed_date'             => 'issued_coupons.redeemed_date',
                    'redeem_verification_code'  => 'issued_coupons.redeem_verification_code',
                    'issued_coupon_code'        => 'issued_coupons.issued_coupon_code',
                    'user_email'                => 'users.user_email',
                    'total_issued'              => 'total_issued',
                    'total_redeemed'            => 'total_redeemed',
                    'coupon_status'             => 'coupon_status',
                    'status'                    => 'promotions.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            // sort by status first
            if ($sortBy !== 'promotions.status') {
                $coupons->orderBy('promotions.status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $coupons->orderBy($sortBy, $sortMode);

            // include sorting user_email
            if ($sortBy !== 'users.user_email') {
                $coupons->orderBy('users.user_email', 'asc');
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
            Event::fire('orbit.couponreport.getissuedcouponreport.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.couponreport.getissuedcouponreport.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.couponreport.getissuedcouponreport.query.error', array($this, $e));

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
            Event::fire('orbit.couponreport.getissuedcouponreport.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.couponreport.getissuedcouponreport.before.render', array($this, &$output));

        return $output;
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
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
