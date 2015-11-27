<?php namespace Report;

use Config;
use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use OrbitShop\API\v1\OrbitShopAPI;
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

        // check if the days is more than 7 or not
        $_startDate = strtotime($start_date);
        $_endDate = strtotime($end_date);
        $dateDiff = $_startDate - $_endDate;
        $days = abs(floor($dateDiff/(60*60*24)));

        if ( $days > 7 ) {
            $errorMessage = 'Date cannot be more than 7 days';
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

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

        $activities = DB::select("
					select date_format(convert_tz(created_at, '+00:00', ?), '%Y-%m-%d') activity_date, activity_name_long, count(activity_id) as `count`
					from {$tablePrefix}activities
					-- filter by date
					where (`group` = 'mobile-ci'
					    or (`group` = 'portal' and activity_type in ('activation'))
					    or (`group` = 'cs-portal' and activity_type in ('registration')))
					    and response_status = 'OK' and location_id = ?
					    and created_at between ? and ?
					group by 1, 2;
                ", array($timezoneOffset, $current_mall, $start_date, $end_date));


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
                $data[$i][$valueA['order']] = $index > -1 ? $value[$index]['count'] : 0;
            }

            $i++;
        }

        usort($dates, function($a, $b) {
            return strtotime($a['label']) - strtotime($b['label']);
        });

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
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'CRM Summary', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');
                printf("Date,");
                foreach ($columns as $x => $y) {
                    if ($x > 0) {
                        printf(",");
                    }
                    printf("\"%s\"", $y['label']);
                    if (count($columns) - 1 === $x) {
                        printf("\n");
                    }
                }
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');

                foreach ($dates as $x => $y) {
                        printf("%s,", $this->printDateTime($y['label'], 'd/m/Y'));
                        foreach ($columns as $i => $j) {
                            if ($i > 0) {
                                printf(",");
                            }
                            printf("%s", $data[$y['order']][$j['order']]);
                            if (count($columns) - 1 === $i ) {
                                printf("\n");
                            }
                        }
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