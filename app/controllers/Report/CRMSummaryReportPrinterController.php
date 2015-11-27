<?php namespace Report;

use Config;
use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Mall;
use DateTime;
use DateTimeZone;
use DateInterval;
use DatePeriod;


class CRMSummaryReportPrinterController extends DataPrinterController
{
    public function getCRMSummaryReportPrintView()
    {
        $this->preparePDO();
        $tablePrefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;

        $current_mall = OrbitInput::get('current_mall');
        $start_date = OrbitInput::get('start_date');
        $end_date = OrbitInput::get('end_date');

        $timezone = $this->getTimezone($current_mall);
        $timezoneOffset = $this->getTimezoneOffset($timezone);

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

        $activities = DB::select( DB::raw("
					select date_format(convert_tz(created_at, '+00:00', '" . $timezoneOffset . "'), '%Y-%m-%d') activity_date, activity_name_long, count(activity_id) as `count`
					from {$tablePrefix}activities
					-- filter by date
					where `group` = 'mobile-ci'
					    or (`group` = 'portal' and activity_type in ('activation'))
					    or (`group` = 'cs-portal' and activity_type in ('registration'))
					    and response_status = 'OK' and location_id = '" . $current_mall . "'
					    and created_at between '" . $start_date . "' and '" . $end_date . "'
					group by 1, 2;
                ") );

        $responses = [];

        foreach ( $dateRange as $key => $value ) {

            foreach ( $activities as $x => $y ) {
                if ( $y->activity_date === $value ) {

                    $date = [];
                    $date['name'] = $y->activity_name_long;
                    $date['count'] = $y->count;

                    $responses[$value][] = $date;
                }
            }
        }

        // if there is date that have no data
        $dateRange2 = $dateRange;

        foreach ($responses as $a => $b) {
            $length = count($dateRange);
            for ($i = 0; $i < $length; $i++) {
                if ($a===$dateRange[$i]) {
                    unset($dateRange2[$i]);
                }
            }
        }

        foreach ($dateRange2 as $x => $y) {
            $responses[$dateRange2[$x]] = array();
        }

        $activity_columns  = Config::get('orbit.activity_columns');
        $columns = [];

        $i = 0;
        foreach ($activity_columns as $key => $value) {
            $colTemp = [];
            $colTemp['order'] = $i;
            $colTemp['value'] = $value;
            $colTemp['label'] = $key;
            array_push($columns, $colTemp);
            $i++;
        }

        $dates = [];
        $data = [];
        $i = 0;
        foreach ($responses as $key => $value) {
            $dateTemp = [];
            $dateTemp['order'] = $i;
            $dateTemp['label'] = $key;
            array_push($dates, $dateTemp);

            foreach ($columns as $keyA => $valueA) {
                $index = $this->in_array_r($value, 'name', $valueA['value']);
                //$data[$i][$valueA['order']] = $index ? $value[$index]['count'] : 0;
                $data[$key][$valueA['label']] = $index ? $value[$index]['count'] : 0;
            }

            $i++;
        }

        ksort($data);

        // special for export csv
        $data2 = $data;
        foreach ($data as $x => $y) {
            $data2[$x]['date'] = $x;
        }

        $pageTitle = 'CRM Summary Report';
        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'CRM Summary List', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                    'Date', 'Email Sign Up', 'Facebook Sign Up', 'Sign In', 'Sign Up via CS', 'Customer Activation',
                    'Network Check In', 'Network Check Out', 'Sign Out', 'View (Home Page)','Event View (Pop Up)',
                    'Event Click','View Coupon List','View Coupon Detail','Coupon Redemption Successful',
                    'Coupon Issuance','View Events Tenant List','View News List','View News Detail','View News Tenant List',
                    'View Promotion List','View Promotion Detail','View Promotion Tenant List','View Tenant Detail',
                    'Widget Click Tenant','Widget Click News','Widget Click Promotion','Widget Click Coupon'
                    );
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');

                foreach ($data2 as $x => $y) {
                        printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                        $this->printDateTime($data2[$x]['date'], 'd/m/Y'), $data2[$x]['Email Sign Up'], $data2[$x]['Facebook Sign Up'],
                        $data2[$x]['Sign In'], $data2[$x]['Sign Up via CS'], $data2[$x]['Customer Activation'], $data2[$x]['Network Check In'],
                        $data2[$x]['Network Check Out'], $data2[$x]['Sign Out'], $data2[$x]['View (Home Page)'], $data2[$x]['Event View (Pop Up)'],
                        $data2[$x]['Event Click'], $data2[$x]['View Coupon List'], $data2[$x]['View Coupon Detail'], $data2[$x]['Coupon Redemption Successful'],
                        $data2[$x]['Coupon Issuance'], $data2[$x]['View Events Tenant List'], $data2[$x]['View News List'], $data2[$x]['View News Detail'],
                        $data2[$x]['View News Tenant List'], $data2[$x]['View Promotion List'], $data2[$x]['View Promotion Detail'], $data2[$x]['View Promotion Tenant List'],
                        $data2[$x]['View Tenant Detail'], $data2[$x]['Widget Click Tenant'], $data2[$x]['Widget Click News'], $data2[$x]['Widget Click Promotion'], $data2[$x]['Widget Click Coupon']
                    );
                }

                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-crm-summary-report-view.php';
        }
    }

    public function getTimezone($current_mall)
    {
        $timezone = Mall::leftJoin('timezones', 'timezones.timezone_id', '=', 'merchants.timezone_id')
            ->where('merchants.merchant_id', '=', $current_mall)
            ->first();

        return $timezone->timezone_name;
    }

    public function getTimezoneOffset($timezone)
    {
        $dt = new DateTime('now', new DateTimeZone($timezone));

        return $dt->format('P');
    }

    /**
     * Print date and time friendly name.
     *
     * @param string $datetime
     * @param string $format
     * @return string
     */
    public function printDateTime($datetime, $format='d M Y')
    {
        // format the datetime if needed
        if ($format == 'no') {
            $result = $datetime;
        } else {
            $time = strtotime($datetime);
            $result = date($format, $time);
        }

        return $result;
    }


    function in_array_r($products, $field, $value)
    {
        foreach($products as $key => $product)
        {
            if ( $product[$field] === $value )
                return $key;
        }
        return false;
    }


}