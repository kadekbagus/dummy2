<?php
/**
 * An API controller for managing Campaign report.
 */
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
     * @param string   `sortby`                (optional) - Column order by. Valid value: updated_date, created_at, campaign_name, campaign_type, tenant, mall_name, begin_date, end_date, page_views, views, clicks, daily, estimated_total, spending, status
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param string   `redeemed_by            (optional) - Filtering redeemed by cs or tenant only
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
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
                    'sort_by' => 'in:updated_at,campaign_name,campaign_type,tenant,mall_name,begin_date,end_date,page_views,popup_views,popup_clicks,base_price,estimated_total,spending,status',
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

            // Builder object
            $now = date('Y-m-d H:i:s');
            $tablePrefix = DB::getTablePrefix();

            //get total cost news
            $news = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, news_name AS campaign_name, {$tablePrefix}news.object_type AS campaign_type,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}news.begin_date, {$tablePrefix}news.end_date, {$tablePrefix}news.updated_at, {$tablePrefix}campaign_price.base_price,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF( {$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS estimated_total,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_news'
                ) as page_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_news_popup'
                ) as popup_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_news_popup'
                ) as popup_clicks,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_news_popup'
                ) as spending,
                {$tablePrefix}news.status"))
                        ->join('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->join('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('merchants.status', '=', 'active')
                        ->where('news.mall_id', '=', $current_mall)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->whereRaw("{$tablePrefix}news.begin_date between ? and ?", [$start_date, $end_date])
                            ->orWhereRaw("{$tablePrefix}news.end_date between ? and ?", [$start_date, $end_date]);
                        })
                        ->where('campaign_price.campaign_type', '=', 'news')
                        ->where('news.object_type', '=', 'news')
                        ->groupBy('news.news_id')
                        ;

            $promotions = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, news_name AS campaign_name, {$tablePrefix}news.object_type AS campaign_type,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}news.begin_date, {$tablePrefix}news.end_date, {$tablePrefix}news.updated_at, {$tablePrefix}campaign_price.base_price,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS estimated_total,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_promotion'
                ) as page_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_news_promotion'
                ) as popup_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_promotion_popup'
                ) as popup_clicks,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_promotion_popup'
                ) as spending,
                {$tablePrefix}news.status"))
                        ->join('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->join('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('merchants.status', '=', 'active')
                        ->where('news.mall_id', '=', $current_mall)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->whereRaw("{$tablePrefix}news.begin_date between ? and ?", [$start_date, $end_date])
                            ->orWhereRaw("{$tablePrefix}news.end_date between ? and ?", [$start_date, $end_date]);
                        })
                        ->where('campaign_price.campaign_type', '=', 'promotion')
                        ->where('news.object_type', '=', 'promotion')
                        ->groupBy('news.news_id')
                        ;

            $coupons = DB::table('promotions')->selectraw(DB::raw("{$tablePrefix}promotions.promotion_id AS campaign_id, promotion_name AS campaign_name, IF(1=1,'coupon', '') AS campaign_type,
                COUNT({$tablePrefix}promotion_retailer.promotion_retailer_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}promotions.begin_date, {$tablePrefix}promotions.end_date, {$tablePrefix}promotions.updated_at, {$tablePrefix}campaign_price.base_price,
                COUNT({$tablePrefix}promotion_retailer.promotion_retailer_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}promotions.end_date, {$tablePrefix}promotions.begin_date) + 1) AS estimated_total,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_coupon'
                ) as page_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_news_coupon'
                ) as popup_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_coupon_popup'
                ) as popup_clicks,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_coupon_popup'
                ) as spending,
                {$tablePrefix}promotions.status"))
                        ->join('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'promotions.promotion_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                        ->join('merchants as merchants2', 'promotions.merchant_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('merchants.status', '=', 'active')
                        ->where('promotions.merchant_id', '=', $current_mall)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->whereRaw("{$tablePrefix}promotions.begin_date between ? and ?", [$start_date, $end_date])
                            ->orWhereRaw("{$tablePrefix}promotions.end_date between ? and ?", [$start_date, $end_date]);
                        })
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
                $campaign->where('campaign_type', $campaign_type);
            });

            // Filter by tenant
            // OrbitInput::get('tenant', function($tenant) use ($campaign) {
            //     $campaign->where('campaign_name', 'like', "%$campaign_name%");
            // });

            // Filter by mall
            OrbitInput::get('mall_name', function($mall_name) use ($campaign) {
                $campaign->where('mall_name', $mall_name);
            });

            // Filter by campaign status
            OrbitInput::get('status', function($status) use ($campaign) {
                $status = (array)$status;
                $campaign->whereIn('status', $status);
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
                    'tenant'          => 'tenant',
                    'mall_name'       => 'mall_name',
                    'begin_date'      => 'begin_date',
                    'end_date'        => 'end_date',
                    'page_views'      => 'page_views',
                    'popup_views'     => 'popup_views',
                    'popup_clicks'    => 'popup_clicks',
                    'base_price'      => 'base_price',
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
     * @param string   `sortby`                (optional) - Column order by. Valid value: updated_date, created_at, campaign_name, campaign_type, tenant, mall_name, begin_date, end_date, page_views, views, clicks, daily, estimated_total, spending, status
     * @param string   `campaign_id            (optional) - Campaign id (news_id, promotion_id, coupon_id)
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param string   `redeemed_by            (optional) - Filtering redeemed by cs or tenant only
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
                    'sort_by' => 'in:updated_at,campaign_name,campaign_type,tenant,mall_name,begin_date,end_date,page_views,popup_views,popup_clicks,base_price,estimated_total,spending,status',
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

            //get total cost news
            $news = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, news_name AS campaign_name, {$tablePrefix}news.object_type AS campaign_type,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}news.begin_date, {$tablePrefix}news.end_date, {$tablePrefix}news.updated_at, {$tablePrefix}campaign_price.base_price,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF( {$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS estimated_total,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_news'
                ) as page_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_news_popup'
                ) as popup_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_news_popup'
                ) as popup_clicks,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_news_popup'
                ) as spending,
                {$tablePrefix}news.status"))
                        ->join('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->join('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('merchants.status', '=', 'active')
                        ->where('news.mall_id', '=', $current_mall)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->whereRaw("{$tablePrefix}news.begin_date between ? and ?", [$start_date, $end_date])
                            ->orWhereRaw("{$tablePrefix}news.end_date between ? and ?", [$start_date, $end_date]);
                        })
                        ->where('campaign_price.campaign_type', '=', 'news')
                        ->where('news.object_type', '=', 'news')
                        ->groupBy('news.news_id')
                        ;

            $promotions = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, news_name AS campaign_name, {$tablePrefix}news.object_type AS campaign_type,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}news.begin_date, {$tablePrefix}news.end_date, {$tablePrefix}news.updated_at, {$tablePrefix}campaign_price.base_price,
                COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS estimated_total,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_promotion'
                ) as page_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_news_promotion'
                ) as popup_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_promotion_popup'
                ) as popup_clicks,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_promotion_popup'
                ) as spending,
                {$tablePrefix}news.status"))
                        ->join('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->join('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('merchants.status', '=', 'active')
                        ->where('news.mall_id', '=', $current_mall)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->whereRaw("{$tablePrefix}news.begin_date between ? and ?", [$start_date, $end_date])
                            ->orWhereRaw("{$tablePrefix}news.end_date between ? and ?", [$start_date, $end_date]);
                        })
                        ->where('campaign_price.campaign_type', '=', 'promotion')
                        ->where('news.object_type', '=', 'promotion')
                        ->groupBy('news.news_id')
                        ;

            $coupons = DB::table('promotions')->selectraw(DB::raw("{$tablePrefix}promotions.promotion_id AS campaign_id, promotion_name AS campaign_name, IF(1=1,'coupon', '') AS campaign_type,
                COUNT({$tablePrefix}promotion_retailer.promotion_retailer_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}promotions.begin_date, {$tablePrefix}promotions.end_date, {$tablePrefix}promotions.updated_at, {$tablePrefix}campaign_price.base_price,
                COUNT({$tablePrefix}promotion_retailer.promotion_retailer_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}promotions.end_date, {$tablePrefix}promotions.begin_date) + 1) AS estimated_total,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_coupon'
                ) as page_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'view_news_coupon'
                ) as popup_views,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_coupon_popup'
                ) as popup_clicks,
                (
                    SELECT COUNT({$tablePrefix}activities.activity_id)
                    FROM {$tablePrefix}activities
                    WHERE `campaign_id` = {$tablePrefix}activities.object_id
                    AND {$tablePrefix}activities.activity_name = 'click_coupon_popup'
                ) as spending,
                {$tablePrefix}promotions.status"))
                        ->join('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'promotions.promotion_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                        ->join('merchants as merchants2', 'promotions.merchant_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('merchants.status', '=', 'active')
                        ->where('promotions.merchant_id', '=', $current_mall)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->whereRaw("{$tablePrefix}promotions.begin_date between ? and ?", [$start_date, $end_date])
                            ->orWhereRaw("{$tablePrefix}promotions.end_date between ? and ?", [$start_date, $end_date]);
                        })
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
                $campaign->where('campaign_type', $campaign_type);
            });

            // Filter by tenant
            // OrbitInput::get('tenant', function($tenant) use ($campaign) {
            //     $campaign->where('campaign_name', 'like', "%$campaign_name%");
            // });

            // Filter by mall
            OrbitInput::get('mall_name', function($mall_name) use ($campaign) {
                $campaign->where('mall_name', $mall_name);
            });

            // Filter by campaign status
            OrbitInput::get('status', function($status) use ($campaign) {
                $status = (array)$status;
                $campaign->whereIn('status', $status);
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
                    'tenant'          => 'tenant',
                    'mall_name'       => 'mall_name',
                    'begin_date'      => 'begin_date',
                    'end_date'        => 'end_date',
                    'page_views'      => 'page_views',
                    'popup_views'     => 'popup_views',
                    'popup_clicks'    => 'popup_clicks',
                    'base_price'      => 'base_price',
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
                        group by {$tablePrefix}activities.user_id
                    ) as A
            ", array($campaign_id, $current_mall, $start_date, $end_date));

            $demograhicMale = DB::select($query . "
                        AND {$tablePrefix}user_details.gender = 'm'
                        AND {$tablePrefix}activities.created_at between ? and ?
                        group by {$tablePrefix}activities.user_id
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
     * @author Qosdil A. <qosdil@dominopos.com>
     */
    public function getSpending()
    {
        $startDate = OrbitInput::get('start_date');
        $endDate = OrbitInput::get('end_date');
        $this->response->data = [
            ['date' => '10/12/2015', 'cost' => '195,000'],
            ['date' => '11/12/2015', 'cost' => '295,000'],
            ['date' => '12/12/2015', 'cost' => '395,000'],
        ];

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
                                        and ac.activity_name_long = 'View Coupon Detail'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'Coupon'
                                        and ac.group = 'mobile-ci'
                                        and ac.role = 'Consumer')
                                        or (ac.activity_name = 'view_news'
                                        and ac.activity_name_long = 'View News Detail'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'News'
                                        and ac.group = 'mobile-ci'
                                        and ac.role = 'Consumer')
                                        or (ac.activity_name = 'view_promotion'
                                        and ac.activity_name_long = 'View Promotion Detail'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'Promotion'
                                        and ac.group = 'mobile-ci'
                                        and ac.role = 'Consumer'))
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
                                        and ac.role = 'Consumer')
                                        or (ac.activity_name = 'view_news_popup'
                                        and ac.activity_name_long = 'View News Pop Up'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'News'
                                        and ac.group = 'mobile-ci'
                                        and ac.role = 'Consumer')
                                        or (ac.activity_name = 'view_promotion_popup'
                                        and ac.activity_name_long = 'View Promotion Pop Up'
                                        and ac.activity_type = 'view'
                                        and ac.module_name = 'Promotion'
                                        and ac.group = 'mobile-ci'
                                        and ac.role = 'Consumer'))
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
                                        and ac.role = 'Consumer')
                                        or (ac.activity_name = 'click_news_popup'
                                        and ac.activity_name_long = 'Click News Pop Up'
                                        and ac.activity_type = 'click'
                                        and ac.module_name = 'News'
                                        and ac.group = 'mobile-ci'
                                        and ac.role = 'Consumer')
                                        or (ac.activity_name = 'click_promotion_popup'
                                        and ac.activity_name_long = 'Click Promotion Pop Up'
                                        and ac.activity_type = 'click'
                                        and ac.module_name = 'Promotion'
                                        and ac.group = 'mobile-ci'
                                        and ac.role = 'Consumer'))
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
