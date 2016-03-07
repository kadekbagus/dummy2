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
     * There should be a Carbon method for this.
     *
     * @param string $timezone The timezone name, e.g. 'Asia/Jakarta'.
     * @return string The hours diff, e.g. '+07:00'.
     * @author Qosdil A. <qosdil@gmail.com>
     */
    private function getTimezoneHoursDiff($timezone)
    {
        $mallDateTime = Carbon::createFromFormat('Y-m-d H:i:s', '2016-01-01 00:00:00', $timezone);
        $utcDateTime = Carbon::createFromFormat('Y-m-d H:i:s', '2016-01-01 00:00:00');
        $diff = $mallDateTime->diff($utcDateTime);
        $sign = ($diff->invert) ? '-' : '+';
        $hour = ($diff->h < 10) ? '0'.$diff->h : $diff->h;
        return $sign.$hour.':00';
    }

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
            $sort_by = OrbitInput::get('sortby');

            // Filter by date
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

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
            $now_ymd = $now->toDateString();

            $timezone = $this->getTimezone($mall->merchant_id);

            // Get now date with timezone
            $timezoneOffset = $this->getTimezoneOffset($timezone);

            // Builder object
            $tablePrefix = DB::getTablePrefix();

            // Get id add_tenant and delete_tenant for counting total tenant percampaign
            $campaignHistoryAction = DB::table('campaign_history_actions')
                            ->select('campaign_history_action_id','action_name')
                            ->where('action_name','add_tenant')
                            ->orWhere('action_name','delete_tenant')
                            ->get();

            $idAddTenant = '';
            $idDeleteTenant = '';
            foreach ($campaignHistoryAction as $key => $value) {
                if ($value->action_name === 'add_tenant') {
                    $idAddTenant = $value->campaign_history_action_id;
                } elseif ($value->action_name === 'delete_tenant') {
                    $idDeleteTenant = $value->campaign_history_action_id;
                }
            }

            // Get data all campaign (news, promotions, coupons), and then use union to join all campaign
            $news = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, 
                CASE WHEN {$tablePrefix}news_translations.news_name !='' THEN {$tablePrefix}news_translations.news_name ELSE {$tablePrefix}news.news_name END as campaign_name,
                {$tablePrefix}news.object_type AS campaign_type,
                IFNULL(total_tenant, 0) AS total_tenant, tenant_name,
                merchants2.name AS mall_name, {$tablePrefix}news.begin_date, {$tablePrefix}news.end_date, {$tablePrefix}news.updated_at, {$tablePrefix}campaign_price.base_price,
                total_tenant * {$tablePrefix}campaign_price.base_price AS daily,
                total_tenant * {$tablePrefix}campaign_price.base_price * (DATEDIFF( {$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS estimated_total,
                (

                    SELECT IFNULL(fnc_campaign_cost(campaign_id, 'news', {$tablePrefix}news.begin_date, {$this->quote($now)}, {$this->quote($timezoneOffset)}), 0.00) AS campaign_total_cost
                ) as spending,
                (
                    select count(campaign_page_view_id) as value
                    from {$tablePrefix}campaign_page_views
                    where campaign_id = {$tablePrefix}news.news_id
                    and location_id = {$this->quote($current_mall)}
                ) as page_views,
                (
                    select count(campaign_popup_view_id) as value
                    from {$tablePrefix}campaign_popup_views
                    where campaign_id = {$tablePrefix}news.news_id
                    and location_id = {$this->quote($current_mall)}
                ) as popup_views,
                (
                    select count(campaign_click_id) as value
                    from {$tablePrefix}campaign_clicks
                    where campaign_id = {$tablePrefix}news.news_id
                    and location_id = {$this->quote($current_mall)}
                ) as popup_clicks,
                {$tablePrefix}news.status, CASE WHEN {$tablePrefix}campaign_status.campaign_status_name = 'expired' THEN {$tablePrefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$tablePrefix}news.end_date < {$this->quote($now)} THEN 'expired' ELSE {$tablePrefix}campaign_status.campaign_status_name END) END  AS campaign_status, {$tablePrefix}campaign_status.order"))
                        ->leftJoin('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        // Join for get mall name
                        ->leftJoin('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        // Join for get total tenant percampaign
                        ->leftJoin(DB::raw("
                                (
                                    SELECT campaign_id as v_campaign_id, count(campaign_id) as total_tenant FROM
                                        (SELECT * FROM
                                            (
                                                SELECT
                                                    och.campaign_id,
                                                    och.campaign_history_action_id,
                                                    och.campaign_external_value,
                                                    om.name,
                                                    DATE_FORMAT(j_on.end_date, '%Y-%m-%d') AS end_date,
                                                    DATE_FORMAT(och.created_at, '%Y-%m-%d %H:00:00') AS history_created_date
                                                FROM {$tablePrefix}campaign_histories och
                                                LEFT JOIN {$tablePrefix}merchants om
                                                ON om.merchant_id = och.campaign_external_value
                                                LEFT JOIN {$tablePrefix}news j_on
                                                ON j_on.news_id = och.campaign_id
                                                WHERE
                                                    och.campaign_history_action_id IN ({$this->quote($idAddTenant)}, {$this->quote($idDeleteTenant)})
                                                    AND och.campaign_type = 'news'
                                                    AND DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') <= IF( DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d') < end_date, DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d'), end_date )
                                                ORDER BY och.created_at DESC
                                            ) as A
                                        group by campaign_id, campaign_external_value) as B
                                    WHERE (
                                        case when campaign_history_action_id = {$this->quote($idDeleteTenant)}
                                        and DATE_FORMAT(CONVERT_TZ(history_created_date, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') < IF( DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d') < end_date, DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d'), end_date )
                                        then campaign_history_action_id != {$this->quote($idDeleteTenant)} else true end
                                    )
                                    group by campaign_id
                                ) AS lf_total_tenant
                        "),
                        // On
                        DB::raw('lf_total_tenant.v_campaign_id'), '=', 'news.news_id')

                        // Join for provide searching by tenant
                        ->leftJoin(DB::raw("
                                (
                                    SELECT campaign_id as t_campaign_id, tenant_name
                                    FROM
                                        (
                                            SELECT * FROM
                                            (
                                                SELECT
                                                    och.campaign_id,
                                                    och.campaign_history_action_id,
                                                    och.campaign_external_value,
                                                    om.name as tenant_name,
                                                    DATE_FORMAT(j_on.end_date, '%Y-%m-%d') AS end_date,
                                                    DATE_FORMAT(och.created_at, '%Y-%m-%d %H:00:00') AS history_created_date
                                                FROM {$tablePrefix}campaign_histories och
                                                LEFT JOIN {$tablePrefix}merchants om
                                                ON om.merchant_id = och.campaign_external_value
                                                LEFT JOIN {$tablePrefix}news j_on
                                                ON j_on.news_id = och.campaign_id
                                                WHERE
                                                    och.campaign_history_action_id IN ({$this->quote($idAddTenant)}, {$this->quote($idDeleteTenant)})
                                                    AND och.campaign_type = 'news'
                                                    AND DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') <= IF( DATE_FORMAT(NOW(), '%Y-%m-%d') < end_date, DATE_FORMAT(NOW(), '%Y-%m-%d'), end_date )
                                                ORDER BY och.created_at DESC
                                            ) as A
                                            group by campaign_id, campaign_external_value) as B
                                            WHERE (
                                                case when campaign_history_action_id = {$this->quote($idDeleteTenant)}
                                                and DATE_FORMAT(CONVERT_TZ(history_created_date, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') < IF( DATE_FORMAT(NOW(), '%Y-%m-%d') < end_date, DATE_FORMAT(NOW(), '%Y-%m-%d'), end_date )
                                                then campaign_history_action_id != {$this->quote($idDeleteTenant)} else true end
                                        )
                                    ) as tenant
                            "),
                        // On
                        DB::raw('tenant.t_campaign_id'), '=', 'news.news_id')

                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                        ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'news_translations.merchant_language_id')
                        ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                        ->where('languages.name', '=', 'en')
                        ->where('news.mall_id', '=', $current_mall)
                        ->where('news.object_type', '=', 'news');

            $promotions = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, 
                CASE WHEN {$tablePrefix}news_translations.news_name !='' THEN {$tablePrefix}news_translations.news_name ELSE {$tablePrefix}news.news_name END as campaign_name, 
                {$tablePrefix}news.object_type AS campaign_type,
                IFNULL(total_tenant, 0) AS total_tenant, tenant_name,
                merchants2.name AS mall_name, {$tablePrefix}news.begin_date, {$tablePrefix}news.end_date, {$tablePrefix}news.updated_at, {$tablePrefix}campaign_price.base_price,
                total_tenant * {$tablePrefix}campaign_price.base_price AS daily,
                total_tenant * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS estimated_total,
                (

                    SELECT IFNULL(fnc_campaign_cost(campaign_id, 'promotion', {$tablePrefix}news.begin_date, {$this->quote($now)}, {$this->quote($timezoneOffset)}), 0.00) AS campaign_total_cost
                ) as spending,
                (
                    select count(campaign_page_view_id) as value
                    from {$tablePrefix}campaign_page_views
                    where campaign_id = {$tablePrefix}news.news_id
                    and location_id = {$this->quote($current_mall)}
                ) as page_views,
                (
                    select count(campaign_popup_view_id) as value
                    from {$tablePrefix}campaign_popup_views
                    where campaign_id = {$tablePrefix}news.news_id
                    and location_id = {$this->quote($current_mall)}
                ) as popup_views,
                (
                    select count(campaign_click_id) as value
                    from {$tablePrefix}campaign_clicks
                    where campaign_id = {$tablePrefix}news.news_id
                    and location_id = {$this->quote($current_mall)}
                ) as popup_clicks,
                {$tablePrefix}news.status, CASE WHEN {$tablePrefix}campaign_status.campaign_status_name = 'expired' THEN {$tablePrefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$tablePrefix}news.end_date < {$this->quote($now)} THEN 'expired' ELSE {$tablePrefix}campaign_status.campaign_status_name END) END  AS campaign_status, {$tablePrefix}campaign_status.order"))
                        ->leftJoin('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        ->leftJoin('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        // Joint for get total tenant percampaign
                        ->leftJoin(DB::raw("
                                (
                                    SELECT campaign_id as v_campaign_id, count(campaign_id) as total_tenant FROM
                                        (SELECT * FROM
                                            (
                                                SELECT
                                                    och.campaign_id,
                                                    och.campaign_history_action_id,
                                                    och.campaign_external_value,
                                                    om.name,
                                                    DATE_FORMAT(j_on.end_date, '%Y-%m-%d') AS end_date,
                                                    DATE_FORMAT(och.created_at, '%Y-%m-%d %H:00:00') AS history_created_date
                                                FROM {$tablePrefix}campaign_histories och
                                                LEFT JOIN {$tablePrefix}merchants om
                                                ON om.merchant_id = och.campaign_external_value
                                                LEFT JOIN {$tablePrefix}news j_on
                                                ON j_on.news_id = och.campaign_id
                                                WHERE
                                                    och.campaign_history_action_id IN ({$this->quote($idAddTenant)}, {$this->quote($idDeleteTenant)})
                                                    AND och.campaign_type = 'promotion'
                                                    AND DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') <= IF( DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d') < end_date, DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d'), end_date )
                                                ORDER BY och.created_at DESC
                                            ) as A
                                        group by campaign_id, campaign_external_value) as B
                                    WHERE (
                                        case when campaign_history_action_id = {$this->quote($idDeleteTenant)}
                                        and DATE_FORMAT(CONVERT_TZ(history_created_date, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') < IF( DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d') < end_date, DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d'), end_date )
                                        then campaign_history_action_id != {$this->quote($idDeleteTenant)} else true end
                                    )
                                    group by campaign_id
                                ) AS lf_total_tenant
                        "),
                        // On
                        DB::raw('lf_total_tenant.v_campaign_id'), '=', 'news.news_id')

                        // Join for get tenant percampaign
                        ->leftJoin(DB::raw("
                                (
                                    SELECT campaign_id as t_campaign_id, tenant_name
                                    FROM
                                        (
                                            SELECT * FROM
                                            (
                                                SELECT
                                                    och.campaign_id,
                                                    och.campaign_history_action_id,
                                                    och.campaign_external_value,
                                                    om.name as tenant_name,
                                                    DATE_FORMAT(j_on.end_date, '%Y-%m-%d') AS end_date,
                                                    DATE_FORMAT(och.created_at, '%Y-%m-%d %H:00:00') AS history_created_date
                                                FROM {$tablePrefix}campaign_histories och
                                                LEFT JOIN {$tablePrefix}merchants om
                                                ON om.merchant_id = och.campaign_external_value
                                                LEFT JOIN {$tablePrefix}news j_on
                                                ON j_on.news_id = och.campaign_id
                                                WHERE
                                                    och.campaign_history_action_id IN ({$this->quote($idAddTenant)}, {$this->quote($idDeleteTenant)})
                                                    AND och.campaign_type = 'promotion'
                                                    AND DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') <= IF( DATE_FORMAT(NOW(), '%Y-%m-%d') < end_date, DATE_FORMAT(NOW(), '%Y-%m-%d'), end_date )
                                                ORDER BY och.created_at DESC
                                            ) as A
                                            group by campaign_id, campaign_external_value) as B
                                            WHERE (
                                                case when campaign_history_action_id = {$this->quote($idDeleteTenant)}
                                                and DATE_FORMAT(CONVERT_TZ(history_created_date, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') < IF( DATE_FORMAT(NOW(), '%Y-%m-%d') < end_date, DATE_FORMAT(NOW(), '%Y-%m-%d'), end_date )
                                                then campaign_history_action_id != {$this->quote($idDeleteTenant)} else true end
                                        )
                                    ) as tenant
                            "),
                        // On
                        DB::raw('tenant.t_campaign_id'), '=', 'news.news_id')

                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                        ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'news_translations.merchant_language_id')
                        ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                        ->where('languages.name', '=', 'en')
                        ->where('news.mall_id', '=', $current_mall)
                        ->where('news.object_type', '=', 'promotion');


            $coupons = DB::table('promotions')->selectraw(DB::raw("{$tablePrefix}promotions.promotion_id AS campaign_id, 
                CASE WHEN {$tablePrefix}coupon_translations.promotion_name !='' THEN {$tablePrefix}coupon_translations.promotion_name ELSE {$tablePrefix}promotions.promotion_name END as campaign_name, 
                IF(1=1,'coupon', '') AS campaign_type,
                IFNULL(total_tenant, 0) AS total_tenant, tenant_name,
                merchants2.name AS mall_name, {$tablePrefix}promotions.begin_date, {$tablePrefix}promotions.end_date, {$tablePrefix}promotions.updated_at, {$tablePrefix}campaign_price.base_price,
                total_tenant * {$tablePrefix}campaign_price.base_price AS daily,
                total_tenant * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}promotions.end_date, {$tablePrefix}promotions.begin_date) + 1) AS estimated_total,
                (

                    SELECT IFNULL(fnc_campaign_cost(campaign_id, 'coupon', {$tablePrefix}promotions.begin_date, {$this->quote($now)}, {$this->quote($timezoneOffset)}), 0.00) AS campaign_total_cost
                ) as spending,
                (
                    select count(campaign_page_view_id) as value
                    from {$tablePrefix}campaign_page_views
                    where campaign_id = {$tablePrefix}promotions.promotion_id
                    and location_id = {$this->quote($current_mall)}
                ) as page_views,
                (
                    select count(campaign_popup_view_id) as value
                    from {$tablePrefix}campaign_popup_views
                    where campaign_id = {$tablePrefix}promotions.promotion_id
                    and location_id = {$this->quote($current_mall)}
                ) as popup_views,
                (
                    select count(campaign_click_id) as value
                    from {$tablePrefix}campaign_clicks
                    where campaign_id = {$tablePrefix}promotions.promotion_id
                    and location_id = {$this->quote($current_mall)}
                ) as popup_clicks,
                {$tablePrefix}promotions.status, CASE WHEN {$tablePrefix}campaign_status.campaign_status_name = 'expired' THEN {$tablePrefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$tablePrefix}promotions.end_date < {$this->quote($now)} THEN 'expired' ELSE {$tablePrefix}campaign_status.campaign_status_name END) END AS campaign_status, {$tablePrefix}campaign_status.order"))
                        ->leftJoin('campaign_price', 'campaign_price.campaign_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchants as merchants2', 'promotions.merchant_id', '=', DB::raw('merchants2.merchant_id'))
                        // Joint for get total tenant percampaign
                        ->leftJoin(DB::raw("
                                (
                                    SELECT campaign_id as v_campaign_id, count(campaign_id) as total_tenant FROM
                                        (SELECT * FROM
                                            (
                                                SELECT
                                                    och.campaign_id,
                                                    och.campaign_history_action_id,
                                                    och.campaign_external_value,
                                                    om.name,
                                                    DATE_FORMAT(j_on.end_date, '%Y-%m-%d') AS end_date,
                                                    DATE_FORMAT(och.created_at, '%Y-%m-%d %H:00:00') AS history_created_date
                                                FROM {$tablePrefix}campaign_histories och
                                                LEFT JOIN {$tablePrefix}merchants om
                                                ON om.merchant_id = och.campaign_external_value
                                                LEFT JOIN {$tablePrefix}promotions j_on
                                                ON j_on.promotion_id = och.campaign_id
                                                WHERE
                                                    och.campaign_history_action_id IN ({$this->quote($idAddTenant)}, {$this->quote($idDeleteTenant)})
                                                    AND och.campaign_type = 'coupon'
                                                    AND DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') <= IF( DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d') < end_date, DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d'), end_date )
                                                ORDER BY och.created_at DESC
                                            ) as A
                                        group by campaign_id, campaign_external_value) as B
                                    WHERE (
                                        case when campaign_history_action_id = {$this->quote($idDeleteTenant)}
                                        and DATE_FORMAT(CONVERT_TZ(history_created_date, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') < IF( DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d') < end_date, DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d'), end_date )
                                        then campaign_history_action_id != {$this->quote($idDeleteTenant)} else true end
                                    )
                                    group by campaign_id
                                ) AS lf_total_tenant
                        "),
                        // On
                        DB::raw('lf_total_tenant.v_campaign_id'), '=', 'promotions.promotion_id')

                        // Join for get tenant percampaign
                        ->leftJoin(DB::raw("
                                (
                                    SELECT campaign_id as t_campaign_id, tenant_name
                                    FROM
                                        (
                                            SELECT * FROM
                                            (
                                                SELECT
                                                    och.campaign_id,
                                                    och.campaign_history_action_id,
                                                    och.campaign_external_value,
                                                    om.name as tenant_name,
                                                    DATE_FORMAT(j_on.end_date, '%Y-%m-%d') AS end_date,
                                                    DATE_FORMAT(och.created_at, '%Y-%m-%d %H:00:00') AS history_created_date
                                                FROM {$tablePrefix}campaign_histories och
                                                LEFT JOIN {$tablePrefix}merchants om
                                                ON om.merchant_id = och.campaign_external_value
                                                LEFT JOIN {$tablePrefix}promotions j_on
                                                ON j_on.promotion_id = och.campaign_id
                                                WHERE
                                                    och.campaign_history_action_id IN ({$this->quote($idAddTenant)}, {$this->quote($idDeleteTenant)})
                                                    AND och.campaign_type = 'coupon'
                                                    AND DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') <= IF( DATE_FORMAT(NOW(), '%Y-%m-%d') < end_date, DATE_FORMAT(NOW(), '%Y-%m-%d'), end_date )
                                                ORDER BY och.created_at DESC
                                            ) as A
                                            group by campaign_id, campaign_external_value) as B
                                            WHERE (
                                                case when campaign_history_action_id = {$this->quote($idDeleteTenant)}
                                                and DATE_FORMAT(CONVERT_TZ(history_created_date, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') < IF( DATE_FORMAT(NOW(), '%Y-%m-%d') < end_date, DATE_FORMAT(NOW(), '%Y-%m-%d'), end_date )
                                                then campaign_history_action_id != {$this->quote($idDeleteTenant)} else true end
                                        )
                                    ) as tenant
                            "),
                        // On
                        DB::raw('tenant.t_campaign_id'), '=', 'promotions.promotion_id')

                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                        ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'coupon_translations.merchant_language_id')
                        ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                        ->where('languages.name', '=', 'en')
                        ->where('promotions.merchant_id', '=', $current_mall);

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


            OrbitInput::get('campaign_type', function($campaign_type) use ($campaign) {
                $campaign->whereIn('campaign_type', $campaign_type);
            });

            // Filter by tenant
            OrbitInput::get('tenant_name', function($tenant_name) use ($campaign) {
                $campaign->where('tenant_name', 'like', "%$tenant_name%");
            });

            // Filter by mall
            OrbitInput::get('mall_name', function($mall_name) use ($campaign) {
                $campaign->where('mall_name', 'like', "%$mall_name%");
            });

            // // Filter by campaign status
            // OrbitInput::get('status', function($status) use ($campaign) {
            //     $campaign->whereIn('status', (array)$status);
            // });

            // Filter coupons by status
            OrbitInput::get('campaign_status', function ($statuses) use ($campaign, $tablePrefix, $now) {
                $campaign->whereIn(DB::raw("CASE WHEN campaign_status = 'expired' THEN campaign_status ELSE (CASE WHEN end_date < {$this->quote($now)} THEN 'expired' ELSE campaign_status END) END"), $statuses);
            });

            // Filter by range date
            if ($start_date != '' && $end_date != ''){

                // Convert UTC to Mall Time
                $startConvert = Carbon::createFromFormat('Y-m-d H:i:s', $start_date, 'UTC');
                $startConvert->setTimezone($timezone);

                $endConvert = Carbon::createFromFormat('Y-m-d H:i:s', $end_date, 'UTC');
                $endConvert->setTimezone($timezone);

                $start_date = $startConvert->toDateString();
                $end_date = $endConvert->toDateString();

                $campaign->where(function ($q) use ($start_date, $end_date) {
                    $q->WhereRaw("DATE_FORMAT(begin_date, '%Y-%m-%d') >= DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') and DATE_FORMAT(begin_date, '%Y-%m-%d') <= DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d')")
                      ->orWhereRaw("DATE_FORMAT(end_date, '%Y-%m-%d') >= DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') and DATE_FORMAT(end_date, '%Y-%m-%d') <= DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d')")
                      ->orWhereRaw("DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') >= DATE_FORMAT(begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') <= DATE_FORMAT(end_date, '%Y-%m-%d')")
                      ->orWhereRaw("DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') >= DATE_FORMAT(begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') <= DATE_FORMAT(end_date, '%Y-%m-%d')")
                      ->orWhereRaw("DATE_FORMAT({$this->quote($start_date)}, '%Y-%m-%d') <= DATE_FORMAT(begin_date, '%Y-%m-%d') and DATE_FORMAT({$this->quote($end_date)}, '%Y-%m-%d') >= DATE_FORMAT(end_date, '%Y-%m-%d')");
                });
            }

            OrbitInput::get('mall_name', function($mall_name) use ($campaign) {
                $campaign->where('mall_name', 'like', "%$mall_name%");
            });

            // Grouping campaign
            $campaign = $campaign->groupBy('campaign_id');

            // Clone the query builder which still does not include the take,
            $_campaign = clone $campaign;

            // Need to sub select after group by
            $_campaign_sql = $_campaign->toSql();

            //Cek exist binding
            if (count($campaign->getBindings()) > 0) {
                foreach($campaign->getBindings() as $binding)
                {
                  $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
                  $_campaign_sql = preg_replace('/\?/', $value, $_campaign_sql, 1);
                }
            }

            $_campaign = DB::table(DB::raw('(' . $_campaign_sql . ') as b'));

            $query_sum = array(
                'COUNT(campaign_id) AS total_records',
                'SUM(page_views) AS page_views',
                'SUM(popup_views) AS popup_views',
                'SUM(estimated_total) AS estimated_total',
                'SUM(spending) AS spending'
            );

            $total = $_campaign->selectRaw(implode(',', $query_sum))->get();

            // Get total page views
            $totalRecords = (int) isset($total[0]->total_records)?$total[0]->total_records:0;

            // Get total page views
            $totalPageViews = isset($total[0]->page_views)?$total[0]->page_views:0;

            // Get total popup views
            $totalPopupViews = isset($total[0]->popup_views)?$total[0]->popup_views:0;

            // Get total estimate
            $totalEstimated = isset($total[0]->estimated_total)?$total[0]->estimated_total:0;

            // Get total spending
            $totalSpending = isset($total[0]->spending)?$total[0]->spending:0;

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

            // skip, and order by
            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $campaign)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            // If request page from export (print/csv), showing without page limitation
            $export = OrbitInput::get('export');

            if (!isset($export)) {
                $campaign->take($take);
                $campaign->skip($skip);
            }

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
                    'status'          => 'campaign_status'
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

            //with name
            if ($sortBy !== 'campaign_name') {
                $campaign->orderBy('campaign_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return [
                    'builder' => $campaign,
                    'count' => $totalRecords,
                    'totalPageViews' => $totalPageViews,
                    'totalPopUpViews' => $totalPopupViews,
                    'totalSpending' => $totalSpending,
                    'totalEstimatedCost' => $totalEstimated,
                ];
            }

            $listOfCampaign = $campaign->get();

            $data = new stdclass();
            $data->total_records = $totalRecords;
            $data->total_page_views = $totalPageViews;
            $data->total_pop_up_views = $totalPopupViews;
            $data->total_estimated_cost = $totalEstimated;
            $data->total_spending = $totalSpending;
            $data->returned_records = count($listOfCampaign);
            $data->records = $listOfCampaign;

            if ($totalRecords == 0) {
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
                    'sort_by' => $sort_by,
                ),
                array(
                    'campaign_id' => 'required',
                    'campaign_type' => 'required',
                    'current_mall' => 'required|orbit.empty.mall',
                    'sort_by' => 'in:campaign_date,total_tenant,mall_name,unique_users,campaign_pages_views,campaign_pages_view_rate,popup_views,popup_view_rate,popup_clicks,popup_click_rate,spending',
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
            $now = Carbon::now($mall->timezone->timezone_name);

            \DB::beginTransaction();

            $procResults = DB::statement("CALL prc_campaign_detailed_cost({$this->quote($campaign_id)}, {$this->quote($campaign_type)}, {$this->quote($beginDate)}, {$this->quote($now)}, {$this->quote($timezoneOffset)})");

            if ($procResults === false) {
                // Do Nothing
            }

            // Get id add_tenant and delete_tenant for counting total tenant percampaign
            $campaignHistoryAction = DB::table('campaign_history_actions')
                            ->select('campaign_history_action_id','action_name')
                            ->where('action_name','add_tenant')
                            ->orWhere('action_name','delete_tenant')
                            ->get();

            $idAddTenant = '';
            $idDeleteTenant = '';
            foreach ($campaignHistoryAction as $key => $value) {
                if ($value->action_name === 'add_tenant') {
                    $idAddTenant = $value->campaign_history_action_id;
                } elseif ($value->action_name === 'delete_tenant') {
                    $idDeleteTenant = $value->campaign_history_action_id;
                }
            }

            $sql = "
                        (SELECT
                            comp_date AS campaign_date,
                            campaign_id,
                            campaign_number_tenant AS total_tenant,
                            tenant_name,
                            om_mall.name AS mall_name,
                            unique_users,
                            campaign_pages_views,
                            campaign_pages_view_rate,
                            popup_views,
                            popup_view_rate,
                            popup_clicks,
                            popup_click_rate,
                            daily_cost AS spending,
                            campaign_status
                        FROM
                        (
                            SELECT *,
                            (
                                SELECT COUNT(DISTINCT user_id)
                                FROM {$tablePrefix}user_signin
                                WHERE location_id = {$this->quote($current_mall)}
                                AND DATE(created_at) = comp_date
                            ) AS unique_users,
                            (
                                SELECT COUNT(campaign_page_view_id) AS value
                                FROM {$tablePrefix}campaign_page_views
                                WHERE campaign_id = {$this->quote($campaign_id)}
                                AND location_id = {$this->quote($current_mall)}
                                AND DATE(created_at) = comp_date
                            ) AS campaign_pages_views,
                            (
                                SELECT COUNT(campaign_popup_view_id) AS value
                                FROM {$tablePrefix}campaign_popup_views
                                WHERE campaign_id = {$this->quote($campaign_id)}
                                AND location_id = {$this->quote($current_mall)}
                                AND DATE(created_at) = comp_date
                            ) AS popup_views,
                            (
                                SELECT COUNT(campaign_click_id) AS value
                                FROM {$tablePrefix}campaign_clicks
                                WHERE campaign_id = {$this->quote($campaign_id)}
                                AND location_id = {$this->quote($current_mall)}
                                AND DATE(created_at) = comp_date
                            ) AS popup_clicks,
                            (
                                SELECT IFNULL(ROUND((campaign_pages_views / unique_users) * 100, 2), 0)
                            ) AS campaign_pages_view_rate,
                            (
                                SELECT IFNULL (ROUND((popup_views / unique_users) * 100, 2), 0)
                            ) AS popup_view_rate,
                            (
                                SELECT IFNULL (ROUND((popup_clicks / popup_views) * 100, 2), 0)
                            ) AS popup_click_rate

                            FROM
                                ( SELECT comp_date, campaign_number_tenant, daily_cost, campaign_status, mall_id  FROM tmp_campaign_cost_detail AS tccd ) AS x

                                -- JOIN to get tenant list per date
                                LEFT JOIN
                                (
                                    SELECT
                                        och.campaign_id,
                                        och.campaign_history_action_id,
                                        och.campaign_external_value,
                                        om.name as tenant_name,
                                        DATE_FORMAT(och.created_at, '%Y-%m-%d') AS history_created_date
                                    FROM
                                        {$tablePrefix}campaign_histories och
                                    LEFT JOIN {$tablePrefix}merchants om
                                    ON om.merchant_id = och.campaign_external_value
                                    WHERE
                                        och.campaign_history_action_id IN ({$this->quote($idAddTenant)}, {$this->quote($idDeleteTenant)})
                                        AND och.campaign_type = {$this->quote($campaign_type)}
                                        AND och.campaign_id = {$this->quote($campaign_id)}
                                    ORDER BY och.created_at DESC
                                ) yy
                                ON DATE_FORMAT(CONVERT_TZ(history_created_date, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') <= comp_date

                                group by comp_date, yy.campaign_id, campaign_external_value
                            ) as c

                            LEFT JOIN {$tablePrefix}merchants om_mall
                            ON mall_id = om_mall.merchant_id

                            WHERE (
                                case when campaign_history_action_id = {$this->quote($idDeleteTenant)}
                                and DATE_FORMAT(CONVERT_TZ(history_created_date, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') < comp_date
                                then campaign_history_action_id != {$this->quote($idDeleteTenant)} else true end
                            )
                            ORDER BY comp_date desc
                        ) AS tbl
                    ";

            $campaign = DB::table(DB::raw($sql))->where("campaign_status", '=', 'activate');

            // Filter by campaign name
            OrbitInput::get('mall_name', function($campaign_name) use ($campaign) {
                $campaign->where('mall_name', 'like', "%$campaign_name%");
            });

            // Filter by tenant name
            OrbitInput::get('tenant_name', function($tenant_name) use ($campaign) {
                $campaign->where('tenant_name', 'like', "%$tenant_name%");
            });

            if ($start_date != '' && $end_date != ''){
                $campaign->whereRaw("campaign_date between ? and ?", [$start_date, $end_date]);
            }

            // Grouping after filter
            $campaign->groupBy('campaign_date');

            // Clone the query builder which still does not include the take,
            $_campaign = clone $campaign;

            // Need to sub select after group by
            $_campaign_sql = $_campaign->toSql();

            foreach($campaign->getBindings() as $binding)
            {
              $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
              $_campaign_sql = preg_replace('/\?/', $value, $_campaign_sql, 1);
            }

            $_campaign = DB::table(DB::raw('(' . $_campaign_sql . ') as b'));

            $query_sum = array(
                'SUM(spending) AS spending',
                'SUM(campaign_pages_views) AS campaign_pages_views',
                'SUM(popup_views) AS popup_views',
                'SUM(popup_clicks) AS popup_clicks'
            );

            $total = $_campaign->selectRaw(implode(',', $query_sum))->get();

            // Get info total bottom page
            $totalPageViews = round(isset($total[0]->campaign_pages_views)?$total[0]->campaign_pages_views:0, 2);
            $totalPopupViews = round(isset($total[0]->popup_views)?$total[0]->popup_views:0, 2);
            $totalPopupClicks = round(isset($total[0]->popup_clicks)?$total[0]->popup_clicks:0, 2);
            $totalSpending = isset($total[0]->spending)?$total[0]->spending:0;

            $_campaign->select('campaign_date');

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
            OrbitInput::get('skip', function($_skip) use (&$skip, $campaign)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });

            // If request page from export (print/csv), showing without page limitation
            $export = OrbitInput::get('export');

            if (!isset($export)){
                $campaign->take($take);
                $campaign->skip($skip);
            }

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
                    'spending'                 => 'spending'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });


            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });

            // Get title campaign
            $campaignName = '';
            if ($campaign_type === 'news' or $campaign_type === 'promotion') {
                $getTitleCampaign = News::selectraw('news_name')
                    ->where('news_id', $campaign_id)
                    ->get();
                $campaignName = htmlentities($getTitleCampaign[0]->news_name);
            } elseif ($campaign_type === 'coupon') {
                $getTitleCampaign = Coupon::selectraw('promotion_name')
                    ->where('promotion_id', $campaign_id)
                    ->where('is_coupon', 'Y')
                    ->get();
                $campaignName = htmlentities($getTitleCampaign[0]->promotion_name);
            }

            $campaign->orderBy($sortBy, $sortMode);

            $totalCampaign = $_campaign->count();

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return [
                    'builder' => $campaign,
                    'count' => $totalCampaign,
                    'totalPageViews' => $totalPageViews,
                    'totalPopupViews' => $totalPopupViews,
                    'totalPopupClicks' => $totalPopupClicks,
                    'totalSpending' => $totalSpending,
                    'campaignName' => $campaignName,
                ];
            }

            $listOfCampaign = $campaign->get();

            $data = new stdclass();
            $data->total_records = $totalCampaign;
            $data->returned_records = count($listOfCampaign);
            $data->active_campaign_days = $totalCampaign;
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


    /**
     * GET - Get Tenant Per Campaign In Campaign Summary
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `campaign_id            (required) - Campaign id (news_id, promotion_id, coupon_id)
     * @param string   `campaign_type          (required) - news, promotion, coupon
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getTenantCampaignSummary()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $campaign_id = OrbitInput::get('campaign_id');
            $campaign_type = OrbitInput::get('campaign_type');
            $current_mall = OrbitInput::get('current_mall');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'campaign_id' => $campaign_id,
                    'campaign_type' => $campaign_type,
                    'current_mall' => $current_mall,
                ),
                array(
                    'campaign_id' => 'required',
                    'campaign_type' => 'required',
                    'current_mall' => 'required|orbit.empty.mall',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.campaignreportgeneral_sortby'),
                )
            );

            Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.after.validation', array($this, $validator));

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
            $timezone = $this->getTimezone($mall->merchant_id);

            // Change Now Date to Mall Time
            $now = Carbon::now($mall->timezone->timezone_name);
            $now = $now->toDateString();

            // Get now date with timezone
            $timezoneOffset = $this->getTimezoneOffset($timezone);

            // Get id add_tenant and delete_tenant for counting total tenant percampaign
            $campaignHistoryAction = DB::table('campaign_history_actions')
                            ->select('campaign_history_action_id','action_name')
                            ->where('action_name','add_tenant')
                            ->orWhere('action_name','delete_tenant')
                            ->get();

            $idAddTenant = '';
            $idDeleteTenant = '';
            foreach ($campaignHistoryAction as $key => $value) {
                if ($value->action_name === 'add_tenant') {
                    $idAddTenant = $value->campaign_history_action_id;
                } elseif ($value->action_name === 'delete_tenant') {
                    $idDeleteTenant = $value->campaign_history_action_id;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            // Get the end_date from campaign
            if ($campaign_type == 'news' || $campaign_type == 'promotion') {
                $sqlEndDate = News::select('end_date')->where('news_id', $campaign_id)->get();
            } elseif ($campaign_type == 'coupon') {
                $sqlEndDate = Coupon::select('end_date')->where('promotion_id', $campaign_id)->get();
            }

            $endDate = $sqlEndDate[0]->end_date;

            // Builder object
            $linkToTenants = DB::select(DB::raw("
                    SELECT name FROM
                        (SELECT * FROM
                            (
                                SELECT
                                    och.campaign_id,
                                    och.campaign_history_action_id,
                                    och.campaign_external_value,
                                    om.name,
                                    DATE_FORMAT(och.created_at, '%Y-%m-%d %H:00:00') AS history_created_date
                                FROM
                                    {$tablePrefix}campaign_histories och
                                LEFT JOIN
                                    {$tablePrefix}campaign_history_actions ocha
                                ON och.campaign_history_action_id = ocha.campaign_history_action_id
                                LEFT JOIN
                                    {$tablePrefix}merchants om
                                ON om.merchant_id = och.campaign_external_value
                                WHERE
                                    och.campaign_history_action_id IN ({$this->quote($idAddTenant)}, {$this->quote($idDeleteTenant)})
                                    AND och.campaign_type = {$this->quote($campaign_type)}
                                    AND och.campaign_id = {$this->quote($campaign_id)}
                                    AND DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') <= {$this->quote($now)}
                                ORDER BY och.created_at DESC
                            ) as A
                        group by campaign_external_value) as B
                    WHERE (
                        case when campaign_history_action_id = {$this->quote($idDeleteTenant)}
                        and DATE_FORMAT(CONVERT_TZ(history_created_date, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') < IF( DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d') < DATE_FORMAT({$this->quote($endDate)}, '%Y-%m-%d'), DATE_FORMAT({$this->quote($now)}, '%Y-%m-%d'), DATE_FORMAT({$this->quote($endDate)}, '%Y-%m-%d') )
                        then campaign_history_action_id != {$this->quote($idDeleteTenant)} else true end
                    )
                     ORDER by name asc
                "));

            $this->response->data = $linkToTenants;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.query.error', array($this, $e));

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
            Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = 'null';
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.campaignreportdetail.gettenantcampaigndetail.before.render', array($this, &$output));

        return $output;
    }


    /**
     * GET - Get Tenant Per Campaign In Campaign Detail
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `campaign_id`            (required) - Campaign id (news_id, promotion_id, coupon_id)
     * @param string   `campaign_type`          (required) - news, promotion, coupon
     * @param string   `campaign_date`          (required) - date campaign
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getTenantCampaignDetail()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $campaign_id = OrbitInput::get('campaign_id');
            $campaign_type = OrbitInput::get('campaign_type');
            $campaign_date = OrbitInput::get('campaign_date');
            $current_mall = OrbitInput::get('current_mall');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'current_mall' => $current_mall,
                    'campaign_id' => $campaign_id,
                    'campaign_type' => $campaign_type,
                    'campaign_date' => $campaign_date,
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'campaign_id' => 'required',
                    'campaign_type' => 'required',
                    'campaign_date' => 'required',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.campaignreportgeneral_sortby'),
                )
            );

            Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.after.validation', array($this, $validator));

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
            $timezone = $this->getTimezone($mall->merchant_id);

            // Get now date with timezone
            $timezoneOffset = $this->getTimezoneOffset($timezone);

            // Get id add_tenant and delete_tenant for counting total tenant percampaign
            $campaignHistoryAction = DB::table('campaign_history_actions')
                            ->select('campaign_history_action_id','action_name')
                            ->where('action_name','add_tenant')
                            ->orWhere('action_name','delete_tenant')
                            ->get();

            $idAddTenant = '';
            $idDeleteTenant = '';
            foreach ($campaignHistoryAction as $key => $value) {
                if ($value->action_name === 'add_tenant') {
                    $idAddTenant = $value->campaign_history_action_id;
                } elseif ($value->action_name === 'delete_tenant') {
                    $idDeleteTenant = $value->campaign_history_action_id;
                }
            }

            // Builder object
            $tablePrefix = DB::getTablePrefix();

            $linkToTenants = DB::select(DB::raw("
                    SELECT name FROM
                        (SELECT * FROM
                            (
                                SELECT
                                    och.campaign_id,
                                    och.campaign_history_action_id,
                                    ocha.action_name,
                                    och.campaign_external_value,
                                    om.name,
                                    DATE_FORMAT(och.created_at, '%Y-%m-%d %H:00:00') AS history_created_date
                                FROM
                                    {$tablePrefix}campaign_histories och
                                LEFT JOIN
                                    {$tablePrefix}campaign_history_actions ocha
                                ON och.campaign_history_action_id = ocha.campaign_history_action_id
                                LEFT JOIN
                                    {$tablePrefix}merchants om
                                ON om.merchant_id = och.campaign_external_value
                                WHERE
                                    och.campaign_history_action_id IN ({$this->quote($idAddTenant)}, {$this->quote($idDeleteTenant)})
                                    AND och.campaign_type = {$this->quote($campaign_type)}
                                    AND och.campaign_id = {$this->quote($campaign_id)}
                                    AND DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') <= {$this->quote($campaign_date)}
                                ORDER BY och.created_at DESC
                            ) as A
                        group by campaign_external_value) as B
                    WHERE (
                        case when campaign_history_action_id = {$this->quote($idDeleteTenant)}
                        and DATE_FORMAT(CONVERT_TZ(history_created_date, '+00:00', {$this->quote($timezoneOffset)}), '%Y-%m-%d') < {$this->quote($campaign_date)}
                        then campaign_history_action_id != {$this->quote($idDeleteTenant)} else true end
                    )
                     ORDER by name asc
                "));


            $this->response->data = $linkToTenants;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.query.error', array($this, $e));

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
            Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = 'null';
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.campaignreportdetail.gettenantcampaignsummary.before.render', array($this, &$output));

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
                if ( $demograhicFemale[0]->$ageRange !== null ) {
                    if ($demograhicFemale[0]->total !== 0){
                        $percent = ($demograhicFemale[0]->$ageRange / $demograhicFemale[0]->total) * 100;
                        $total = $demograhicFemale[0]->$ageRange;
                    }
                }

                $female[$key]['age_range'] = $ageRange;
                $female[$key]['total'] = $total;
                $female[$key]['percent'] = round($percent, 2) . ' %';
            }

            foreach (Config::get('orbit.age_ranges') as $key => $ageRange) {
                if ( $demograhicMale[0]->$ageRange !== null ) {
                    if ($demograhicMale[0]->total !== 0){
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

    public function getSpending()
    {
        // Campaign ID
        $id = OrbitInput::get('campaign_id');

        // News, promotion or coupon
        $type = OrbitInput::get('campaign_type');

        // Get mall's timezone
        $mallId = OrbitInput::get('current_mall');
        $timezone = Mall::find($mallId)->timezone->timezone_name;

        $method = \Input::get('m', 2);
        return $this->{'getSpending'.$method}($id, $type, $timezone);
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
    private function getSpending1($id, $type, $mallTimezone)
    {
        $timezone = $mallTimezone;

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

        $activationActionId = CampaignHistoryAction::whereActionName('activate')->first()->campaign_history_action_id;
        $deactivationActionId = CampaignHistoryAction::whereActionName('deactivate')->first()->campaign_history_action_id;

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

                    // Set cost as 0 when there's only the deactivation today
                    if (!$activationRow) {
                        $cost = 0;
                    }
                }

                // If there is a deactivation at last row, it will be affected tomorrow
                if ($deactivationRowId > $activationRowId) {
                    $previousDayCost = 0;
                }

                // When the change is only the tenant count change
                if (!($activationRow && $deactivationRow)) {
                    $cost = $previousDayCost = 0;
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
     * getSpending2
     *
     * Implements Thomas' proc.
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     */
    private function getSpending2($id, $type, $mallTimezone)
    {
        $requestBeginDateTime = OrbitInput::get('start_date');

        // Begin date in mall's timezone
        $requestBeginDate = Carbon::createFromFormat('Y-m-d H:i:s', $requestBeginDateTime)->setTimezone($mallTimezone)->toDateString();

        $requestEndDateTime = OrbitInput::get('end_date');

        // End date in mall's timezone
        $requestEndDate = Carbon::createFromFormat('Y-m-d H:i:s', $requestEndDateTime)->setTimezone($mallTimezone)->toDateString();

        $hoursDiff = $this->getTimezoneHoursDiff($mallTimezone);

        $procCallStatement = 'CALL prc_campaign_detailed_cost(?, ?, ?, ?, ?)';

        // DB::select below will need to use the same connection (write) as DB::statement
        // Otherwise, it won't get the temp table
        \DB::beginTransaction();

        // It should return true
        $procCall = \DB::statement($procCallStatement, [
            $id, $type, $requestBeginDate, $requestEndDate, $hoursDiff
        ]);

        if ($procCall === false) {
            // What to do here?
        }

        // Now let's retrieve the data from the temporary table
        $procResults = DB::select('select * from tmp_campaign_cost_detail');

        foreach ($procResults as $row) {
            $costs[$row->comp_date] = $row->daily_cost;
        }

        $carbonLoop = Carbon::createFromFormat('Y-m-d', $requestBeginDate);
        while ($carbonLoop->toDateString() <= $requestEndDate) {
            $loopDate = $carbonLoop->toDateString();
            $cost = isset($costs[$loopDate]) ? $costs[$loopDate] : 0;

            // Add to output array
            $outputs[] = [
                'date' => $carbonLoop->toDateString(),
                'cost' => (int) $cost, // Format cost as integer
            ];

            // Increment day by 1
            $carbonLoop->addDay();
        }

        // Debug the proc call
        if (Config::get('app.debug')) {
            $procCall = sprintf(str_replace('?', "'%s'", $procCallStatement), $id, $type, $requestBeginDate, $requestEndDate, $hoursDiff);
            Log::info('Proc call: '.$procCall);
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
                                select
                                    date_format(convert_tz(created_at, '+00:00', ?), '%Y-%m-%d') as date,
                                    count(campaign_page_view_id) as value
                                from
                                    {$tablePrefix}campaign_page_views
                                where
                                    campaign_id = ? and
                                    location_id = ? and
                                    created_at between ? and ?
                                group by 1
                                order by 1
            ", array($timezoneOffset, $campaign_id, $current_mall, $start_date, $end_date));


            $pop_up_view = DB::select("
                                select
                                    date_format(convert_tz(created_at, '+00:00', ?), '%Y-%m-%d') as date,
                                    count(campaign_popup_view_id) as value
                                from
                                    {$tablePrefix}campaign_popup_views
                                where
                                    campaign_id = ? and
                                    location_id = ? and
                                    created_at between ? and ?
                                group by 1
                                order by 1
            ", array($timezoneOffset, $campaign_id, $current_mall, $start_date, $end_date));

            $pop_up_click = DB::select("
                                select
                                    date_format(convert_tz(created_at, '+00:00', ?), '%Y-%m-%d') as date,
                                    count(campaign_click_id) as value
                                from
                                    {$tablePrefix}campaign_clicks
                                where
                                    campaign_id = ? and
                                    location_id = ? and
                                    created_at between ? and ?
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
