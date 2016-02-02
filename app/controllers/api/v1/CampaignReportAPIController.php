<?php
/**
 * An API controller for managing Campaign report.
 */
use Carbon\Carbon;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class CampaignReportAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'mall customer service'];

    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    /**
     * GET - Campaign Report Summary List
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `current_mall`  (required) - mall id
     * @param string   `campaign_id`   (required) - campaign id (news_id, promotion_id, coupon_id)
     * @param string   `sortby`        (optional) - Column order by. Valid value: updated_date, created_at, campaign_name, campaign_type, tenant, mall_name, begin_date, end_date, page_views, views, clicks, daily, estimated_total, spending, status
     * @param string   `sortmode`      (optional) - ASC or DESC
     * @param integer  `take`          (optional) - Limit
     * @param integer  `skip`          (optional) - Limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignReportSummary()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.campaignreport.getcampaignreportsummary.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.campaignreport.getcampaignreportsummary.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.campaignreport.getcampaignreportsummary.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.campaignreport.getcampaignreportsummary.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $current_mall = OrbitInput::get('current_mall');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');
            $sort_by = OrbitInput::get('sortby');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'current_mall' => $current_mall,
                    'sort_by' => $sort_by,
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'sort_by' => 'in:updated_at,campaign_name,campaign_type,total_tenant,mall_name,begin_date,end_date,page_views,popup_views,popup_clicks,daily,base_price,estimated_total,spending,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.campaignreportgeneral_sortby'),
                )
            );

            Event::fire('orbit.campaignreport.getcampaignreportsummary.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.campaignreport.getcampaignreportsummary.after.validation', array($this, $validator));

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

            $mall = App::make('orbit.empty.mall');
            $now = Carbon::now($mall->timezone->timezone_name);
            $timezone = $this->getTimezone($mall->merchant_id);
            $timezoneOffset = $this->getTimezoneOffset($timezone);

            // Builder object
            $now = date('Y-m-d H:i:s');
            $tablePrefix = DB::getTablePrefix();

            //get total cost news
            $news = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, news_name AS campaign_name, {$tablePrefix}news.object_type AS campaign_type,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}news.begin_date, {$tablePrefix}news.end_date, {$tablePrefix}news.updated_at, {$tablePrefix}campaign_price.base_price,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price AS daily,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF( {$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS estimated_total,
                (
                    SELECT fn_campaign_total_spending ('', {$tablePrefix}news.news_id, 'news', {$tablePrefix}news.begin_date, date_format(convert_tz(NOW(), '+00:00', '".$timezoneOffset."'), '%Y-%m-%d')) as a
                ) as spending,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_news'
                    AND {$tablePrefix}activities.group = 'mobile-ci'
                    AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                ) as page_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_news_popup'
                    AND {$tablePrefix}activities.activity_name_long = 'View News Pop Up'
                    AND {$tablePrefix}activities.group = 'mobile-ci'
                    AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                ) as popup_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_news_popup'
                    AND {$tablePrefix}activities.activity_name_long = 'Click News Pop Up'
                    AND {$tablePrefix}activities.group = 'mobile-ci'
                    AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                ) as popup_clicks,
                {$tablePrefix}news.status"))
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->join('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('news.mall_id', '=', $current_mall)
                        ->where('campaign_price.campaign_type', '=', 'news')
                        ->where('news.object_type', '=', 'news')
                        ->groupBy('news.news_id')
                        ;

            $promotions = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, news_name AS campaign_name, {$tablePrefix}news.object_type AS campaign_type,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}news.begin_date, {$tablePrefix}news.end_date, {$tablePrefix}news.updated_at, {$tablePrefix}campaign_price.base_price,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price AS daily,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS estimated_total,
                (
                    SELECT fn_campaign_total_spending ('', {$tablePrefix}news.news_id, 'promotion', {$tablePrefix}news.begin_date, date_format(convert_tz(NOW(), '+00:00', '".$timezoneOffset."'), '%Y-%m-%d')) as a
                ) as spending,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_promotion'
                    AND {$tablePrefix}activities.group = 'mobile-ci'
                    AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                ) as page_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_promotion_popup'
                    AND {$tablePrefix}activities.activity_name_long = 'View Promotion Pop Up'
                ) as popup_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_promotion_popup'
                    AND {$tablePrefix}activities.activity_name_long = 'Click Promotion Pop Up'
                ) as popup_clicks,

                {$tablePrefix}news.status"))
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->join('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('news.mall_id', '=', $current_mall)
                        ->where('campaign_price.campaign_type', '=', 'promotion')
                        ->where('news.object_type', '=', 'promotion')
                        ->groupBy('news.news_id')
                        ;

            $coupons = DB::table('promotions')->selectraw(DB::raw("{$tablePrefix}promotions.promotion_id AS campaign_id, promotion_name AS campaign_name, IF(1=1,'coupon', '') AS campaign_type,
                COUNT({$tablePrefix}promotion_retailer.promotion_retailer_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}promotions.begin_date, {$tablePrefix}promotions.end_date, {$tablePrefix}promotions.updated_at, {$tablePrefix}campaign_price.base_price,
                COUNT({$tablePrefix}promotion_retailer.promotion_retailer_id) * {$tablePrefix}campaign_price.base_price AS daily,
                COUNT({$tablePrefix}promotion_retailer.promotion_retailer_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}promotions.end_date, {$tablePrefix}promotions.begin_date) + 1) AS estimated_total,
                (
                    SELECT fn_campaign_total_spending ('', {$tablePrefix}promotions.promotion_id, 'promotion', {$tablePrefix}promotions.begin_date, date_format(convert_tz(NOW(), '+00:00', '".$timezoneOffset."'), '%Y-%m-%d')) as a
                ) as spending,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_coupon'
                    AND {$tablePrefix}activities.group = 'mobile-ci'
                    AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                ) as page_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_coupon_popup'
                    AND {$tablePrefix}activities.activity_name_long = 'View Coupon Pop Up'
                    AND {$tablePrefix}activities.group = 'mobile-ci'
                    AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                ) as popup_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_coupon_popup'
                    AND {$tablePrefix}activities.activity_name_long = 'Click Coupon Pop Up'
                    AND {$tablePrefix}activities.group = 'mobile-ci'
                    AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                ) as popup_clicks,
                {$tablePrefix}promotions.status"))
                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('campaign_price', 'campaign_price.campaign_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                        ->join('merchants as merchants2', 'promotions.merchant_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('promotions.merchant_id', '=', $current_mall)
                        ->where('campaign_price.campaign_type', '=', 'coupon')
                        ->groupBy('promotions.promotion_id')
                        ;

            $campaign = $news->unionAll($promotions)->unionAll($coupons);

            $sql = $campaign->toSql();
            foreach($campaign->getBindings() as $binding)
            {
              $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
              $sql = preg_replace('/\?/', $value, $sql, 1);
            }

            // Make union result subquery
            $campaign = DB::table(DB::raw('(' . $sql . ') as a'));


            // Filter by campaign name
            OrbitInput::get('campaign_name', function($campaign_name) use ($campaign) {
                $campaign->where('campaign_name', 'like', "%$campaign_name%");
            });

            // Filter by campaign type
            OrbitInput::get('campaign_type', function($campaign_type) use ($campaign) {
                $campaign->where('campaign_type', 'like', "%$campaign_type%");
            });

            // Filter by tenant
            // OrbitInput::get('tenant', function($tenant) use ($campaign) {
            //     $campaign->where('campaign_name', 'like', "%$campaign_name%");
            // });

            // Filter by mall
            OrbitInput::get('mall_name', function($mall_name) use ($campaign) {
                $campaign->where('mall_name', 'like', "%$mall_name%");
            });

            // Filter by campaign status
            OrbitInput::get('status', function($status) use ($campaign) {
                $status = (array)$status;
                $campaign->whereIn('status', $status);
            });

            // Filter by range date
            if($start_date != '' && $end_date != ''){
                $campaign->where(function ($q) use ($start_date, $end_date) {
                            $q->where(function ($r) use ($start_date, $end_date) {
                                    $r->whereRaw("begin_date between ? and ?", [$start_date, $end_date])
                                      ->orWhereRaw("end_date between ? and ?", [$start_date, $end_date]);
                                })
                              ->orWhere(function ($s) use ($start_date, $end_date) {
                                    $s->whereRaw(" ? between begin_date and end_date", [$start_date])
                                      ->orWhereRaw(" ? between begin_date and end_date", [$end_date]);
                                });
                        });
            }

            OrbitInput::get('mall_name', function($mall_name) use ($campaign) {
                $campaign->where('mall_name', 'like', "%$mall_name%");
            });


            // Clone the query builder which still does not include the take,
            $_campaign = clone $campaign;

            // Get total page views
            $totalPageViews = $_campaign->sum('page_views');

            // Get total popup views
            $totalPopupViews = $_campaign->sum('popup_views');

            // Get total estimate
            $totalEstimated = $_campaign->sum('estimated_total');

            // Get total spending
            $totalSpending = $_campaign->sum('spending');

            $_campaign->select('campaign_id');

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
            $campaign->take($take);

            // skip, and order by
            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $campaign)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $campaign->skip($skip);

            // Default sort by
            $sortBy = 'updated_at';

            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'updated_at'      => 'updated_at',
                    'campaign_name'   => 'campaign_name',
                    'campaign_type'   => 'campaign_type',
                    'total_tenant'    => 'total_tenant',
                    'mall_name'       => 'mall_name',
                    'begin_date'      => 'begin_date',
                    'end_date'        => 'end_date',
                    'page_views'      => 'page_views',
                    'popup_views'     => 'popup_views',
                    'popup_clicks'    => 'popup_clicks',
                    'base_price'      => 'base_price',
                    'daily'           => 'daily',
                    'estimated_total' => 'estimated_total',
                    'spending'        => 'spending',
                    'status'          => 'status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $campaign->orderBy($sortBy, $sortMode);

            $totalCampaign = $_campaign->count();
            $listOfCampaign = $campaign->get();

            // get popup tenant
            foreach ($listOfCampaign as $key => $val) {
                if ($val->campaign_type === 'coupon') {
                    $linkToTenants = DB::table('promotion_retailer')->selectraw(DB::raw("{$tablePrefix}merchants.name"))
                            ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                            ->where('promotion_retailer.promotion_id', $val->campaign_id)
                            ->get();
                } else {
                    $linkToTenants = DB::table('news_merchant')->selectraw(DB::raw("{$tablePrefix}merchants.name"))
                            ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                            ->where('news_merchant.news_id', $val->campaign_id)
                            ->get();
                }

                $listOfCampaign[$key]->tenants = $linkToTenants;
            }

            $data = new stdclass();
            $data->total_records = $totalCampaign;
            $data->total_page_views = $totalPageViews;
            $data->total_pop_up_views = $totalPopupViews;
            $data->total_estimated_cost = $totalEstimated;
            $data->total_spending = $totalSpending;
            $data->returned_records = count($listOfCampaign);
            $data->records = $listOfCampaign;

            if ($totalCampaign === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.campaignreport.getcampaignreportsummary.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.campaignreport.getcampaignreportsummary.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.campaignreport.getcampaignreportsummary.query.error', array($this, $e));

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
            Event::fire('orbit.campaignreport.getcampaignreportsummary.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.campaignreport.getcampaignreportsummary.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Campaign Report Detail List
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `campaign_id            (required) - Campaign id (news_id, promotion_id, coupon_id)
     * @param string   `campaign_type          (required) - news, promotion, coupon
     * @param string   `current_mall`          (required) - mall id
     * @param string   `sortby`                (optional) - Column order by. Valid value: updated_date, created_at, campaign_name, campaign_type, tenant, mall_name, begin_date, end_date, page_views, views, clicks, daily, estimated_total, spending, status
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignReportDetail()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $campaign_id = OrbitInput::get('campaign_id');
            $campaign_type = OrbitInput::get('campaign_type');
            $current_mall = OrbitInput::get('current_mall');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');
            $sort_by = OrbitInput::get('sortby');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'campaign_id' => $campaign_id,
                    'campaign_type' => $campaign_type,
                    'current_mall' => $current_mall,
                    // 'sort_by' => $sort_by,
                ),
                array(
                    'campaign_id' => 'required',
                    'campaign_type' => 'required',
                    'current_mall' => 'required|orbit.empty.mall',
                    // 'sort_by' => 'in:updated_at,campaign_name,campaign_type,tenant,mall_name,begin_date,end_date,page_views,popup_views,popup_clicks,base_price,estimated_total,spending,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.campaignreportgeneral_sortby'),
                )
            );

            Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.after.validation', array($this, $validator));

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
            $tablePrefix = DB::getTablePrefix();

            if ($campaign_type === 'news' or $campaign_type === 'promotion') {
                // Get begin and end
                $getBeginEndDate = News::excludeDeleted()->selectRaw('DATE(begin_date) as begin_date, DATE(end_date) as end_date')
                    ->where('news_id', $campaign_id)
                    ->where('object_type', $campaign_type)
                    ->get();

            } elseif ($campaign_type === 'coupon') {
                // Get begin and end
                $getBeginEndDate = Coupon::excludeDeleted()->selectRaw('DATE(begin_date) as begin_date, DATE(end_date) as end_date')
                    ->where('promotion_id', $campaign_id)
                    ->get();
            }

            // Get data from activity per day
            $mall = App::make('orbit.empty.mall');
            $now = Carbon::now($mall->timezone->timezone_name);
            $timezone = $this->getTimezone($mall->merchant_id);
            $timezoneOffset = $this->getTimezoneOffset($timezone);
            $beginDate = date("Y-m-d", strtotime($getBeginEndDate[0]->begin_date));
            $endDate = date("Y-m-d", strtotime($getBeginEndDate[0]->end_date));
            $today = date("Y-m-d");

            // Only show campaign data from start date from today
            if ($today <= $endDate) {
                $endDate = $today;
            }

            if ($campaign_type === 'news' or $campaign_type === 'promotion') {

                    $campaignHistory = DB::select( DB::raw("select
                                        {$tablePrefix}campaign_histories.campaign_id as campaign_id,
                                        {$tablePrefix}campaign_histories.number_active_tenants as total_tenant,
                                        {$tablePrefix}campaign_price.base_price,
                                        date_format(convert_tz({$tablePrefix}campaign_histories.created_at, '+00:00', '".$timezoneOffset."'), '%Y-%m-%d') as created_at,
                                        (select
                                                {$tablePrefix}campaign_history_actions.action_name
                                            from
                                                {$tablePrefix}campaign_histories a
                                                    LEFT JOIN {$tablePrefix}campaign_history_actions ON {$tablePrefix}campaign_history_actions.campaign_history_action_id = a.campaign_history_action_id
                                            where
                                                date_format(convert_tz(a.created_at, '+00:00', '".$timezoneOffset."'), '%Y-%m-%d') <= date_format(convert_tz({$tablePrefix}campaign_histories.created_at, '+00:00', '".$timezoneOffset."'), '%Y-%m-%d')
                                                    and {$tablePrefix}campaign_history_actions.action_name in ('activate' , 'deactivate')
                                                    and a.campaign_id = {$tablePrefix}campaign_histories.campaign_id
                                            order by {$tablePrefix}campaign_history_actions.action_name, DATE_FORMAT(a.created_at, '%Y-%m-%d') desc
                                            limit 1) as action_status,
                                        (select
                                                {$tablePrefix}campaign_history_actions.action_name
                                            from
                                                {$tablePrefix}campaign_histories a
                                                    LEFT JOIN {$tablePrefix}campaign_history_actions ON {$tablePrefix}campaign_history_actions.campaign_history_action_id = a.campaign_history_action_id
                                            where
                                                date_format(convert_tz(a.created_at, '+00:00', '".$timezoneOffset."'), '%Y-%m-%d') <= date_format(convert_tz({$tablePrefix}campaign_histories.created_at, '+00:00', '".$timezoneOffset."'), '%Y-%m-%d')
                                                    and ({$tablePrefix}campaign_history_actions.action_name in ('activate' , 'deactivate'))
                                                    and a.campaign_id = {$tablePrefix}campaign_histories.campaign_id
                                            order by a.campaign_history_id desc, DATE_FORMAT(a.created_at, '%Y-%m-%d') desc
                                            limit 1) as previous_status
                                    from
                                        (select *
                                        from
                                            {$tablePrefix}campaign_histories
                                        where
                                            campaign_id = '". $campaign_id ."'
                                        order by number_active_tenants desc) {$tablePrefix}campaign_histories
                                            left join
                                        {$tablePrefix}news ON {$tablePrefix}news.news_id = {$tablePrefix}campaign_histories.campaign_id
                                            left join
                                        {$tablePrefix}campaign_price ON {$tablePrefix}campaign_price.campaign_id = {$tablePrefix}campaign_histories.campaign_id
                                            left join
                                        {$tablePrefix}campaign_history_actions ON {$tablePrefix}campaign_history_actions.campaign_history_action_id = {$tablePrefix}campaign_histories.campaign_history_action_id
                                    group by DATE_FORMAT({$tablePrefix}campaign_histories.created_at, '%Y-%m-%d'), {$tablePrefix}campaign_histories.campaign_id") );


            } elseif ($campaign_type === 'coupon') {

                    $campaignHistory = DB::select( DB::raw("select
                                        {$tablePrefix}campaign_histories.campaign_id as campaign_id,
                                        {$tablePrefix}campaign_histories.number_active_tenants as total_tenant,
                                        {$tablePrefix}campaign_price.base_price,
                                        date_format(convert_tz({$tablePrefix}campaign_histories.created_at, '+00:00', '".$timezoneOffset."'), '%Y-%m-%d') as created_at,
                                        (select
                                                {$tablePrefix}campaign_history_actions.action_name
                                            from
                                                {$tablePrefix}campaign_histories a
                                                    LEFT JOIN {$tablePrefix}campaign_history_actions ON {$tablePrefix}campaign_history_actions.campaign_history_action_id = a.campaign_history_action_id
                                            where
                                                date_format(convert_tz(a.created_at, '+00:00', '".$timezoneOffset."'), '%Y-%m-%d') <= date_format(convert_tz({$tablePrefix}campaign_histories.created_at, '+00:00', '".$timezoneOffset."'), '%Y-%m-%d')
                                                    and {$tablePrefix}campaign_history_actions.action_name in ('activate' , 'deactivate')
                                                    and a.campaign_id = {$tablePrefix}campaign_histories.campaign_id
                                            order by {$tablePrefix}campaign_history_actions.action_name, DATE_FORMAT(a.created_at, '%Y-%m-%d') desc
                                            limit 1) as action_status,
                                        (select
                                                {$tablePrefix}campaign_history_actions.action_name
                                            from
                                                {$tablePrefix}campaign_histories a
                                                    LEFT JOIN {$tablePrefix}campaign_history_actions ON {$tablePrefix}campaign_history_actions.campaign_history_action_id = a.campaign_history_action_id
                                            where
                                                date_format(convert_tz(a.created_at, '+00:00', '".$timezoneOffset."'), '%Y-%m-%d') <= date_format(convert_tz({$tablePrefix}campaign_histories.created_at, '+00:00', '".$timezoneOffset."'), '%Y-%m-%d')
                                                    and ({$tablePrefix}campaign_history_actions.action_name in ('activate' , 'deactivate'))
                                                    and a.campaign_id = {$tablePrefix}campaign_histories.campaign_id
                                            order by a.campaign_history_id desc, DATE_FORMAT(a.created_at, '%Y-%m-%d') desc
                                            limit 1) as previous_status
                                    from
                                        (select *
                                        from
                                            {$tablePrefix}campaign_histories
                                        where
                                            campaign_id = '". $campaign_id ."'
                                        order by number_active_tenants desc) {$tablePrefix}campaign_histories
                                            left join
                                        {$tablePrefix}promotions ON {$tablePrefix}promotions.promotion_id = {$tablePrefix}campaign_histories.campaign_id
                                            left join
                                        {$tablePrefix}campaign_price ON {$tablePrefix}campaign_price.campaign_id = {$tablePrefix}campaign_histories.campaign_id
                                            left join
                                        {$tablePrefix}campaign_history_actions ON {$tablePrefix}campaign_history_actions.campaign_history_action_id = {$tablePrefix}campaign_histories.campaign_history_action_id
                                    group by DATE_FORMAT({$tablePrefix}campaign_histories.created_at, '%Y-%m-%d') , {$tablePrefix}campaign_histories.campaign_id"));

            }

            $start_date = new Carbon(substr($beginDate,0,10));
            $end_date = new Carbon(substr($now,0,10));
            $diff = $start_date->diffInDays($end_date);
            $begin =substr($beginDate,0,10);
            $end =substr($now,0,10);
            $start = new Carbon($start_date);
            $find = FALSE;

            // Get active date only
            $campaignDetailActive = array();

            if ($endDate < $end) {
                $end = $endDate;
            }

            if (count($campaignHistory) > 0) {
                for ($x = 0; $x<=$diff; $x++) {
                    $dateloop = $start->toDateString();
                    $spending = 0;
                    foreach($campaignHistory as $nq) {
                        if($nq->created_at <= $dateloop) {
                            $find = FALSE;
                            if ($nq->campaign_id === $campaign_id) {
                                if($nq->created_at >= $begin && $nq->created_at <= $end) {
                                    if ($nq->created_at === $dateloop) {
                                        $find = TRUE;
                                        $campaignstatus = $nq->action_status;
                                        $campaigntenant = $nq->total_tenant;
                                        $statustemp = $nq->previous_status;
                                        $tenanttemp = $nq->total_tenant;
                                    } else {
                                        $find = FALSE;
                                        $campaignstatus = $nq->action_status;
                                        $campaigntenant = $nq->total_tenant;
                                        $statustemp = $nq->previous_status;
                                        $tenanttemp = $nq->total_tenant;
                                    }
                                } else {
                                    $find = FALSE;
                                    $campaignstatus = $nq->action_status;
                                    $campaigntenant = $nq->total_tenant;
                                    $statustemp = $nq->previous_status;
                                    $tenanttemp = $nq->total_tenant;
                                }
                                if (!$find) {
                                    $campaignstatus = $statustemp;
                                    $campaigntenant = $tenanttemp;
                                }
                            }
                        }
                    }

                    if($dateloop >= $begin && $dateloop <= $end) {
                        if($campaignstatus == 'activate' || $campaignstatus == 'active'){
                            $spending = (int) $campaigntenant * $nq->base_price;
                            // $totalSpendingCampaign += $spending;
                            $campaignDetailActive[$x]['campaign_type'] = $campaign_type;
                            $campaignDetailActive[$x]['campaign_id'] = $campaign_id;
                            $campaignDetailActive[$x]['total_tenant'] = $campaigntenant;
                            $campaignDetailActive[$x]['base_price'] = $nq->base_price;
                            $campaignDetailActive[$x]['campaign_date'] = $dateloop;
                            $campaignDetailActive[$x]['action_status'] = $campaignstatus;
                            $campaignDetailActive[$x]['spending'] = $spending;
                        }
                    }
                    $start->addDay();
                }
            }


            // Get detail campaign ( Mall, Unique User, Detail Campaign Page, Pop Up)
            foreach ($campaignDetailActive as $key => $valDetailActive) {
                if ($campaign_type === 'coupon') {
                    $details = DB::table('promotions')->selectraw(DB::raw("
                        merchants2.name as mall_name,
                        (
                            SELECT COUNT(activity_id) as unique_user
                            FROM (
                                SELECT {$tablePrefix}activities.activity_id
                                FROM {$tablePrefix}activities
                                WHERE ({$tablePrefix}activities.activity_name_long like '%sign up%' OR {$tablePrefix}activities.activity_name_long = 'Sign In')
                                AND DATE({$tablePrefix}activities.created_at) = '" . $valDetailActive['campaign_date'] . "'
                                AND {$tablePrefix}activities.`group` = 'mobile-ci'
                                AND {$tablePrefix}activities.`location_id` = '" . $current_mall . "'
                                group by {$tablePrefix}activities.user_id
                            ) as act1
                        ) as unique_users,
                        (
                            SELECT COUNT({$tablePrefix}activities.activity_id)
                            FROM {$tablePrefix}activities
                            WHERE {$tablePrefix}activities.object_id = '" . $campaign_id . "'
                            AND DATE({$tablePrefix}activities.created_at) = '" . $valDetailActive['campaign_date'] . "'
                            AND {$tablePrefix}activities.`location_id` = '" . $current_mall . "'
                            AND {$tablePrefix}activities.activity_name = 'view_news'
                            AND {$tablePrefix}activities.group = 'mobile-ci'
                            AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                        ) as campaign_pages_views,
                        (
                            SELECT COUNT({$tablePrefix}activities.activity_id)
                            FROM {$tablePrefix}activities
                            WHERE {$tablePrefix}activities.object_id = '" . $campaign_id . "'
                            AND DATE({$tablePrefix}activities.created_at) = '" . $valDetailActive['campaign_date'] . "'
                            AND {$tablePrefix}activities.`location_id` = '" . $current_mall . "'
                            AND {$tablePrefix}activities.activity_name = 'view_news_popup'
                            AND {$tablePrefix}activities.activity_name_long = 'View News Pop Up'
                            AND {$tablePrefix}activities.group = 'mobile-ci'
                            AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                        ) as popup_views,
                        (
                            SELECT COUNT({$tablePrefix}activities.activity_id)
                            FROM {$tablePrefix}activities
                            WHERE {$tablePrefix}activities.object_id = '" . $campaign_id . "'
                            AND DATE({$tablePrefix}activities.created_at) = '" . $valDetailActive['campaign_date'] . "'
                            AND {$tablePrefix}activities.`location_id` = '" . $current_mall . "'
                            AND {$tablePrefix}activities.activity_name = 'click_news_popup'
                            AND {$tablePrefix}activities.activity_name_long = 'Click News Pop Up'
                            AND {$tablePrefix}activities.group = 'mobile-ci'
                            AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                        ) as popup_clicks
                        "))
                        ->join('merchants as merchants2', 'promotions.merchant_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('promotions.merchant_id', '=', $current_mall)
                        ->where('promotions.promotion_id', '=', $campaign_id)
                        ->get();
                } elseif ($campaign_type === 'news' || $campaign_type === 'promotion') {
                    $details = DB::table('news')->selectraw(DB::raw("
                        merchants2.name as mall_name,
                        (
                            SELECT COUNT(activity_id) as unique_user
                            FROM (
                                SELECT {$tablePrefix}activities.activity_id
                                FROM {$tablePrefix}activities
                                WHERE ({$tablePrefix}activities.activity_name_long like '%sign up%' OR {$tablePrefix}activities.activity_name_long = 'Sign In')
                                AND DATE({$tablePrefix}activities.created_at) = '" . $valDetailActive['campaign_date'] . "'
                                AND {$tablePrefix}activities.`location_id` = '" . $current_mall . "'
                                AND {$tablePrefix}activities.`group` = 'mobile-ci'
                                group by {$tablePrefix}activities.user_id
                            ) as act1
                        ) as unique_users,
                        (
                            SELECT COUNT({$tablePrefix}activities.activity_id)
                            FROM {$tablePrefix}activities
                            WHERE {$tablePrefix}activities.object_id = '" . $campaign_id . "'
                            AND DATE({$tablePrefix}activities.created_at) = '" . $valDetailActive['campaign_date'] . "'
                            AND {$tablePrefix}activities.`location_id` = '" . $current_mall . "'
                            AND {$tablePrefix}activities.activity_name = 'view_news'
                            AND {$tablePrefix}activities.group = 'mobile-ci'
                            AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                        ) as campaign_pages_views,
                        (
                            SELECT COUNT({$tablePrefix}activities.activity_id)
                            FROM {$tablePrefix}activities
                            WHERE {$tablePrefix}activities.object_id = '" . $campaign_id . "'
                            AND DATE({$tablePrefix}activities.created_at) = '" . $valDetailActive['campaign_date'] . "'
                            AND {$tablePrefix}activities.`location_id` = '" . $current_mall . "'
                            AND {$tablePrefix}activities.activity_name = 'view_news_popup'
                            AND {$tablePrefix}activities.activity_name_long = 'View News Pop Up'
                            AND {$tablePrefix}activities.group = 'mobile-ci'
                            AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                        ) as popup_views,
                        (
                            SELECT COUNT({$tablePrefix}activities.activity_id)
                            FROM {$tablePrefix}activities
                            WHERE {$tablePrefix}activities.object_id = '" . $campaign_id . "'
                            AND DATE({$tablePrefix}activities.created_at) = '" . $valDetailActive['campaign_date'] . "'
                            AND {$tablePrefix}activities.`location_id` = '" . $current_mall . "'
                            AND {$tablePrefix}activities.activity_name = 'click_news_popup'
                            AND {$tablePrefix}activities.activity_name_long = 'Click News Pop Up'
                            AND {$tablePrefix}activities.group = 'mobile-ci'
                            AND ({$tablePrefix}activities.role = 'Consumer' or {$tablePrefix}activities.role = 'Guest')
                        ) as popup_clicks
                        "))
                        ->join('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('news.mall_id', '=', $current_mall)
                        ->where('news.object_type', '=', $campaign_type)
                        ->where('news.news_id', '=', $campaign_id)
                        ->get();
                }

                $unique_users = 0;
                if (count($details[0]->unique_users) != 0) {
                    $unique_users = $details[0]->unique_users;
                }

                $campaign_pages_view_rate = 0;
                if ($unique_users != 0){
                    $campaign_pages_view_rate = ($details[0]->campaign_pages_views / $unique_users) * 100;
                }

                $popup_view_rate = 0;
                if ($unique_users != 0){
                    $popup_view_rate = ($details[0]->popup_views / $unique_users) * 100;
                }

                $popup_click_rate = 0;
                if ($details[0]->popup_clicks != 0) {
                    $popup_click_rate = ($details[0]->popup_clicks / $details[0]->popup_views) * 100;
                }

                $campaignDetailActive[$key]['mall_name'] = $details[0]->mall_name;
                $campaignDetailActive[$key]['unique_users'] = $unique_users;
                $campaignDetailActive[$key]['campaign_pages_views'] = $details[0]->campaign_pages_views;
                $campaignDetailActive[$key]['campaign_pages_view_rate'] =  round($campaign_pages_view_rate, 2);
                $campaignDetailActive[$key]['popup_views'] = $details[0]->popup_views;
                $campaignDetailActive[$key]['popup_view_rate'] =  round($popup_view_rate, 2);
                $campaignDetailActive[$key]['popup_clicks'] = $details[0]->popup_clicks;
                $campaignDetailActive[$key]['popup_click_rate'] =  round($popup_click_rate, 2);
            }

            // Get total
            $activeCampaignDays = count($campaignDetailActive);
            $totalPageViews = 0;
            $totalPopupViews = 0;
            $totalPopupClicks = 0;
            $totalSpending = 0;

            if (count($campaignDetailActive) > 0) {
                foreach ($campaignDetailActive as $key => $value) {
                    $totalPageViews += $value['campaign_pages_views'];
                    $totalPopupViews += $value['popup_views'];
                    $totalPopupClicks += $value['popup_clicks'];
                    $totalSpending += $value['spending'];
                }
            }

            // $_campaign->select('campaign_id');

            // // Get the take args
            // $take = $perPage;
            // OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
            //     if ($_take > $maxRecord) {
            //         $_take = $maxRecord;
            //     }
            //     $take = $_take;

            //     if ((int)$take <= 0) {
            //         $take = $maxRecord;
            //     }
            // });
            // $campaign->take($take);

            // // skip, and order by
            // $skip = 0;
            // OrbitInput::get('skip', function($_skip) use (&$skip, $campaign)
            // {
            //     if ($_skip < 0) {
            //         $_skip = 0;
            //     }

            //     $skip = $_skip;
            // });
            // $campaign->skip($skip);

            // Default sort by
            $sortBy = 'campaign_date';

            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'campaign_date'            => 'campaign_date',
                    'total_tenant'             => 'total_tenant',
                    'mall_name'                => 'mall_name',
                    'unique_users'             => 'unique_users',
                    'campaign_pages_views'     => 'campaign_pages_views',
                    'campaign_pages_view_rate' => 'campaign_pages_view_rate',
                    'popup_views'              => 'popup_views',
                    'popup_view_rate'          => 'popup_view_rate',
                    'popup_clicks'             => 'popup_clicks',
                    'popup_click_rate'         => 'popup_click_rate',
                    'spending'                 => 'spending',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            // reverse array by date desc
            $campaignDetailActive = array_reverse($campaignDetailActive);

            $totalCampaign = count($campaignDetailActive);
            $listOfCampaign = $campaignDetailActive;

            $data = new stdclass();
            $data->total_records = $totalCampaign;
            $data->returned_records = count($listOfCampaign);
            $data->active_campaign_days = $activeCampaignDays;
            $data->total_page_views = $totalPageViews;
            $data->total_popup_views = $totalPopupViews;
            $data->total_popup_clicks = $totalPopupClicks;
            $data->total_spending = $totalSpending;
            $data->records = $listOfCampaign;

            if ($totalCampaign === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.query.error', array($this, $e));

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
            Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.campaignreportdetail.getcampaignreportdetail.before.render', array($this, &$output));

        return $output;
    }

    public function orderBy($data, $field)
    {
        $code = "return strnatcmp(\$a['$field'], \$b['$field']);";
        usort($data, create_function('$a,$b', $code));
        return $data;
    }

    /**
     * GET - Campaign demographic
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param string  `current_mall`  (required) - mall id
     * @param string  `campaign_id`   (required) - campaign id (news_id, promotion_id, coupon_id)
     * @param date    `start_date`    (required) - start date, default is 1 month
     * @param date    `end_date`      (required) - end date
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignDemographic()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getcampaigndemographic.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getcampaigndemographic.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getcampaigndemographic.before.auth', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.dashboard.getcampaigndemographic.after.auth', array($this, $user));

            $tablePrefix = DB::getTablePrefix();

            $this->registerCustomValidation();

            $current_mall = OrbitInput::get('current_mall');
            $campaign_id = OrbitInput::get('campaign_id');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $validator = Validator::make(
                array(
                    'current_mall' => $current_mall,
                    'campaign_id' => $campaign_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ),
                array(
                    'current_mall' => 'required',
                    'campaign_id' => 'required',
                    'start_date' => 'required | date_format:Y-m-d H:i:s',
                    'end_date' => 'required | date_format:Y-m-d H:i:s',
                )
            );

            Event::fire('orbit.dashboard.getcampaigndemographic.before.validation', array($this, $validator));

            // Run the validation
            if ( $validator->fails() ) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getcampaigndemographic.after.validation', array($this, $validator));

            // start date cannot be bigger than end date
            if ( $start_date > $end_date ) {
                $errorMessage = 'Start date cannot be greater than end date';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $query = "SELECT
                    SUM(case when age >= 0 and age <= 14 then 1 else 0 end) as '0 - 14',
                    SUM(case when age >= 15 and age <= 24 then 1 else 0 end) as '15 - 24',
                    SUM(case when age >= 25 and age <= 34 then 1 else 0 end) as '25 - 34',
                    SUM(case when age >= 35 and age <= 44 then 1 else 0 end) as '35 - 44',
                    SUM(case when age >= 45 and age <= 54 then 1 else 0 end) as '45 - 54',
                    SUM(case when age >= 55 then 1 else 0 end) as '55 +',
                    SUM(case when age >= 0 then 1 else 0 end) as 'total'
                FROM(
                    SELECT activity_id, activity_name, user_email, {$tablePrefix}user_details.gender, birthdate ,TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age
                    FROM {$tablePrefix}activities
                    LEFT JOIN {$tablePrefix}user_details
                    ON {$tablePrefix}activities.user_id = {$tablePrefix}user_details.user_id
                    WHERE 1 = 1
                    AND {$tablePrefix}activities.object_id = ?
                    AND `group` = 'mobile-ci' AND activity_type = 'view'
                    AND (activity_name = 'view_promotion' OR activity_name = 'view_news' OR activity_name = 'view_coupon')
                    AND (birthdate != '0000-00-00' AND birthdate != '' AND birthdate is not null)
                    AND {$tablePrefix}activities.gender is not null
                    AND location_id = ?
                ";

            $demograhicFemale = DB::select($query . "
                        AND {$tablePrefix}user_details.gender = 'f'
                        AND {$tablePrefix}activities.created_at between ? and ?
                    ) as A
            ", array($campaign_id, $current_mall, $start_date, $end_date));

            $demograhicMale = DB::select($query . "
                        AND {$tablePrefix}user_details.gender = 'm'
                        AND {$tablePrefix}activities.created_at between ? and ?
                    ) as A
            ", array($campaign_id, $current_mall, $start_date, $end_date));

            $female = array();
            $percent = 0;
            $total = 0;

            foreach (Config::get('orbit.age_ranges') as $key => $ageRange) {
                if( $demograhicFemale[0]->$ageRange !== null ) {
                    if($demograhicFemale[0]->total !== 0){
                        $percent = ($demograhicFemale[0]->$ageRange / $demograhicFemale[0]->total) * 100;
                        $total = $demograhicFemale[0]->$ageRange;
                    }
                }

                $female[$key]['age_range'] = $ageRange;
                $female[$key]['total'] = $total;
                $female[$key]['percent'] = round($percent, 2) . ' %';
            }

            foreach (Config::get('orbit.age_ranges') as $key => $ageRange) {
                if( $demograhicMale[0]->$ageRange !== null ) {
                    if($demograhicMale[0]->total !== 0){
                        $percent = ($demograhicMale[0]->$ageRange / $demograhicMale[0]->total) * 100;
                        $total = $demograhicMale[0]->$ageRange;
                    }
                }

                $male[$key]['age_range'] = $ageRange;
                $male[$key]['total'] = $total;
                $male[$key]['percent'] = round($percent, 2) . ' %';
            }

            // get column name from config
            $responses['female'] = $female;
            $responses['male'] = $male;

            $this->response->data = $responses;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getcampaigndemographic.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getcampaigndemographic.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getcampaigndemographic.query.error', array($this, $e));

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
            Event::fire('orbit.dashboard.getcampaigndemographic.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getcampaigndemographic.before.render', array($this, &$output));

        return $output;
    }

    /**
     * Get the campaign spending
     *
     * Request datetimes: UTC
     * Campaign begin and end datetimes: Mall's timezone
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     * @todo Validations
     */
    public function getSpending()
    {
        // Mall ID
        $mallId = OrbitInput::get('current_mall');
        $timezone = Mall::find($mallId)->timezone->timezone_name;

        // Campaign ID
        $id = OrbitInput::get('campaign_id');

        // News, promotion or coupon
        $type = OrbitInput::get('campaign_type');

        // Date intervals
        $requestBeginDateTime = OrbitInput::get('start_date');
        $requestBeginTime = substr($requestBeginDateTime, 11, 8);

        $requestEndDateTime = OrbitInput::get('end_date');

        // Init Carbon
        $carbonLoop = Carbon::createFromFormat('Y-m-d H:i:s', $requestBeginDateTime);
        $carbonLoopNextDay = Carbon::createFromFormat('Y-m-d H:i:s', $requestBeginDateTime)->addDay();

        // Get the campaign from database
        switch ($type) {
            case 'news':
                $campaign = News::isNews();
                break;
            case 'promotion':
                $campaign = News::isPromotion();
                break;
            case 'coupon':
                $campaign = new Coupon;
                break;
        }

        $campaign = $campaign->find($id);

        $campaignBeginDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $campaign->begin_date, $timezone)->setTimezone('UTC')->toDateTimeString();

        // This assumes request begin time is always 00:00 of mall timezone
        $campaignBeginDateTimeMidnight = substr($campaign->begin_date, 0, 10).' 00:00:00';
        $campaignBeginDateTimeMidnight = Carbon::createFromFormat('Y-m-d H:i:s', $campaignBeginDateTimeMidnight, $timezone)->setTimeZone('UTC')->toDateTimeString();

        $campaignEndDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $campaign->end_date, $timezone)->setTimezone('UTC')->toDateTimeString();
        $campaignEndDateTime2 = Carbon::createFromFormat('Y-m-d H:i:s', $campaign->end_date, $timezone)->setTimezone('UTC')->addMinute()->toDateTimeString();

        // Get the base cost
        $baseCost = CampaignPrice::whereCampaignType($type)->whereCampaignId($id)->first()->base_price;

        // Set the default initial cost
        $previousDayCost = 0;

        // In case the creation date is earlier than the first active date
        $campaignLog = CampaignHistory::ofCampaignTypeAndId($type, $id)
            ->where('created_at', '<', $campaignBeginDateTime)
            ->orderBy('campaign_history_id', 'desc')->first();

        $activationActionId = CampaignHistoryActions::whereActionName('activate')->first()->campaign_history_action_id;
        $deactivationActionId = CampaignHistoryActions::whereActionName('deactivate')->first()->campaign_history_action_id;

        if ($campaignLog) {

            $activationRowId = null;
            $deactivationRowId = null;

            // Null when not found
            $activationRow = CampaignHistory::ofCampaignTypeAndId($type, $id)
                ->whereCampaignHistoryActionId($activationActionId)
                ->orderBy('campaign_history_id', 'desc')->first();

            if ($activationRow) {
                $activationRowId = $activationRow->campaign_history_id;
            }

            // Null when not found
            $deactivationRow = CampaignHistory::ofCampaignTypeAndId($type, $id)
                ->whereCampaignHistoryActionId($deactivationActionId)
                ->orderBy('campaign_history_id', 'desc')->first();

            if ($deactivationRow) {
                $deactivationRowId = $deactivationRow->campaign_history_id;
            }

            if ($activationRowId > $deactivationRowId || ($activationRowId === null && $deactivationRowId === null)) {

                // Get max tenant count
                $row = CampaignHistory::ofCampaignTypeAndId($type, $id)
                    ->where('created_at', '<', $campaignBeginDateTime)
                    ->orderBy('number_active_tenants', 'desc')->first();

                $previousDayCost = $baseCost * $row->number_active_tenants;
            }
        }

        // Loop
        while ($carbonLoop->toDateTimeString() <= $requestEndDateTime) {
            $loopBeginDateTime = $carbonLoop->toDateTimeString();
            $loopEndDateTime = $carbonLoopNextDay->toDateTimeString();

            // Let's retrieve it from DB
            $campaignLog = CampaignHistory::ofCampaignTypeAndId($type, $id)->ofTimestampRange($loopBeginDateTime, $loopEndDateTime)
                ->orderBy('campaign_history_id', 'desc')->first();

            // Data found
            if ($campaignLog) {

                $activationRowId = '';
                $deactivationRowId = '';

                // Null when not found
                $activationRow = CampaignHistory::ofCampaignTypeAndId($type, $id)->ofTimestampRange($loopBeginDateTime, $loopEndDateTime)
                    ->whereCampaignHistoryActionId($activationActionId)
                    ->orderBy('campaign_history_id', 'desc')->first();

                if ($activationRow) {

                    // Get max tenant count
                    $row = CampaignHistory::ofCampaignTypeAndId($type, $id)->ofTimestampRange($loopBeginDateTime, $loopEndDateTime)
                        ->orderBy('number_active_tenants', 'desc')->first();

                    // If there is an activation today, any deactivation won't be affected
                    $cost = $previousDayCost = $baseCost * $row->number_active_tenants;

                    // Cancel
                    if ($campaignLog->created_at->toDateTimeString() < $campaignBeginDateTimeMidnight) {
                        $cost = 0;
                    }

                    $activationRowId = $activationRow->campaign_history_id;
                }

                // Null when not found
                $deactivationRow = CampaignHistory::ofCampaignTypeAndId($type, $id)->ofTimestampRange($loopBeginDateTime, $loopEndDateTime)
                    ->whereCampaignHistoryActionId($deactivationActionId)
                    ->orderBy('campaign_history_id', 'desc')->first();

                if ($deactivationRow) {
                    $deactivationRowId = $deactivationRow->campaign_history_id;
                }

                // If there is a deactivation at last row, it will be affected tomorrow
                if ($deactivationRowId > $activationRowId) {
                    $previousDayCost = 0;
                }

            // Data not found, but the date is in the interval
            } elseif ($loopBeginDateTime >= $campaignBeginDateTime && $loopEndDateTime <= $campaignEndDateTime2) {
                $cost = $previousDayCost;

            // Data not found
            } else {
                $cost = 0;
            }

            // Add to output array
            $outputs[] = [
                'date' => $carbonLoop->setTimezone($timezone)->toDateString(),
                'cost' => (int) $cost, // Format cost as integer
            ];

            // Set it back to UTC
            $carbonLoop->setTimezone('UTC');

            // Increment day by 1
            $carbonLoop->addDay();
            $carbonLoopNextDay->addDay();
        }

        $this->response->data = $outputs;

        return $this->render(200);
    }

    /**
     * GET - Campaign overview
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List Of Parameters
     * ------------------
     * @param string  `current_mall`   (required) - mall id
     * @param string  `campaign_id`   (required) - promotion id or coupon id or news id
     * @param date    `start_date`    (required) - start date, default is 1 month
     * @param date    `end_date`      (required) - end date
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignOverview()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dashboard.getcampaignoverview.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dashboard.getcampaignoverview.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dashboard.getcampaignoverview.before.auth', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.dashboard.getcampaignoverview.after.auth', array($this, $user));

            $tablePrefix = DB::getTablePrefix();

            $this->registerCustomValidation();

            $current_mall = OrbitInput::get('current_mall');
            $campaign_id = OrbitInput::get('campaign_id');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $validator = Validator::make(
                array(
                    'current_mall' => $current_mall,
                    'campaign_id' => $campaign_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ),
                array(
                    'current_mall' => 'required',
                    'campaign_id' => 'required',
                    'start_date' => 'required | date_format:Y-m-d H:i:s',
                    'end_date' => 'required | date_format:Y-m-d H:i:s',
                )
            );

            Event::fire('orbit.dashboard.getcampaignoverview.before.validation', array($this, $validator));

            // Run the validation
            if ( $validator->fails() ) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dashboard.getcampaignoverview.after.validation', array($this, $validator));

            // start date cannot be bigger than end date
            if ( $start_date > $end_date ) {
                $errorMessage = 'Start date cannot be greater than end date';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $timezone = $this->getTimezone($current_mall);
            $timezoneOffset = $this->getTimezoneOffset($timezone);

            // convert to timezone
            $begin = new DateTime($start_date, new DateTimeZone('UTC'));
            $endtime = new DateTime($end_date, new DateTimeZone('UTC'));
            $begin->setTimezone(new DateTimeZone($timezone));
            $endtime->setTimezone(new DateTimeZone($timezone));

            // get periode per 1 day
            $interval = DateInterval::createFromDateString('1 day');
            $_dateRange = new DatePeriod($begin, $interval, $endtime);

            $dateRange = [];

            foreach ( $_dateRange as $date ) {
                $dateRange[] = $date->format("Y-m-d");
            }

            $campaign_view = DB::select("
                                select date_format(convert_tz(created_at, '+00:00', ?), '%Y-%m-%d') as date,
                                        count(activity_id) as value
                                from
                                    {$tablePrefix}activities ac
                                where
                                    ac.object_id = ?
                                        and ((ac.activity_name = 'view_coupon'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'Coupon'
                                        and ac.group = 'mobile-ci'
                                        and (ac.role = 'Consumer' or ac.role = 'Guest'))

                                        or (ac.activity_name = 'view_news'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'News'
                                        and ac.group = 'mobile-ci'
                                        and (ac.role = 'Consumer' or ac.role = 'Guest'))

                                        or (ac.activity_name = 'view_promotion'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'Promotion'
                                        and ac.group = 'mobile-ci'
                                        and (ac.role = 'Consumer' or ac.role = 'Guest')))
                                        and ac.location_id = ?
                                        and ac.created_at between ? and ?
                                group by 1
                                order by 1
            ", array($timezoneOffset, $campaign_id, $current_mall, $start_date, $end_date));


            $pop_up_view = DB::select("
                                select date_format(convert_tz(created_at, '+00:00', ?), '%Y-%m-%d') as date,
                                        count(activity_id) as value
                                from
                                    {$tablePrefix}activities ac
                                where
                                    ac.object_id = ?
                                        and ((ac.activity_name = 'view_coupon_popup'
                                        and ac.activity_name_long = 'View Coupon Pop Up'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'Coupon'
                                        and ac.group = 'mobile-ci'
                                        and (ac.role = 'Consumer' or ac.role = 'Guest'))
                                        or (ac.activity_name = 'view_news_popup'
                                        and ac.activity_name_long = 'View News Pop Up'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'News'
                                        and ac.group = 'mobile-ci'
                                        and (ac.role = 'Consumer' or ac.role = 'Guest'))
                                        or (ac.activity_name = 'view_promotion_popup'
                                        and ac.activity_name_long = 'View Promotion Pop Up'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'Promotion'
                                        and ac.group = 'mobile-ci'
                                        and (ac.role = 'Consumer' or ac.role = 'Guest')))
                                        and ac.location_id = ?
                                        and ac.created_at between ? and ?
                                group by 1
                                order by 1
            ", array($timezoneOffset, $campaign_id, $current_mall, $start_date, $end_date));

            $pop_up_click = DB::select("
                                select date_format(convert_tz(created_at, '+00:00', ?), '%Y-%m-%d') as date,
                                        count(activity_id) as value
                                from
                                    {$tablePrefix}activities ac
                                where
                                    ac.object_id = ?
                                        and ((ac.activity_name = 'click_coupon_popup'
                                        and ac.activity_name_long = 'Click Coupon Pop Up'
                                        and ac.activity_type = 'click'
                                        and ac.module_name = 'Coupon'
                                        and ac.group = 'mobile-ci'
                                        and (ac.role = 'Consumer' or ac.role = 'Guest'))
                                        or (ac.activity_name = 'click_news_popup'
                                        and ac.activity_name_long = 'Click News Pop Up'
                                        and ac.activity_type = 'click'
                                        and ac.module_name = 'News'
                                        and ac.group = 'mobile-ci'
                                        and (ac.role = 'Consumer' or ac.role = 'Guest'))
                                        or (ac.activity_name = 'click_promotion_popup'
                                        and ac.activity_name_long = 'Click Promotion Pop Up'
                                        and ac.activity_type = 'click'
                                        and ac.module_name = 'Promotion'
                                        and ac.group = 'mobile-ci'
                                        and (ac.role = 'Consumer' or ac.role = 'Guest')))
                                        and ac.location_id = ?
                                        and ac.created_at between ? and ?
                                group by 1
                                order by 1
            ", array($timezoneOffset, $campaign_id, $current_mall, $start_date, $end_date));

            $unique_user = DB::select("select date_format(convert_tz(created_at, '+00:00', ?), '%Y-%m-%d') as date, count(distinct user_id) as value
                        from {$tablePrefix}user_signin
                        where location_id = ?
                            and created_at between ? and ?
                        group by 1
                        order by 1
            ", array($timezoneOffset, $current_mall, $start_date, $end_date));


            function cmp($a, $b)
            {
                return strcmp($a->date, $b->date);
            }


            // if there is date that have no data
            $dateRange_campaign_view = $dateRange;
            $dateRange_pop_up_view = $dateRange;
            $dateRange_pop_up_click = $dateRange;
            $dateRange_unique_user = $dateRange;

            foreach ($campaign_view as $a => $b) {
                $length = count($dateRange);
                for ($i = 0; $i < $length; $i++) {
                    if ($campaign_view[$a]->date === $dateRange[$i]) {
                        unset($dateRange_campaign_view[$i]);
                    }
                }
            }

            foreach ($pop_up_view as $a => $b) {
                $length = count($dateRange);
                for ($i = 0; $i < $length; $i++) {
                    if ($pop_up_view[$a]->date === $dateRange[$i]) {
                        unset($dateRange_pop_up_view[$i]);
                    }
                }
            }

            foreach ($pop_up_click as $a => $b) {
                $length = count($dateRange);
                for ($i = 0; $i < $length; $i++) {
                    if ($pop_up_click[$a]->date === $dateRange[$i]) {
                        unset($dateRange_pop_up_click[$i]);
                    }
                }
            }

            foreach ($unique_user as $a => $b) {
                $length = count($dateRange);
                for ($i = 0; $i < $length; $i++) {
                    if ($unique_user[$a]->date === $dateRange[$i]) {
                        unset($dateRange_unique_user[$i]);
                    }
                }
            }

            foreach ($dateRange_campaign_view as $key => $value) {
                $vw = new stdclass();
                $vw->date = $dateRange_campaign_view[$key];
                $vw->value = 0;
                $campaign_view[] = $vw;
            }

            foreach ($dateRange_pop_up_view as $key => $value) {
                $vw = new stdclass();
                $vw->date = $dateRange_pop_up_view[$key];
                $vw->value = 0;
                $pop_up_view[] = $vw;
            }

            foreach ($dateRange_pop_up_click as $key => $value) {
                $vw = new stdclass();
                $vw->date = $dateRange_pop_up_click[$key];
                $vw->value = 0;
                $pop_up_click[] = $vw;
            }

            foreach ($dateRange_unique_user as $key => $value) {
                $vw = new stdclass();
                $vw->date = $dateRange_unique_user[$key];
                $vw->value = 0;
                $unique_user[] = $vw;
            }

            usort($campaign_view, "cmp");
            usort($pop_up_view, "cmp");
            usort($pop_up_click, "cmp");
            usort($unique_user, "cmp");

            $total_campaign_view = 0;
            $total_pop_up_view = 0;
            $total_pop_up_click = 0;
            $total_unique_user = 0;

            if ( !empty($campaign_view) ) {
                foreach ($campaign_view as $key => $value) {
                    $total_campaign_view += $campaign_view[$key]->value;
                }
            }

            if ( !empty($pop_up_view) ) {
                foreach ($pop_up_view as $key => $value) {
                    $total_pop_up_view += $pop_up_view[$key]->value;
                }
            }

            if ( !empty($pop_up_click) ) {
                foreach ($pop_up_click as $key => $value) {
                    $total_pop_up_click += $pop_up_click[$key]->value;
                }
            }

            if ( !empty($unique_user) ) {
                foreach ($unique_user as $key => $value) {
                    $total_unique_user += $unique_user[$key]->value;
                }
            }

            $total = new stdclass();
            $total->total_campaign_view = $total_campaign_view;
            $total->total_pop_up_view = $total_pop_up_view;
            $total->total_pop_up_click = $total_pop_up_click;
            $total->total_unique_user = $total_unique_user;

            $data = new stdclass();
            $data->campaign_view = $campaign_view;
            $data->pop_up_view = $pop_up_view;
            $data->pop_up_click = $pop_up_click;
            $data->unique_user = $unique_user;
            $data->total = $total;

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dashboard.getcampaignoverview.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dashboard.getcampaignoverview.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.dashboard.getcampaignoverview.query.error', array($this, $e));

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
            Event::fire('orbit.dashboard.getcampaignoverview.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dashboard.getcampaignoverview.before.render', array($this, &$output));

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
}
