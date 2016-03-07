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
    
    private function getData($mallId, $startDate, $endDate, $timeDimensionType)
    {
        $tablePrefix = DB::getTablePrefix();

        $mallTimezone = 'Asia/Jakarta';
        $timezoneOffset = $this->quote('+07:00');

        $mallStartDate = Carbon::createFromFormat('Y-m-d H:i:s', $startDate)->timezone($mallTimezone);
        $mallEndDate = Carbon::createFromFormat('Y-m-d H:i:s', $endDate)->timezone($mallTimezone);

        $numberDays = $mallStartDate->diffInDays($mallEndDate) + 1;

        $dayOfWeek = "
            (
                select sequence_number,
                    case when sequence_number = 0 then 'Sunday'
                    when sequence_number = 1 then 'Monday'
                    when sequence_number = 2 then 'Tuesday'
                    when sequence_number = 3 then 'Wednesday'
                    when sequence_number = 4 then 'Thursday'
                    when sequence_number = 5 then 'Friday'
                    when sequence_number = 6 then 'Saturday'
                    else null end as day_of_week_name
                from
                (select 0 as sequence_number union
                select * from {$tablePrefix}sequence where sequence_number <= 6
                ) a
            ) day_of_week
        ";

        $hourOfDay = "
            (
                select 0 as sequence_number union
                select * from {$tablePrefix}sequence where sequence_number <= 23
            ) hour_of_day
        ";

        $reportMonth = "
            (
                select * from {$tablePrefix}sequence where sequence_number <= 12
            ) report_month
        ";

        $reportDate = "
            (
                select date_format('{$startDate}' + interval sequence_number - 1 day, '%Y-%m-%d') as sequence_date
                from {$tablePrefix}sequence s
                where s.sequence_number <= {$numberDays}
            ) report_date
        ";

        switch ($timeDimensionType) {
            case 'day_of_week':
                $sequenceTable = $dayOfWeek;
                $selectTimeDimensionColumns = 'day_of_week.sequence_number as report_day_of_week, day_of_week.day_of_week_name as report_day_of_week_name';
                $signUpGroupBy = 'sign_up_day_of_week';
                break;

            case 'hour_of_day':
                $sequenceTable = $hourOfDay;
                $selectTimeDimensionColumns = "hour_of_day.sequence_number as report_hour_of_day, concat(date_format(curdate() + interval hour_of_day.sequence_number hour, '%H'), ':00 - ', date_format(curdate() + interval hour_of_day.sequence_number + 1 hour, '%H'), ':00') as report_hour_of_day_name";
                $signUpGroupBy = 'sign_up_hour_of_day';
                break;

            case 'report_month':
                $sequenceTable = $reportMonth;
                $selectTimeDimensionColumns = "report_month.sequence_number as report_month, monthname(str_to_date(report_month.sequence_number, '%m')) as report_month_name";
                $signUpGroupBy = 'sign_up_month';
                break;

            case 'report_date':
            default:
                $sequenceTable = $reportDate;
                $selectTimeDimensionColumns = 'report_date.sequence_date as report_date';
                $signUpGroupBy = 'sign_up_date';
        };

        $signUpReport = "
            (
            select

            date_format(convert_tz(ua2.created_at, '+00:00', {$timezoneOffset}), '%Y-%m-%d') as sign_up_date,
            cast(date_format(convert_tz(ua2.created_at, '+00:00', {$timezoneOffset}), '%k') as signed) as sign_up_hour_of_day,
            cast(date_format(convert_tz(ua2.created_at, '+00:00', {$timezoneOffset}), '%w') as signed) as sign_up_day_of_week,
            cast(date_format(convert_tz(ua2.created_at, '+00:00', {$timezoneOffset}), '%c') as signed) as sign_up_month,

            count(ua2.user_acquisition_id) as sign_up,

            sum(ua2.signup_via = 'facebook') as sign_up_type_facebook,
            sum(ua2.signup_via = 'google') as sign_up_type_google,
            sum(ua2.signup_via = 'form') as sign_up_type_form,
            sum(ua2.signup_via not in ('facebook', 'google', 'form')) as sign_up_type_unknown,

            sum(if(ua2.gender = 'm', 1, 0)) as sign_up_gender_male,
            sum(if(ua2.gender = 'f', 1, 0)) as sign_up_gender_female,
            sum(if(ua2.gender not in ('m', 'f') || ua2.gender is null, 1, 0)) as sign_up_gender_unknown,

            sum(if(ua2.age >= 0 and ua2.age <= 14, 1, 0)) as sign_up_age_0_to_14,
            sum(if(ua2.age >= 15 and ua2.age <= 24, 1, 0)) as sign_up_age_15_to_24,
            sum(if(ua2.age >= 25 and ua2.age <= 34, 1, 0)) as sign_up_age_25_to_34,
            sum(if(ua2.age >= 35 and ua2.age <= 44, 1, 0)) as sign_up_age_35_to_44,
            sum(if(ua2.age >= 45 and ua2.age <= 54, 1, 0)) as sign_up_age_45_to_54,
            sum(if(ua2.age >= 55 and ua2.age <= 64, 1, 0)) as sign_up_age_55_to_64,
            sum(if(ua2.age >= 65, 1, 0)) as sign_up_age_65_plus,
            sum(if(ua2.age is null, 1, 0)) as sign_up_age_unknown

            from
                (
                select ua.user_acquisition_id, ua.acquirer_id, ua.signup_via, ua.created_at, ud.gender, timestampdiff(year, ud.birthdate, date_format(convert_tz(utc_timestamp(), '+00:00', {$timezoneOffset}), '%Y-%m-%d')) as age
                from {$tablePrefix}user_acquisitions ua
                inner join {$tablePrefix}users u on ua.user_id = u.user_id
                inner join {$tablePrefix}user_details ud on u.user_id = ud.user_id
                where u.status != 'deleted'
                and ua.acquirer_id in ('{$mallId}')
                and ua.created_at between '{$startDate}' and '{$endDate}'
                ) ua2
            group by {$signUpGroupBy}
            ) as report_sign_up
        ";

        $selectSignUpColumns = "
            if(sign_up is null, 0, sign_up) as sign_up,

            if(sign_up_age_0_to_14 is null, 0, sign_up_age_0_to_14) as sign_up_age_0_to_14,
            if(sign_up_age_15_to_24 is null, 0, sign_up_age_15_to_24) as sign_up_age_15_to_24,
            if(sign_up_age_25_to_34 is null, 0, sign_up_age_25_to_34) as sign_up_age_25_to_34,
            if(sign_up_age_35_to_44 is null, 0, sign_up_age_35_to_44) as sign_up_age_35_to_44,
            if(sign_up_age_45_to_54 is null, 0, sign_up_age_45_to_54) as sign_up_age_45_to_54,
            if(sign_up_age_55_to_64 is null, 0, sign_up_age_55_to_64) as sign_up_age_55_to_64,
            if(sign_up_age_65_plus is null, 0, sign_up_age_65_plus) as sign_up_age_65_plus,
            if(sign_up_age_unknown is null, 0, sign_up_age_unknown) as sign_up_age_unknown,
            if(sign_up_age_0_to_14 = 0 || sign_up_age_0_to_14 is null, 0, trim(round((sign_up_age_0_to_14 / sign_up) * 100, 2))+0) as sign_up_age_0_to_14_percentage,
            if(sign_up_age_15_to_24 = 0 || sign_up_age_15_to_24 is null, 0, trim(round((sign_up_age_15_to_24 / sign_up) * 100, 2))+0) as sign_up_age_15_to_24_percentage,
            if(sign_up_age_25_to_34 = 0 || sign_up_age_25_to_34 is null, 0, trim(round((sign_up_age_25_to_34 / sign_up) * 100, 2))+0) as sign_up_age_25_to_34_percentage,
            if(sign_up_age_35_to_44 = 0 || sign_up_age_35_to_44 is null, 0, trim(round((sign_up_age_35_to_44 / sign_up) * 100, 2))+0) as sign_up_age_35_to_44_percentage,
            if(sign_up_age_45_to_54 = 0 || sign_up_age_45_to_54 is null, 0, trim(round((sign_up_age_45_to_54 / sign_up) * 100, 2))+0) as sign_up_age_45_to_54_percentage,
            if(sign_up_age_55_to_64 = 0 || sign_up_age_55_to_64 is null, 0, trim(round((sign_up_age_55_to_64 / sign_up) * 100, 2))+0) as sign_up_age_55_to_64_percentage,
            if(sign_up_age_65_plus = 0 || sign_up_age_65_plus is null, 0, trim(round((sign_up_age_65_plus / sign_up) * 100, 2))+0) as sign_up_age_65_plus_percentage,
            if(sign_up_age_unknown = 0 || sign_up_age_unknown is null, 0, trim(round((sign_up_age_unknown / sign_up) * 100, 2))+0) as sign_up_age_unknown_percentage,

            if(sign_up_type_facebook is null, 0, sign_up_type_facebook) as sign_up_type_facebook,
            if(sign_up_type_google is null, 0, sign_up_type_google) as sign_up_type_google,
            if(sign_up_type_form is null, 0, sign_up_type_form) as sign_up_type_form,
            if(sign_up_type_unknown is null, 0, sign_up_type_unknown) as sign_up_type_unknown,
            if(sign_up_type_facebook = 0 || sign_up_type_facebook is null, 0, trim(round((sign_up_type_facebook / sign_up) * 100, 2))+0) as sign_up_type_facebook_percentage,
            if(sign_up_type_google = 0 || sign_up_type_google is null, 0, trim(round((sign_up_type_google / sign_up) * 100, 2))+0) as sign_up_type_google_percentage,
            if(sign_up_type_form = 0 || sign_up_type_form is null, 0, trim(round((sign_up_type_form / sign_up) * 100, 2))+0) as sign_up_type_form_percentage,
            if(sign_up_type_unknown = 0 || sign_up_type_unknown is null, 0, trim(round((sign_up_type_unknown / sign_up) * 100, 2))+0) as sign_up_type_unknown_percentage,

            if(sign_up_gender_male is null, 0, sign_up_gender_male) as sign_up_gender_male,
            if(sign_up_gender_female is null, 0, sign_up_gender_female) as sign_up_gender_female,
            if(sign_up_gender_unknown is null, 0, sign_up_gender_unknown) as sign_up_gender_unknown,
            if(sign_up_gender_male = 0 || sign_up_gender_male is null, 0, trim(round((sign_up_gender_male / sign_up) * 100, 2))+0) as sign_up_gender_male_percentage,
            if(sign_up_gender_female = 0 || sign_up_gender_female is null, 0, trim(round((sign_up_gender_female / sign_up) * 100, 2))+0) as sign_up_gender_female_percentage,
            if(sign_up_gender_unknown = 0 || sign_up_gender_unknown is null, 0, trim(round((sign_up_gender_unknown / sign_up) * 100, 2))+0) as sign_up_gender_unknown_percentage
        ";

        $selectSignInColumns = "1";

        $records = DB::table(DB::raw($sequenceTable))
            ->selectRaw($selectTimeDimensionColumns . "," . $selectSignUpColumns . "," . $selectSignInColumns);

        switch ($timeDimensionType) {
            case 'day_of_week':
                $records->leftJoin(DB::raw($signUpReport), DB::raw('report_sign_up.sign_up_day_of_week'), '=', DB::raw('day_of_week.sequence_number'));
                break;

            case 'hour_of_day':
                $records->leftJoin(DB::raw($signUpReport), DB::raw('report_sign_up.sign_up_hour_of_day'), '=', DB::raw('hour_of_day.sequence_number'));
                break;

            case 'report_month':
                $records->leftJoin(DB::raw($signUpReport), DB::raw('report_sign_up.sign_up_month'), '=', DB::raw('report_month.sequence_number'));
                break;

            case 'report_date':
            default:
                $records->leftJoin(DB::raw($signUpReport), DB::raw('report_sign_up.sign_up_date'), '=', DB::raw('report_date.sequence_date'));
        }

        return $records->get();
    }

    private function getTitle($code)
    {
        switch ($code) {
            case 'report_date':
                $title = 'Date';
                break;
            case 'sign_up':
                $title = 'Sign Up';
                break;
        }

        return $title;
    }

    private function getTotalTitle($code)
    {
        switch ($code) {
            case 'sign_up':
                $title = 'Sign Up';
                break;
        }

        return $title;
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
    public function getUserReportX()
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

    /**
     * Get User Report
     * 
     * @author Tian <tian@dominopos.com>
     * @author Qosdil A. <qosdil@dominopos.com>
     */
    public function getUserReport()
    {
        $mallId = OrbitInput::get('current_mall');
        $startDate = OrbitInput::get('start_date');
        $endDate = OrbitInput::get('end_date');
        $timeDimensionType = OrbitInput::get('time_dimension_type');

        $data = new stdClass();
        foreach ($this->getData($mallId, $startDate, $endDate, $timeDimensionType) as $row) {
            $records[] = [
                'date' => Carbon::createFromFormat('Y-m-d', $row->report_date)->format('j M Y'),
                'sign_up' => $row->sign_up,
                'sign_up_by_type_facebook' => (int) $row->sign_up_type_facebook,
                'sign_up_by_type_google' => (int) $row->sign_up_type_google,
                'sign_up_by_type_form' => (int) $row->sign_up_type_form,
                'sign_in' => 0,
                'unique_sign_in' => 0,
                'returning' => 0,
                'status_active' => 0,
                'status_pending' => 0,
            ];
        }

        $data->columns = [
            'date' => [
                'title' => 'Date',
                'sort_key' => 'date',
            ],
            'sign_up' => [
                'title' => 'Sign Up',
                'sort_key' => 'sign_up',
                'total_title' => 'Sign Up',
                'total' => 0,
            ],
            'sign_up_by_type' => [
                'title' => 'Sign Up by Type',
                'sub_columns' => [
                    'sign_up_by_type_facebook' => [
                        'title' => 'Facebook',
                        'sort_key' => 'sign_up_by_type_facebook',
                        'total_title' => 'Sign Up via Facebook',
                        'total' => 0,
                    ],
                    'sign_up_by_type_google' => [
                        'title' => 'Google+',
                        'sort_key' => 'sign_up_by_type_google',
                        'total_title' => 'Sign Up via Google+',
                        'total' => 0,
                    ],
                    'sign_up_by_type_form' => [
                        'title' => 'Form',
                        'sort_key' => 'sign_up_by_type_form',
                        'total_title' => 'Sign Up via Form',
                        'total' => 0,
                    ],
                ],
            ],
            'sign_in' => [
                'title' => 'Sign In',
                'sort_key' => 'sign_in',
                'total_title' => 'Sign In',
                'total' => 0,
            ],
            'unique_sign_in' => [
                'title' => 'Unique Sign In',
                'sort_key' => 'unique_sign_in',
                'total_title' => 'Unique Sign In',
                'total' => 0,
            ],
            'returning' => [
                'title' => 'Returning',
                'sort_key' => 'returning',
                'total_title' => 'Returning',
                'total' => 0,
            ],
            'status' => [
                'title' => 'Status',
                'sub_columns' => [
                    'status_active' => [
                        'title' => 'Active',
                        'sort_key' => 'status_active',
                        'total_title' => 'Active Status',
                        'total' => 0,
                    ],
                    'status_pending' => [
                        'title' => 'Pending',
                        'sort_key' => 'status_pending',
                        'total_title' => 'Pending Status',
                        'total' => 0,
                    ],
                ],
            ],
        ];

        $data->records = $records;

        $this->response->data = $data;
        return $this->render(200);
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
