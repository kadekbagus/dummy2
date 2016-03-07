<?php
/**
 * An API controller for managing User report.
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

class UserReportAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'mall customer service'];

    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    private function generateCountRandom()
    {
        return rand(201, 999);
    }

    private function generateTotalRandom()
    {
        return rand(10001, 99999);
    }

    /**
     * A temporary method to output dummy data with the accepted structure
     * so that frontend guys can work on their part
     * without waiting for the real data.
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     */
    public function getDummyUserReport()
    {
        $data = new stdClass();
        $data->columns = [
            'date' => [
                'title' => 'Date',
                'sort_key' => 'date',
            ],
            'sign_up' => [
                'title' => 'Sign Up',
                'sort_key' => 'sign_up',
                'total_title' => 'Sign Up',
                'total' => $this->generateTotalRandom(),
            ],
            'sign_up_by_type' => [
                'title' => 'Sign Up by Type',
                'sub_columns' => [
                    'sign_up_by_type_facebook' => [
                        'title' => 'Facebook',
                        'sort_key' => 'sign_up_by_type_facebook',
                        'total_title' => 'Sign Up via Facebook',
                        'total' => $this->generateTotalRandom(),
                    ],
                    'sign_up_by_type_google' => [
                        'title' => 'Google+',
                        'sort_key' => 'sign_up_by_type_google',
                        'total_title' => 'Sign Up via Google+',
                        'total' => $this->generateTotalRandom(),
                    ],
                    'sign_up_by_type_form' => [
                        'title' => 'Form',
                        'sort_key' => 'sign_up_by_type_form',
                        'total_title' => 'Sign Up via Form',
                        'total' => $this->generateTotalRandom(),
                    ],
                ],
            ],
            'sign_in' => [
                'title' => 'Sign In',
                'sort_key' => 'sign_in',
                'total_title' => 'Sign In',
                'total' => $this->generateTotalRandom(),
            ],
            'unique_sign_in' => [
                'title' => 'Unique Sign In',
                'sort_key' => 'unique_sign_in',
                'total_title' => 'Unique Sign In',
                'total' => $this->generateTotalRandom(),
            ],
            'returning' => [
                'title' => 'Returning',
                'sort_key' => 'returning',
                'total_title' => 'Returning',
                'total' => $this->generateTotalRandom(),
            ],
            'status' => [
                'title' => 'Status',
                'sub_columns' => [
                    'status_active' => [
                        'title' => 'Active',
                        'sort_key' => 'status_active',
                        'total_title' => 'Active Status',
                        'total' => $this->generateTotalRandom(),
                    ],
                    'status_pending' => [
                        'title' => 'Pending',
                        'sort_key' => 'status_pending',
                        'total_title' => 'Pending Status',
                        'total' => $this->generateTotalRandom(),
                    ],
                ],
            ],
        ];

        for ($date = 22; $date > 15; $date--) {
            $data->records[] = [
                'date' => $date.' Feb 2016',
                'sign_up' => $this->generateCountRandom(),
                'sign_up_by_type_facebook' => $this->generateCountRandom(),
                'sign_up_by_type_google' => $this->generateCountRandom(),
                'sign_up_by_type_form' => $this->generateCountRandom(),
                'sign_in' => $this->generateCountRandom(),
                'unique_sign_in' => $this->generateCountRandom(),
                'returning' => $this->generateCountRandom(),
                'status_active' => $this->generateCountRandom(),
                'status_pending' => $this->generateCountRandom(),
            ];
        }

        $this->response->data = $data;
        return $this->render(200);
    }

    /**
     * GET - User Report List
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`        (optional) - Column order by. Valid value: .
     * @param string   `sortmode`      (optional) - ASC or DESC
     * @param integer  `take`          (optional) - Limit
     * @param integer  `skip`          (optional) - Limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserReport()
    {
        return $this->getDummyUserReport();

        try {
            $httpCode = 200;

            Event::fire('orbit.userreport.getuserreport.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.userreport.getuserreport.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.userreport.getuserreport.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.userreport.getuserreport.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // validate user mall id for current_mall
            $mallId = OrbitInput::get('current_mall');
            $listOfMallIds = $user->getUserMallIds($mallId);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $mallId = $listOfMallIds[0];
            }

            $sort_by = OrbitInput::get('sortby');

            // Filter by date
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'current_mall' => $mallId,
                    'sort_by'      => $sort_by,
                    'start_date'   => $start_date,
                    'end_date'     => $end_date,
                ),
                array(
                    'current_mall' => 'orbit.empty.mall',
                    'sort_by'      => 'in:',
                    'start_date'   => 'required|date_format:Y-m-d H:i:s',
                    'end_date'     => 'required|date_format:Y-m-d H:i:s',
                ),
                array(
                    'in'           => Lang::get('validation.orbit.empty.userreport_sortby'),
                )
            );

            Event::fire('orbit.userreport.getuserreport.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.userreport.getuserreport.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.user_report.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.user_report.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $mall = App::make('orbit.empty.mall');
            $timezone = $mall->timezone->timezone_name;
            $now = Carbon::now($timezone);
            $now_ymd = $now->toDateString();

            // Get timezone offset, ex: '+07:00'
            $timezoneOffset = $this->getTimezoneOffset($timezone);

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
            $news = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, news_name AS campaign_name, {$tablePrefix}news.object_type AS campaign_type,
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
                    and location_id = {$this->quote($mallId)}
                ) as page_views,
                (
                    select count(campaign_popup_view_id) as value
                    from {$tablePrefix}campaign_popup_views
                    where campaign_id = {$tablePrefix}news.news_id
                    and location_id = {$this->quote($mallId)}
                ) as popup_views,
                (
                    select count(campaign_click_id) as value
                    from {$tablePrefix}campaign_clicks
                    where campaign_id = {$tablePrefix}news.news_id
                    and location_id = {$this->quote($mallId)}
                ) as popup_clicks,
                {$tablePrefix}news.status"))
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

                        ->where('news.mall_id', '=', $mallId)
                        ->where('news.object_type', '=', 'news');

            $promotions = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, news_name AS campaign_name, {$tablePrefix}news.object_type AS campaign_type,
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
                    and location_id = {$this->quote($mallId)}
                ) as page_views,
                (
                    select count(campaign_popup_view_id) as value
                    from {$tablePrefix}campaign_popup_views
                    where campaign_id = {$tablePrefix}news.news_id
                    and location_id = {$this->quote($mallId)}
                ) as popup_views,
                (
                    select count(campaign_click_id) as value
                    from {$tablePrefix}campaign_clicks
                    where campaign_id = {$tablePrefix}news.news_id
                    and location_id = {$this->quote($mallId)}
                ) as popup_clicks,
                {$tablePrefix}news.status"))
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

                        ->where('news.mall_id', '=', $mallId)
                        ->where('news.object_type', '=', 'promotion');


            $coupons = DB::table('promotions')->selectraw(DB::raw("{$tablePrefix}promotions.promotion_id AS campaign_id, promotion_name AS campaign_name, IF(1=1,'coupon', '') AS campaign_type,
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
                    and location_id = {$this->quote($mallId)}
                ) as page_views,
                (
                    select count(campaign_popup_view_id) as value
                    from {$tablePrefix}campaign_popup_views
                    where campaign_id = {$tablePrefix}promotions.promotion_id
                    and location_id = {$this->quote($mallId)}
                ) as popup_views,
                (
                    select count(campaign_click_id) as value
                    from {$tablePrefix}campaign_clicks
                    where campaign_id = {$tablePrefix}promotions.promotion_id
                    and location_id = {$this->quote($mallId)}
                ) as popup_clicks,
                {$tablePrefix}promotions.status"))
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
                        ->where('promotions.merchant_id', '=', $mallId);

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

            // Filter by campaign status
            OrbitInput::get('status', function($status) use ($campaign) {
                $campaign->whereIn('status', (array)$status);
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
                'SUM(page_views) AS page_views',
                'SUM(popup_views) AS popup_views',
                'SUM(estimated_total) AS estimated_total',
                'SUM(spending) AS spending'
            );

            $total = $_campaign->selectRaw(implode(',', $query_sum))->get();

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

            if (!isset($export)){
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

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return [
                    'builder' => $campaign,
                    'count' => $_campaign->count(),
                    'totalPageViews' => $totalPageViews,
                    'totalPopUpViews' => $totalPopupViews,
                    'totalSpending' => $totalSpending,
                    'totalEstimatedCost' => $totalEstimated,
                ];
            }

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
            Event::fire('orbit.userreport.getuserreport.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.userreport.getuserreport.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.userreport.getuserreport.query.error', array($this, $e));

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
            Event::fire('orbit.userreport.getuserreport.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.userreport.getuserreport.before.render', array($this, &$output));

        return $output;
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
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
