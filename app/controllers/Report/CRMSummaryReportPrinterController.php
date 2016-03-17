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
use ActivityAPIController;


class CRMSummaryReportPrinterController extends DataPrinterController
{
    public function getCRMSummaryReportPrintView()
    {
        $this->preparePDO();
        $tablePrefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $flag_7days = false;
        $flag_noconfig = false;

        $current_mall = OrbitInput::get('current_mall');
        $start_date = OrbitInput::get('start_date');
        $end_date = OrbitInput::get('end_date');

        // check if the days is more than 7 or not
        $_startDate = strtotime($start_date);
        $_endDate = strtotime($end_date);
        $dateDiff = $_startDate - $_endDate;
        $days = abs(floor($dateDiff / (60 * 60 * 24)));

        if ($days > 7) {
            $flag_7days = true;
        }

        if ( $start_date > $end_date ) {
            $flag_7days = true;
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
        $responses = [];

        foreach ($_dateRange as $date) {
            $dateRange[] = $date->format("Y-m-d");
        }


        if (!$flag_7days) {
            $builder = ActivityAPIController::create('raw')->setReturnQuery(TRUE)->getCRMSummaryReport();      
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

                foreach ($builder['responses'] as $key => $value) {
                    foreach ($value as $key2 => $value2) {
                        if ($key2 != 'date') {
                            printf(",");
                        }
                        printf("\"%s\"", ucwords(str_replace('_', ' ', $key2)));
                    }
                    break;
                    printf("\n");
                }

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');

                foreach ($builder['responses'] as $key => $value) {
                    foreach ($value as $key2 => $value2) {
                        if ($key2 === 'date') {
                            printf("%s,", $this->printDateTime($value2, 'd/m/Y'));
                        } else {
                            printf("\"%s\"", $this->printFormatNumber((int)$value2));
                            printf(",");
                        }
                    }
                    printf("\n");
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


    public function in_array_r($products, $field, $value)
    {
        foreach($products as $key => $product)
        {
            if ( $product[$field] === $value )
                return $key;
        }
        return false;
    }

    public function printFormatNumber($number)
    {
        return number_format($number, 0,'.','.');
    }

}