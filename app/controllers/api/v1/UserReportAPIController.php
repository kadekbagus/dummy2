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


/**
 * User Report API Controller
 * 
 * @author Qosdil A. <qosdil@dominopos.com>
 * @author Tian <tian@dominopos.com>
 */
class UserReportAPIController extends ControllerAPI
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
     * @todo Remove this since no longer used by the main method.
     */
    private function getTotals($mallId, $mallTimezone, $startDate, $endDate)
    {
        $tablePrefix = DB::getTablePrefix();
        $timezoneOffset = $this->quote($this->getTimezoneHoursDiff($mallTimezone));

        $mallStartDate = Carbon::createFromFormat('Y-m-d H:i:s', $startDate)->timezone($mallTimezone);
        $mallEndDate = Carbon::createFromFormat('Y-m-d H:i:s', $endDate)->timezone($mallTimezone);

        $numberDays = $mallStartDate->diffInDays($mallEndDate) + 1;

        $sequenceTable = "
            (
                select 'grandtotal' as grandtotal
            ) grandtotal
        ";

        $signUpReport = "
            (
            select

            'grandtotal' as grandtotal,
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
            ) as report_sign_up
        ";

        $signInReport = "
            (
            select

            'grandtotal' as grandtotal,
            count(us2.user_signin_id) as sign_in,

            sum(us2.signin_via = 'facebook') as sign_in_type_facebook,
            sum(us2.signin_via = 'google') as sign_in_type_google,
            sum(us2.signin_via = 'form') as sign_in_type_form,
            sum(us2.signin_via not in ('facebook', 'google', 'form')) as sign_in_type_unknown,

            sum(if(us2.gender = 'm', 1, 0)) as sign_in_gender_male,
            sum(if(us2.gender = 'f', 1, 0)) as sign_in_gender_female,
            sum(if(us2.gender not in ('m', 'f') || us2.gender is null, 1, 0)) as sign_in_gender_unknown,

            sum(if(us2.age >= 0 and us2.age <= 14, 1, 0)) as sign_in_age_0_to_14,
            sum(if(us2.age >= 15 and us2.age <= 24, 1, 0)) as sign_in_age_15_to_24,
            sum(if(us2.age >= 25 and us2.age <= 34, 1, 0)) as sign_in_age_25_to_34,
            sum(if(us2.age >= 35 and us2.age <= 44, 1, 0)) as sign_in_age_35_to_44,
            sum(if(us2.age >= 45 and us2.age <= 54, 1, 0)) as sign_in_age_45_to_54,
            sum(if(us2.age >= 55 and us2.age <= 64, 1, 0)) as sign_in_age_55_to_64,
            sum(if(us2.age >= 65, 1, 0)) as sign_in_age_65_plus,
            sum(if(us2.age is null, 1, 0)) as sign_in_age_unknown

            from
                (
                select us.user_signin_id, us.location_id, us.user_id, us.signin_via, us.created_at, ud.gender, timestampdiff(year, ud.birthdate, date_format(convert_tz(utc_timestamp(), '+00:00', {$timezoneOffset}), '%Y-%m-%d')) as age
                from {$tablePrefix}user_signin us
                inner join {$tablePrefix}users u on us.user_id = u.user_id
                inner join {$tablePrefix}user_details ud on u.user_id = ud.user_id
                where u.status != 'deleted'
                and us.location_id in ('{$mallId}')
                and us.created_at between '{$startDate}' and '{$endDate}'
                ) us2
            ) report_sign_in
        ";

        $uniqueSignInReport = "
            (
            select

            'grandtotal' as grandtotal,
            count(us2.user_id) as unique_sign_in,

            sum(us2.signin_via = 'facebook') as unique_sign_in_type_facebook,
            sum(us2.signin_via = 'google') as unique_sign_in_type_google,
            sum(us2.signin_via = 'form') as unique_sign_in_type_form,
            sum(us2.signin_via not in ('facebook', 'google', 'form')) as unique_sign_in_type_unknown,

            sum(if(us2.gender = 'm', 1, 0)) as unique_sign_in_gender_male,
            sum(if(us2.gender = 'f', 1, 0)) as unique_sign_in_gender_female,
            sum(if(us2.gender not in ('m', 'f') || us2.gender is null, 1, 0)) as unique_sign_in_gender_unknown,

            sum(if(us2.age >= 0 and us2.age <= 14, 1, 0)) as unique_sign_in_age_0_to_14,
            sum(if(us2.age >= 15 and us2.age <= 24, 1, 0)) as unique_sign_in_age_15_to_24,
            sum(if(us2.age >= 25 and us2.age <= 34, 1, 0)) as unique_sign_in_age_25_to_34,
            sum(if(us2.age >= 35 and us2.age <= 44, 1, 0)) as unique_sign_in_age_35_to_44,
            sum(if(us2.age >= 45 and us2.age <= 54, 1, 0)) as unique_sign_in_age_45_to_54,
            sum(if(us2.age >= 55 and us2.age <= 64, 1, 0)) as unique_sign_in_age_55_to_64,
            sum(if(us2.age >= 65, 1, 0)) as unique_sign_in_age_65_plus,
            sum(if(us2.age is null, 1, 0)) as unique_sign_in_age_unknown,

            sum(if(us2.status = 'active', 1, 0)) as unique_sign_in_status_active,
            sum(if(us2.status = 'pending', 1, 0)) as unique_sign_in_status_pending,
            sum(if(us2.status not in ('active', 'pending') || us2.status is null, 1, 0)) as unique_sign_in_status_unknown

            from
                (
                select
                date_format(convert_tz(us.created_at, '+00:00', {$timezoneOffset}), '%Y-%m-%d') as unique_sign_in_date,
                cast(date_format(convert_tz(us.created_at, '+00:00', {$timezoneOffset}), '%k') as signed) as unique_sign_in_hour_of_day,
                cast(date_format(convert_tz(us.created_at, '+00:00', {$timezoneOffset}), '%w') as signed) as unique_sign_in_day_of_week,
                cast(date_format(convert_tz(us.created_at, '+00:00', {$timezoneOffset}), '%c') as signed) as unique_sign_in_month,
                us.user_signin_id, us.location_id, us.user_id, us.signin_via, us.created_at, ud.gender, timestampdiff(year, ud.birthdate, date_format(convert_tz(utc_timestamp(), '+00:00', {$timezoneOffset}), '%Y-%m-%d')) as age, u.status
                from {$tablePrefix}user_signin us
                inner join {$tablePrefix}users u on us.user_id = u.user_id
                inner join {$tablePrefix}user_details ud on u.user_id = ud.user_id
                where u.status != 'deleted'
                and us.location_id in ('{$mallId}')
                and us.created_at between '{$startDate}' and '{$endDate}'
                group by us.user_id
                ) us2
            ) report_unique_sign_in
        ";

        $selectSignUpColumns = "
            if(sign_up is null, 0, sign_up) as sign_up,

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
            if(sign_up_gender_unknown = 0 || sign_up_gender_unknown is null, 0, trim(round((sign_up_gender_unknown / sign_up) * 100, 2))+0) as sign_up_gender_unknown_percentage,

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
            if(sign_up_age_unknown = 0 || sign_up_age_unknown is null, 0, trim(round((sign_up_age_unknown / sign_up) * 100, 2))+0) as sign_up_age_unknown_percentage
        ";


        $selectSignInColumns = "
            if(sign_in is null, 0, sign_in) as sign_in,

            if(sign_in_type_facebook is null, 0, sign_in_type_facebook) as sign_in_type_facebook,
            if(sign_in_type_google is null, 0, sign_in_type_google) as sign_in_type_google,
            if(sign_in_type_form is null, 0, sign_in_type_form) as sign_in_type_form,
            if(sign_in_type_unknown is null, 0, sign_in_type_unknown) as sign_in_type_unknown,
            if(sign_in_type_facebook = 0 || sign_in_type_facebook is null, 0, trim(round((sign_in_type_facebook / sign_in) * 100, 2))+0) as sign_in_type_facebook_percentage,
            if(sign_in_type_google = 0 || sign_in_type_google is null, 0, trim(round((sign_in_type_google / sign_in) * 100, 2))+0) as sign_in_type_google_percentage,
            if(sign_in_type_form = 0 || sign_in_type_form is null, 0, trim(round((sign_in_type_form / sign_in) * 100, 2))+0) as sign_in_type_form_percentage,
            if(sign_in_type_unknown = 0 || sign_in_type_unknown is null, 0, trim(round((sign_in_type_unknown / sign_in) * 100, 2))+0) as sign_in_type_unknown_percentage,

            if(sign_in_gender_male is null, 0, sign_in_gender_male) as sign_in_gender_male,
            if(sign_in_gender_female is null, 0, sign_in_gender_female) as sign_in_gender_female,
            if(sign_in_gender_unknown is null, 0, sign_in_gender_unknown) as sign_in_gender_unknown,
            if(sign_in_gender_male = 0 || sign_in_gender_male is null, 0, trim(round((sign_in_gender_male / sign_in) * 100, 2))+0) as sign_in_gender_male_percentage,
            if(sign_in_gender_female = 0 || sign_in_gender_female is null, 0, trim(round((sign_in_gender_female / sign_in) * 100, 2))+0) as sign_in_gender_female_percentage,
            if(sign_in_gender_unknown = 0 || sign_in_gender_unknown is null, 0, trim(round((sign_in_gender_unknown / sign_in) * 100, 2))+0) as sign_in_gender_unknown_percentage,

            if(sign_in_age_0_to_14 is null, 0, sign_in_age_0_to_14) as sign_in_age_0_to_14,
            if(sign_in_age_15_to_24 is null, 0, sign_in_age_15_to_24) as sign_in_age_15_to_24,
            if(sign_in_age_25_to_34 is null, 0, sign_in_age_25_to_34) as sign_in_age_25_to_34,
            if(sign_in_age_35_to_44 is null, 0, sign_in_age_35_to_44) as sign_in_age_35_to_44,
            if(sign_in_age_45_to_54 is null, 0, sign_in_age_45_to_54) as sign_in_age_45_to_54,
            if(sign_in_age_55_to_64 is null, 0, sign_in_age_55_to_64) as sign_in_age_55_to_64,
            if(sign_in_age_65_plus is null, 0, sign_in_age_65_plus) as sign_in_age_65_plus,
            if(sign_in_age_unknown is null, 0, sign_in_age_unknown) as sign_in_age_unknown,
            if(sign_in_age_0_to_14 = 0 || sign_in_age_0_to_14 is null, 0, trim(round((sign_in_age_0_to_14 / sign_in) * 100, 2))+0) as sign_in_age_0_to_14_percentage,
            if(sign_in_age_15_to_24 = 0 || sign_in_age_15_to_24 is null, 0, trim(round((sign_in_age_15_to_24 / sign_in) * 100, 2))+0) as sign_in_age_15_to_24_percentage,
            if(sign_in_age_25_to_34 = 0 || sign_in_age_25_to_34 is null, 0, trim(round((sign_in_age_25_to_34 / sign_in) * 100, 2))+0) as sign_in_age_25_to_34_percentage,
            if(sign_in_age_35_to_44 = 0 || sign_in_age_35_to_44 is null, 0, trim(round((sign_in_age_35_to_44 / sign_in) * 100, 2))+0) as sign_in_age_35_to_44_percentage,
            if(sign_in_age_45_to_54 = 0 || sign_in_age_45_to_54 is null, 0, trim(round((sign_in_age_45_to_54 / sign_in) * 100, 2))+0) as sign_in_age_45_to_54_percentage,
            if(sign_in_age_55_to_64 = 0 || sign_in_age_55_to_64 is null, 0, trim(round((sign_in_age_55_to_64 / sign_in) * 100, 2))+0) as sign_in_age_55_to_64_percentage,
            if(sign_in_age_65_plus = 0 || sign_in_age_65_plus is null, 0, trim(round((sign_in_age_65_plus / sign_in) * 100, 2))+0) as sign_in_age_65_plus_percentage,
            if(sign_in_age_unknown = 0 || sign_in_age_unknown is null, 0, trim(round((sign_in_age_unknown / sign_in) * 100, 2))+0) as sign_in_age_unknown_percentage
        ";

        $selectUniqueSignInColumns = "
            if(unique_sign_in is null, 0, unique_sign_in) as unique_sign_in,

            if(unique_sign_in_type_facebook is null, 0, unique_sign_in_type_facebook) as unique_sign_in_type_facebook,
            if(unique_sign_in_type_google is null, 0, unique_sign_in_type_google) as unique_sign_in_type_google,
            if(unique_sign_in_type_form is null, 0, unique_sign_in_type_form) as unique_sign_in_type_form,
            if(unique_sign_in_type_unknown is null, 0, unique_sign_in_type_unknown) as unique_sign_in_type_unknown,
            if(unique_sign_in_type_facebook = 0 || unique_sign_in_type_facebook is null, 0, trim(round((unique_sign_in_type_facebook / unique_sign_in) * 100, 2))+0) as unique_sign_in_type_facebook_percentage,
            if(unique_sign_in_type_google = 0 || unique_sign_in_type_google is null, 0, trim(round((unique_sign_in_type_google / unique_sign_in) * 100, 2))+0) as unique_sign_in_type_google_percentage,
            if(unique_sign_in_type_form = 0 || unique_sign_in_type_form is null, 0, trim(round((unique_sign_in_type_form / unique_sign_in) * 100, 2))+0) as unique_sign_in_type_form_percentage,
            if(unique_sign_in_type_unknown = 0 || unique_sign_in_type_unknown is null, 0, trim(round((unique_sign_in_type_unknown / unique_sign_in) * 100, 2))+0) as unique_sign_in_type_unknown_percentage,

            if(unique_sign_in_gender_male is null, 0, unique_sign_in_gender_male) as unique_sign_in_gender_male,
            if(unique_sign_in_gender_female is null, 0, unique_sign_in_gender_female) as unique_sign_in_gender_female,
            if(unique_sign_in_gender_unknown is null, 0, unique_sign_in_gender_unknown) as unique_sign_in_gender_unknown,
            if(unique_sign_in_gender_male = 0 || unique_sign_in_gender_male is null, 0, trim(round((unique_sign_in_gender_male / unique_sign_in) * 100, 2))+0) as unique_sign_in_gender_male_percentage,
            if(unique_sign_in_gender_female = 0 || unique_sign_in_gender_female is null, 0, trim(round((unique_sign_in_gender_female / unique_sign_in) * 100, 2))+0) as unique_sign_in_gender_female_percentage,
            if(unique_sign_in_gender_unknown = 0 || unique_sign_in_gender_unknown is null, 0, trim(round((unique_sign_in_gender_unknown / unique_sign_in) * 100, 2))+0) as unique_sign_in_gender_unknown_percentage,

            if(unique_sign_in_age_0_to_14 is null, 0, unique_sign_in_age_0_to_14) as unique_sign_in_age_0_to_14,
            if(unique_sign_in_age_15_to_24 is null, 0, unique_sign_in_age_15_to_24) as unique_sign_in_age_15_to_24,
            if(unique_sign_in_age_25_to_34 is null, 0, unique_sign_in_age_25_to_34) as unique_sign_in_age_25_to_34,
            if(unique_sign_in_age_35_to_44 is null, 0, unique_sign_in_age_35_to_44) as unique_sign_in_age_35_to_44,
            if(unique_sign_in_age_45_to_54 is null, 0, unique_sign_in_age_45_to_54) as unique_sign_in_age_45_to_54,
            if(unique_sign_in_age_55_to_64 is null, 0, unique_sign_in_age_55_to_64) as unique_sign_in_age_55_to_64,
            if(unique_sign_in_age_65_plus is null, 0, unique_sign_in_age_65_plus) as unique_sign_in_age_65_plus,
            if(unique_sign_in_age_unknown is null, 0, unique_sign_in_age_unknown) as unique_sign_in_age_unknown,
            if(unique_sign_in_age_0_to_14 = 0 || unique_sign_in_age_0_to_14 is null, 0, trim(round((unique_sign_in_age_0_to_14 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_0_to_14_percentage,
            if(unique_sign_in_age_15_to_24 = 0 || unique_sign_in_age_15_to_24 is null, 0, trim(round((unique_sign_in_age_15_to_24 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_15_to_24_percentage,
            if(unique_sign_in_age_25_to_34 = 0 || unique_sign_in_age_25_to_34 is null, 0, trim(round((unique_sign_in_age_25_to_34 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_25_to_34_percentage,
            if(unique_sign_in_age_35_to_44 = 0 || unique_sign_in_age_35_to_44 is null, 0, trim(round((unique_sign_in_age_35_to_44 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_35_to_44_percentage,
            if(unique_sign_in_age_45_to_54 = 0 || unique_sign_in_age_45_to_54 is null, 0, trim(round((unique_sign_in_age_45_to_54 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_45_to_54_percentage,
            if(unique_sign_in_age_55_to_64 = 0 || unique_sign_in_age_55_to_64 is null, 0, trim(round((unique_sign_in_age_55_to_64 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_55_to_64_percentage,
            if(unique_sign_in_age_65_plus = 0 || unique_sign_in_age_65_plus is null, 0, trim(round((unique_sign_in_age_65_plus / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_65_plus_percentage,
            if(unique_sign_in_age_unknown = 0 || unique_sign_in_age_unknown is null, 0, trim(round((unique_sign_in_age_unknown / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_unknown_percentage
        ";

        $selectReturningColumns = "
            @returning := if(unique_sign_in is null, 0, unique_sign_in - if(sign_up is null, 0, sign_up)) as returning,
            if(@returning = 0 || @returning is null, 0, trim(round((@returning / unique_sign_in) * 100, 2))+0) as returning_percentage
        ";

        $selectStatusColumns = "
            if(unique_sign_in_status_active is null, 0, unique_sign_in_status_active) as unique_sign_in_status_active,
            if(unique_sign_in_status_pending is null, 0, unique_sign_in_status_pending) as unique_sign_in_status_pending,
            if(unique_sign_in_status_unknown is null, 0, unique_sign_in_status_unknown) as unique_sign_in_status_unknown,
            if(unique_sign_in_status_active = 0 || unique_sign_in_status_active is null, 0, trim(round((unique_sign_in_status_active / unique_sign_in) * 100, 2))+0) as unique_sign_in_status_active_percentage,
            if(unique_sign_in_status_pending = 0 || unique_sign_in_status_pending is null, 0, trim(round((unique_sign_in_status_pending / unique_sign_in) * 100, 2))+0) as unique_sign_in_status_pending_percentage,
            if(unique_sign_in_status_unknown = 0 || unique_sign_in_status_unknown is null, 0, trim(round((unique_sign_in_status_unknown / unique_sign_in) * 100, 2))+0) as unique_sign_in_status_unknown_percentage
        ";

        $records = DB::table(DB::raw($sequenceTable))
            ->selectRaw($selectSignUpColumns . ',' .
                $selectSignInColumns . ',' .
                $selectUniqueSignInColumns . ',' .
                $selectReturningColumns . ',' .
                $selectStatusColumns
            );

        $records->leftJoin(DB::raw($signUpReport), DB::raw('report_sign_up.grandtotal'), '=', DB::raw('grandtotal.grandtotal'));
        $records->leftJoin(DB::raw($signInReport), DB::raw('report_sign_in.grandtotal'), '=', DB::raw('grandtotal.grandtotal'));
        $records->leftJoin(DB::raw($uniqueSignInReport), DB::raw('report_unique_sign_in.grandtotal'), '=', DB::raw('grandtotal.grandtotal'));

        return $records->first();
    }
    
    private function prepareData($mallId, $mallTimezone, $startDate, $endDate, $timeDimensionType)
    {
        $tablePrefix = DB::getTablePrefix();

        $timezoneOffset = $this->quote($this->getTimezoneHoursDiff($mallTimezone));

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
                select date_format('{$mallStartDate}' + interval sequence_number - 1 day, '%Y-%m-%d') as sequence_date
                from {$tablePrefix}sequence s
                where s.sequence_number <= {$numberDays}
            ) report_date
        ";

        switch ($timeDimensionType) {
            case 'day_of_week':
                $sequenceTable = $dayOfWeek;
                $selectTimeDimensionColumns = 'day_of_week.sequence_number as report_day_of_week, day_of_week.day_of_week_name as report_day_of_week_name';
                $signUpGroupBy = 'sign_up_day_of_week';
                $signInGroupBy = 'sign_in_day_of_week';
                $uniqueSignInGroupBy = 'unique_sign_in_day_of_week';
                break;

            case 'hour_of_day':
                $sequenceTable = $hourOfDay;
                $selectTimeDimensionColumns = "hour_of_day.sequence_number as report_hour_of_day, concat(date_format(curdate() + interval hour_of_day.sequence_number hour, '%H'), ':00 - ', date_format(curdate() + interval hour_of_day.sequence_number + 1 hour, '%H'), ':00') as report_hour_of_day_name";
                $signUpGroupBy = 'sign_up_hour_of_day';
                $signInGroupBy = 'sign_in_hour_of_day';
                $uniqueSignInGroupBy = 'unique_sign_in_hour_of_day';
                break;

            case 'report_month':
                $sequenceTable = $reportMonth;
                $selectTimeDimensionColumns = "report_month.sequence_number as report_month, monthname(str_to_date(report_month.sequence_number, '%m')) as report_month_name";
                $signUpGroupBy = 'sign_up_month';
                $signInGroupBy = 'sign_in_month';
                $uniqueSignInGroupBy = 'unique_sign_in_month';
                break;

            case 'report_date':
            default:
                $sequenceTable = $reportDate;
                $selectTimeDimensionColumns = 'report_date.sequence_date as report_date';
                $signUpGroupBy = 'sign_up_date';
                $signInGroupBy = 'sign_in_date';
                $uniqueSignInGroupBy = 'unique_sign_in_date';
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

        $signInReport = "
                (
                select

                date_format(convert_tz(us2.created_at, '+00:00', {$timezoneOffset}), '%Y-%m-%d') as sign_in_date,
                cast(date_format(convert_tz(us2.created_at, '+00:00', {$timezoneOffset}), '%k') as signed) as sign_in_hour_of_day,
                cast(date_format(convert_tz(us2.created_at, '+00:00', {$timezoneOffset}), '%w') as signed) as sign_in_day_of_week,
                cast(date_format(convert_tz(us2.created_at, '+00:00', {$timezoneOffset}), '%c') as signed) as sign_in_month,

                count(us2.user_signin_id) as sign_in,

                sum(us2.signin_via = 'facebook') as sign_in_type_facebook,
                sum(us2.signin_via = 'google') as sign_in_type_google,
                sum(us2.signin_via = 'form') as sign_in_type_form,
                sum(us2.signin_via not in ('facebook', 'google', 'form')) as sign_in_type_unknown,

                sum(if(us2.gender = 'm', 1, 0)) as sign_in_gender_male,
                sum(if(us2.gender = 'f', 1, 0)) as sign_in_gender_female,
                sum(if(us2.gender not in ('m', 'f') || us2.gender is null, 1, 0)) as sign_in_gender_unknown,

                sum(if(us2.age >= 0 and us2.age <= 14, 1, 0)) as sign_in_age_0_to_14,
                sum(if(us2.age >= 15 and us2.age <= 24, 1, 0)) as sign_in_age_15_to_24,
                sum(if(us2.age >= 25 and us2.age <= 34, 1, 0)) as sign_in_age_25_to_34,
                sum(if(us2.age >= 35 and us2.age <= 44, 1, 0)) as sign_in_age_35_to_44,
                sum(if(us2.age >= 45 and us2.age <= 54, 1, 0)) as sign_in_age_45_to_54,
                sum(if(us2.age >= 55 and us2.age <= 64, 1, 0)) as sign_in_age_55_to_64,
                sum(if(us2.age >= 65, 1, 0)) as sign_in_age_65_plus,
                sum(if(us2.age is null, 1, 0)) as sign_in_age_unknown

                from
                    (
                    select us.user_signin_id, us.location_id, us.user_id, us.signin_via, us.created_at, ud.gender, timestampdiff(year, ud.birthdate, date_format(convert_tz(utc_timestamp(), '+00:00', {$timezoneOffset}), '%Y-%m-%d')) as age
                    from {$tablePrefix}user_signin us
                    inner join {$tablePrefix}users u on us.user_id = u.user_id
                    inner join {$tablePrefix}user_details ud on u.user_id = ud.user_id
                    where u.status != 'deleted'
                    and us.location_id in ('{$mallId}')
                    and us.created_at between '{$startDate}' and '{$endDate}'
                    ) us2
                group by {$signInGroupBy}
                ) report_sign_in
        ";

        $uniqueSignInReport = "
                (
                select

                unique_sign_in_date,
                unique_sign_in_hour_of_day,
                unique_sign_in_day_of_week,
                unique_sign_in_month,

                count(us2.user_id) as unique_sign_in,

                sum(us2.signin_via = 'facebook') as unique_sign_in_type_facebook,
                sum(us2.signin_via = 'google') as unique_sign_in_type_google,
                sum(us2.signin_via = 'form') as unique_sign_in_type_form,
                sum(us2.signin_via not in ('facebook', 'google', 'form')) as unique_sign_in_type_unknown,

                sum(if(us2.gender = 'm', 1, 0)) as unique_sign_in_gender_male,
                sum(if(us2.gender = 'f', 1, 0)) as unique_sign_in_gender_female,
                sum(if(us2.gender not in ('m', 'f') || us2.gender is null, 1, 0)) as unique_sign_in_gender_unknown,

                sum(if(us2.age >= 0 and us2.age <= 14, 1, 0)) as unique_sign_in_age_0_to_14,
                sum(if(us2.age >= 15 and us2.age <= 24, 1, 0)) as unique_sign_in_age_15_to_24,
                sum(if(us2.age >= 25 and us2.age <= 34, 1, 0)) as unique_sign_in_age_25_to_34,
                sum(if(us2.age >= 35 and us2.age <= 44, 1, 0)) as unique_sign_in_age_35_to_44,
                sum(if(us2.age >= 45 and us2.age <= 54, 1, 0)) as unique_sign_in_age_45_to_54,
                sum(if(us2.age >= 55 and us2.age <= 64, 1, 0)) as unique_sign_in_age_55_to_64,
                sum(if(us2.age >= 65, 1, 0)) as unique_sign_in_age_65_plus,
                sum(if(us2.age is null, 1, 0)) as unique_sign_in_age_unknown,

                sum(if(us2.status = 'active', 1, 0)) as unique_sign_in_status_active,
                sum(if(us2.status = 'pending', 1, 0)) as unique_sign_in_status_pending,
                sum(if(us2.status not in ('active', 'pending') || us2.status is null, 1, 0)) as unique_sign_in_status_unknown

                from
                    (
                    select
                    date_format(convert_tz(us.created_at, '+00:00', {$timezoneOffset}), '%Y-%m-%d') as unique_sign_in_date,
                    cast(date_format(convert_tz(us.created_at, '+00:00', {$timezoneOffset}), '%k') as signed) as unique_sign_in_hour_of_day,
                    cast(date_format(convert_tz(us.created_at, '+00:00', {$timezoneOffset}), '%w') as signed) as unique_sign_in_day_of_week,
                    cast(date_format(convert_tz(us.created_at, '+00:00', {$timezoneOffset}), '%c') as signed) as unique_sign_in_month,
                    us.user_signin_id, us.location_id, us.user_id, us.signin_via, us.created_at, ud.gender, timestampdiff(year, ud.birthdate, date_format(convert_tz(utc_timestamp(), '+00:00', {$timezoneOffset}), '%Y-%m-%d')) as age, u.status
                    from {$tablePrefix}user_signin us
                    inner join {$tablePrefix}users u on us.user_id = u.user_id
                    inner join {$tablePrefix}user_details ud on u.user_id = ud.user_id
                    where u.status != 'deleted'
                    and us.location_id in ('{$mallId}')
                    and us.created_at between '{$startDate}' and '{$endDate}'
                    group by {$uniqueSignInGroupBy}, us.user_id
                    ) us2
                group by {$uniqueSignInGroupBy}
                ) report_unique_sign_in
        ";

        $selectSignUpColumns = "
            if(sign_up is null, 0, sign_up) as sign_up,

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
            if(sign_up_gender_unknown = 0 || sign_up_gender_unknown is null, 0, trim(round((sign_up_gender_unknown / sign_up) * 100, 2))+0) as sign_up_gender_unknown_percentage,

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
            if(sign_up_age_unknown = 0 || sign_up_age_unknown is null, 0, trim(round((sign_up_age_unknown / sign_up) * 100, 2))+0) as sign_up_age_unknown_percentage
        ";

        $selectSignInColumns = "
            if(sign_in is null, 0, sign_in) as sign_in,

            if(sign_in_type_facebook is null, 0, sign_in_type_facebook) as sign_in_type_facebook,
            if(sign_in_type_google is null, 0, sign_in_type_google) as sign_in_type_google,
            if(sign_in_type_form is null, 0, sign_in_type_form) as sign_in_type_form,
            if(sign_in_type_unknown is null, 0, sign_in_type_unknown) as sign_in_type_unknown,
            if(sign_in_type_facebook = 0 || sign_in_type_facebook is null, 0, trim(round((sign_in_type_facebook / sign_in) * 100, 2))+0) as sign_in_type_facebook_percentage,
            if(sign_in_type_google = 0 || sign_in_type_google is null, 0, trim(round((sign_in_type_google / sign_in) * 100, 2))+0) as sign_in_type_google_percentage,
            if(sign_in_type_form = 0 || sign_in_type_form is null, 0, trim(round((sign_in_type_form / sign_in) * 100, 2))+0) as sign_in_type_form_percentage,
            if(sign_in_type_unknown = 0 || sign_in_type_unknown is null, 0, trim(round((sign_in_type_unknown / sign_in) * 100, 2))+0) as sign_in_type_unknown_percentage,

            if(sign_in_gender_male is null, 0, sign_in_gender_male) as sign_in_gender_male,
            if(sign_in_gender_female is null, 0, sign_in_gender_female) as sign_in_gender_female,
            if(sign_in_gender_unknown is null, 0, sign_in_gender_unknown) as sign_in_gender_unknown,
            if(sign_in_gender_male = 0 || sign_in_gender_male is null, 0, trim(round((sign_in_gender_male / sign_in) * 100, 2))+0) as sign_in_gender_male_percentage,
            if(sign_in_gender_female = 0 || sign_in_gender_female is null, 0, trim(round((sign_in_gender_female / sign_in) * 100, 2))+0) as sign_in_gender_female_percentage,
            if(sign_in_gender_unknown = 0 || sign_in_gender_unknown is null, 0, trim(round((sign_in_gender_unknown / sign_in) * 100, 2))+0) as sign_in_gender_unknown_percentage,

            if(sign_in_age_0_to_14 is null, 0, sign_in_age_0_to_14) as sign_in_age_0_to_14,
            if(sign_in_age_15_to_24 is null, 0, sign_in_age_15_to_24) as sign_in_age_15_to_24,
            if(sign_in_age_25_to_34 is null, 0, sign_in_age_25_to_34) as sign_in_age_25_to_34,
            if(sign_in_age_35_to_44 is null, 0, sign_in_age_35_to_44) as sign_in_age_35_to_44,
            if(sign_in_age_45_to_54 is null, 0, sign_in_age_45_to_54) as sign_in_age_45_to_54,
            if(sign_in_age_55_to_64 is null, 0, sign_in_age_55_to_64) as sign_in_age_55_to_64,
            if(sign_in_age_65_plus is null, 0, sign_in_age_65_plus) as sign_in_age_65_plus,
            if(sign_in_age_unknown is null, 0, sign_in_age_unknown) as sign_in_age_unknown,
            if(sign_in_age_0_to_14 = 0 || sign_in_age_0_to_14 is null, 0, trim(round((sign_in_age_0_to_14 / sign_in) * 100, 2))+0) as sign_in_age_0_to_14_percentage,
            if(sign_in_age_15_to_24 = 0 || sign_in_age_15_to_24 is null, 0, trim(round((sign_in_age_15_to_24 / sign_in) * 100, 2))+0) as sign_in_age_15_to_24_percentage,
            if(sign_in_age_25_to_34 = 0 || sign_in_age_25_to_34 is null, 0, trim(round((sign_in_age_25_to_34 / sign_in) * 100, 2))+0) as sign_in_age_25_to_34_percentage,
            if(sign_in_age_35_to_44 = 0 || sign_in_age_35_to_44 is null, 0, trim(round((sign_in_age_35_to_44 / sign_in) * 100, 2))+0) as sign_in_age_35_to_44_percentage,
            if(sign_in_age_45_to_54 = 0 || sign_in_age_45_to_54 is null, 0, trim(round((sign_in_age_45_to_54 / sign_in) * 100, 2))+0) as sign_in_age_45_to_54_percentage,
            if(sign_in_age_55_to_64 = 0 || sign_in_age_55_to_64 is null, 0, trim(round((sign_in_age_55_to_64 / sign_in) * 100, 2))+0) as sign_in_age_55_to_64_percentage,
            if(sign_in_age_65_plus = 0 || sign_in_age_65_plus is null, 0, trim(round((sign_in_age_65_plus / sign_in) * 100, 2))+0) as sign_in_age_65_plus_percentage,
            if(sign_in_age_unknown = 0 || sign_in_age_unknown is null, 0, trim(round((sign_in_age_unknown / sign_in) * 100, 2))+0) as sign_in_age_unknown_percentage
        ";

        $selectUniqueSignInColumns = "
            if(unique_sign_in is null, 0, unique_sign_in) as unique_sign_in,

            if(unique_sign_in_type_facebook is null, 0, unique_sign_in_type_facebook) as unique_sign_in_type_facebook,
            if(unique_sign_in_type_google is null, 0, unique_sign_in_type_google) as unique_sign_in_type_google,
            if(unique_sign_in_type_form is null, 0, unique_sign_in_type_form) as unique_sign_in_type_form,
            if(unique_sign_in_type_unknown is null, 0, unique_sign_in_type_unknown) as unique_sign_in_type_unknown,
            if(unique_sign_in_type_facebook = 0 || unique_sign_in_type_facebook is null, 0, trim(round((unique_sign_in_type_facebook / unique_sign_in) * 100, 2))+0) as unique_sign_in_type_facebook_percentage,
            if(unique_sign_in_type_google = 0 || unique_sign_in_type_google is null, 0, trim(round((unique_sign_in_type_google / unique_sign_in) * 100, 2))+0) as unique_sign_in_type_google_percentage,
            if(unique_sign_in_type_form = 0 || unique_sign_in_type_form is null, 0, trim(round((unique_sign_in_type_form / unique_sign_in) * 100, 2))+0) as unique_sign_in_type_form_percentage,
            if(unique_sign_in_type_unknown = 0 || unique_sign_in_type_unknown is null, 0, trim(round((unique_sign_in_type_unknown / unique_sign_in) * 100, 2))+0) as unique_sign_in_type_unknown_percentage,

            if(unique_sign_in_gender_male is null, 0, unique_sign_in_gender_male) as unique_sign_in_gender_male,
            if(unique_sign_in_gender_female is null, 0, unique_sign_in_gender_female) as unique_sign_in_gender_female,
            if(unique_sign_in_gender_unknown is null, 0, unique_sign_in_gender_unknown) as unique_sign_in_gender_unknown,
            if(unique_sign_in_gender_male = 0 || unique_sign_in_gender_male is null, 0, trim(round((unique_sign_in_gender_male / unique_sign_in) * 100, 2))+0) as unique_sign_in_gender_male_percentage,
            if(unique_sign_in_gender_female = 0 || unique_sign_in_gender_female is null, 0, trim(round((unique_sign_in_gender_female / unique_sign_in) * 100, 2))+0) as unique_sign_in_gender_female_percentage,
            if(unique_sign_in_gender_unknown = 0 || unique_sign_in_gender_unknown is null, 0, trim(round((unique_sign_in_gender_unknown / unique_sign_in) * 100, 2))+0) as unique_sign_in_gender_unknown_percentage,

            if(unique_sign_in_age_0_to_14 is null, 0, unique_sign_in_age_0_to_14) as unique_sign_in_age_0_to_14,
            if(unique_sign_in_age_15_to_24 is null, 0, unique_sign_in_age_15_to_24) as unique_sign_in_age_15_to_24,
            if(unique_sign_in_age_25_to_34 is null, 0, unique_sign_in_age_25_to_34) as unique_sign_in_age_25_to_34,
            if(unique_sign_in_age_35_to_44 is null, 0, unique_sign_in_age_35_to_44) as unique_sign_in_age_35_to_44,
            if(unique_sign_in_age_45_to_54 is null, 0, unique_sign_in_age_45_to_54) as unique_sign_in_age_45_to_54,
            if(unique_sign_in_age_55_to_64 is null, 0, unique_sign_in_age_55_to_64) as unique_sign_in_age_55_to_64,
            if(unique_sign_in_age_65_plus is null, 0, unique_sign_in_age_65_plus) as unique_sign_in_age_65_plus,
            if(unique_sign_in_age_unknown is null, 0, unique_sign_in_age_unknown) as unique_sign_in_age_unknown,
            if(unique_sign_in_age_0_to_14 = 0 || unique_sign_in_age_0_to_14 is null, 0, trim(round((unique_sign_in_age_0_to_14 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_0_to_14_percentage,
            if(unique_sign_in_age_15_to_24 = 0 || unique_sign_in_age_15_to_24 is null, 0, trim(round((unique_sign_in_age_15_to_24 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_15_to_24_percentage,
            if(unique_sign_in_age_25_to_34 = 0 || unique_sign_in_age_25_to_34 is null, 0, trim(round((unique_sign_in_age_25_to_34 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_25_to_34_percentage,
            if(unique_sign_in_age_35_to_44 = 0 || unique_sign_in_age_35_to_44 is null, 0, trim(round((unique_sign_in_age_35_to_44 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_35_to_44_percentage,
            if(unique_sign_in_age_45_to_54 = 0 || unique_sign_in_age_45_to_54 is null, 0, trim(round((unique_sign_in_age_45_to_54 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_45_to_54_percentage,
            if(unique_sign_in_age_55_to_64 = 0 || unique_sign_in_age_55_to_64 is null, 0, trim(round((unique_sign_in_age_55_to_64 / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_55_to_64_percentage,
            if(unique_sign_in_age_65_plus = 0 || unique_sign_in_age_65_plus is null, 0, trim(round((unique_sign_in_age_65_plus / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_65_plus_percentage,
            if(unique_sign_in_age_unknown = 0 || unique_sign_in_age_unknown is null, 0, trim(round((unique_sign_in_age_unknown / unique_sign_in) * 100, 2))+0) as unique_sign_in_age_unknown_percentage
        ";

        $selectReturningColumns = "
            @returning := if(unique_sign_in is null, 0, unique_sign_in - if(sign_up is null, 0, sign_up)) as returning,
            if(@returning = 0 || @returning is null, 0, trim(round((@returning / unique_sign_in) * 100, 2))+0) as returning_percentage
        ";

        $selectStatusColumns = "
            if(unique_sign_in_status_active is null, 0, unique_sign_in_status_active) as unique_sign_in_status_active,
            if(unique_sign_in_status_pending is null, 0, unique_sign_in_status_pending) as unique_sign_in_status_pending,
            if(unique_sign_in_status_unknown is null, 0, unique_sign_in_status_unknown) as unique_sign_in_status_unknown,
            if(unique_sign_in_status_active = 0 || unique_sign_in_status_active is null, 0, trim(round((unique_sign_in_status_active / unique_sign_in) * 100, 2))+0) as unique_sign_in_status_active_percentage,
            if(unique_sign_in_status_pending = 0 || unique_sign_in_status_pending is null, 0, trim(round((unique_sign_in_status_pending / unique_sign_in) * 100, 2))+0) as unique_sign_in_status_pending_percentage,
            if(unique_sign_in_status_unknown = 0 || unique_sign_in_status_unknown is null, 0, trim(round((unique_sign_in_status_unknown / unique_sign_in) * 100, 2))+0) as unique_sign_in_status_unknown_percentage
        ";

        $records = DB::table(DB::raw($sequenceTable))
            ->selectRaw($selectTimeDimensionColumns . ',' .
                       $selectSignUpColumns . ',' .
                       $selectSignInColumns . ',' .
                       $selectUniqueSignInColumns . ',' .
                       $selectReturningColumns . ',' .
                       $selectStatusColumns
                       );

        switch ($timeDimensionType) {
            case 'day_of_week':
                $records->leftJoin(DB::raw($signUpReport), DB::raw('report_sign_up.sign_up_day_of_week'), '=', DB::raw('day_of_week.sequence_number'));
                $records->leftJoin(DB::raw($signInReport), DB::raw('report_sign_in.sign_in_day_of_week'), '=', DB::raw('day_of_week.sequence_number'));
                $records->leftJoin(DB::raw($uniqueSignInReport), DB::raw('report_unique_sign_in.unique_sign_in_day_of_week'), '=', DB::raw('day_of_week.sequence_number'));
                break;

            case 'hour_of_day':
                $records->leftJoin(DB::raw($signUpReport), DB::raw('report_sign_up.sign_up_hour_of_day'), '=', DB::raw('hour_of_day.sequence_number'));
                $records->leftJoin(DB::raw($signInReport), DB::raw('report_sign_in.sign_in_hour_of_day'), '=', DB::raw('hour_of_day.sequence_number'));
                $records->leftJoin(DB::raw($uniqueSignInReport), DB::raw('report_unique_sign_in.unique_sign_in_hour_of_day'), '=', DB::raw('hour_of_day.sequence_number'));
                break;

            case 'report_month':
                $records->leftJoin(DB::raw($signUpReport), DB::raw('report_sign_up.sign_up_month'), '=', DB::raw('report_month.sequence_number'));
                $records->leftJoin(DB::raw($signInReport), DB::raw('report_sign_in.sign_in_month'), '=', DB::raw('report_month.sequence_number'));
                $records->leftJoin(DB::raw($uniqueSignInReport), DB::raw('report_unique_sign_in.unique_sign_in_month'), '=', DB::raw('report_month.sequence_number'));
                break;

            case 'report_date':
            default:
                $records->leftJoin(DB::raw($signUpReport), DB::raw('report_sign_up.sign_up_date'), '=', DB::raw('report_date.sequence_date'));
                $records->leftJoin(DB::raw($signInReport), DB::raw('report_sign_in.sign_in_date'), '=', DB::raw('report_date.sequence_date'));
                $records->leftJoin(DB::raw($uniqueSignInReport), DB::raw('report_unique_sign_in.unique_sign_in_date'), '=', DB::raw('report_date.sequence_date'));
        }

        $this->rows = $records;
    }

    public function getUserReport()
    {
        // Do validation
        if (!$this->validate()) {
            return $this->render($this->errorCode);
        }        

        $mallId = OrbitInput::get('current_mall');
        $mallTimezone = OrbitInput::get('timezone');
        $startDate = OrbitInput::get('start_date');
        $endDate = OrbitInput::get('end_date');
        $timeDimensionType = OrbitInput::get('time_dimension_type');

        $mallTimezone = Mall::leftJoin('timezones', 'timezones.timezone_id', '=', 'merchants.timezone_id')
            ->where('merchants.merchant_id', '=', $mallId)
            ->first()->timezone_name;

        /**        
        Special sort keys:
            day_of_week  --> sequence_number
            hour_of_day  --> sequence_number
            report_date  --> sequence_date
            report_month --> sequence_number
        **/
        $sortKey = Input::get('sortby', 'sign_up');
        switch ($sortKey) {
            case 'day_of_week':
            case 'hour_of_day':
            case 'report_month';
            case 'month':
                $sortKey = 'sequence_number';
                break;
            case 'date':
            case 'report_date':
                $sortKey = 'sequence_date';
                break;
        }

        $sortType = Input::get('sortmode', 'asc');

        $take = Input::get('take');
        $skip = Input::get('skip');

        $data = new stdClass();

        $this->prepareData($mallId, $mallTimezone, $startDate, $endDate, $timeDimensionType);

        // For Totals counting
        $allRows = clone $this->rows;
        
        $totalCount = $this->rows->count();
        if (!$this->returnBuilder) {
            $this->rows->take($take)->skip($skip);
        }
        
        $rows = $this->rows->orderBy($sortKey, $sortType)->get();
        foreach ($rows as $row) {
            switch ($timeDimensionType) {
                case 'day_of_week':
                    $firstColumnArray['day_of_week'] = $row->report_day_of_week_name;
                    unset($row->report_day_of_week, $row->report_day_of_week_name);
                    break;
                case 'hour_of_day':
                    $firstColumnArray['hour_of_day'] = $row->report_hour_of_day_name;
                    unset($row->report_hour_of_day, $row->report_hour_of_day_name);
                    break;
                case 'report_date':
                    $firstColumnArray['date'] = Carbon::createFromFormat('Y-m-d', $row->report_date)->format('j M Y');
                    unset($row->report_date);
                    break;
                case 'report_month':
                    $firstColumnArray['month'] = $row->report_month_name;
                    unset($row->report_month, $row->report_month_name);
                    break;
            }

            // Add "%" sign on each of percentage column
            foreach ($row as $rowKey => $rowValue) {
                if (substr($rowKey, -11) == '_percentage') {
                    $row->{$rowKey} = $row->{$rowKey}.'%';
                }
            }

            $records[] = array_merge($firstColumnArray, (array) $row);
        }

        $data->columns = $this->getOutputColumns($timeDimensionType);
        $data->records = $records;

        // Get the row of Totals
        $totalRow = new stdClass();
        foreach ($allRows->get() as $row) {
            foreach ($row as $rowKey => $rowValue) {
                @$totalRow->{$rowKey} += $rowValue;
            }
        }

        foreach (Config::get('orbit_user_report_total_columns') as $key => $title) {
            $totals[$key] = [
                'title' => $title,
                'total' => $totalRow->{$key},
            ];
        }

        // Return the instance of Query Builder
        if ($this->returnBuilder) {
            return ['builder' => $records, 'totals' => $totals];
        }

        $data->totals = $totals;

        $data->returned_records = count($records);
        $data->total_records = $totalCount;

        $this->response->data = $data;
        return $this->render(200);
    }

    private function getOutputColumns($timeDimensionType)
    {
        switch ($timeDimensionType) {
            case 'day_of_week':
                $firstColumn = [
                    'day_of_week' => [
                        'title' => 'Day of Week',
                        'sort_key' => 'day_of_week',
                    ],
                ];
                break;
                
            case 'hour_of_day':
                $firstColumn = [
                    'hour_of_day' => [
                        'title' => 'Hour of Day',
                        'sort_key' => 'hour_of_day',
                    ],
                ];
                break;

            case 'report_date':
                $firstColumn = [
                    'date' => [
                        'title' => 'Date',
                        'sort_key' => 'date',
                    ],
                ];
                break;
                
            case 'report_month':
                $firstColumn = [
                    'month' => [
                        'title' => 'Month',
                        'sort_key' => 'month',
                    ],
                ];
                break;
        }

        return array_merge($firstColumn, Config::get('orbit_user_report_columns'));
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

    protected function validate()
    {
        $validator = Validator::make([
            'current_mall'         => Input::get('current_mall'),
            'start_date'           => Input::get('start_date'),
            'end_date'             => Input::get('end_date'),
            'time_dimension_type'  => Input::get('time_dimension_type'),
            'sortby'               => Input::get('sortby'),
            'sortmode'             => Input::get('sortmode'),
            'take'                 => Input::get('take'),
            'skip'                 => Input::get('skip'),
        ],
        [
            'current_mall'         => 'required',
            'start_date'           => 'required|date_format:Y-m-d H:i:s',
            'end_date'             => 'required|date_format:Y-m-d H:i:s|after:start_date',
            'time_dimension_type'  => 'required|in:day_of_week,hour_of_day,report_date,report_month',
            'sortby'               => 'required',
            'sortmode'             => 'in:asc,desc',
            'take'                 => 'required|integer',
            'skip'                 => 'required|integer',
        ]);

        try {
            if ($validator->fails()) {
                OrbitShopAPI::throwInvalidArgument($validator->messages()->first());
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.userreport.getuserreport.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            $this->errorCode = 400;
            return false;
        }

        return true;
    }

}
