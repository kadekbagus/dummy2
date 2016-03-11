<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use Activity;
use UserReportAPIController;
use Response;
use Mall;
use Carbon\Carbon as Carbon;

class UserReportPrinterController extends DataPrinterController
{

    public function getPrintUserReport()
    {
        $timeDimensionType = OrbitInput::get('time_dimension_type');

        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');
        $startDate = OrbitInput::get('start_date');
        $endDate = OrbitInput::get('end_date');

        $user = $this->loggedUser;

        // Instantiate the UserReportAPIController to get the data
        $response = UserReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getUserReport();

        $timezone = $this->getTimezoneMall($current_mall);

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $userReportData = $response['builder'];
        $userReportTotal = $response['totals'];

        // remove unwanted data
        foreach ($userReportTotal as $key => $value) {
            unset($userReportTotal['sign_in_type_facebook']);
            unset($userReportTotal['sign_in_type_google']);
            unset($userReportTotal['sign_in_type_form']);
            unset($userReportTotal['sign_in_type_facebook_percentage']);
            unset($userReportTotal['sign_in_type_google_percentage']);
            unset($userReportTotal['sign_in_type_form_percentage']);

            unset($userReportTotal['unique_sign_in_type_facebook']);
            unset($userReportTotal['unique_sign_in_type_google']);
            unset($userReportTotal['unique_sign_in_type_form']);
            unset($userReportTotal['unique_sign_in_type_facebook_percentage']);
            unset($userReportTotal['unique_sign_in_type_google_percentage']);
            unset($userReportTotal['unique_sign_in_type_form_percentage']);
        }

        // include percentage
        $userReportHeader = [];  
        foreach ($userReportTotal as $key => $value) {
                $userReportHeader[] = array(
                                'key' => $key,
                                'title' => $value['title'],
                                'total' => $value['total']
                            );
                      
        }

        // exclude percentage
        $userReportHeaderExcludePercent = [];  
        foreach ($userReportTotal as $key => $value) {
            if( !strpos($value['title'], '(%)') ) {
                $userReportHeaderExcludePercent[] = array(
                                'key' => $key,
                                'title' => $value['title'],
                                'total' => $value['total']
                            );
            }
                      
        }

        $timeDimensionTitle = null;
        $timeDimension = null;

        switch ($timeDimensionType) {
            case 'day_of_week':
                $timeDimensionTitle = 'Days of Week';
                $timeDimension = 'day_of_week';
                break;
            case 'hour_of_day':
                $timeDimensionTitle = 'Hour of Day';
                $timeDimension = 'hour_of_day';
                break;
            case 'report_date':
                $timeDimensionTitle = 'Date';
                $timeDimension = 'date';
                break;
            case 'report_month':
                $timeDimensionTitle = 'Month';
                $timeDimension = 'month';
                break;
            default:
                $timeDimensionTitle = 'Date';
                $timeDimension = 'date';
        }

        $pageTitle = 'User Report';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", 'User Report', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                $datePeriod = $this->printDatePeriod($startDate, $endDate);

                foreach ($userReportHeaderExcludePercent as $value) {
                    printf('%s,%s', 'Total '.$value['title'], $value['total']);
                    printf("\n");
                }

                printf('%s,%s', 'Date Period', $datePeriod);

                printf("\n");
                printf("\n");

                printf('%s,', $timeDimensionTitle);
                foreach ($userReportHeader as $value) {

                    printf('%s,', $value['title']);
                }

                printf("\n");

                foreach ($userReportData as $key => $value) {

                    printf('%s, ', $value[$timeDimension]);
                    foreach ($userReportHeader as $header_value) {
                        $x = $header_value['key'];
                        printf('%s,', $value[$x]);
                    }

                    printf("\n");
                }

                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-user-report-view.php';
        }
    }



    /**
     * Print date and time friendly name.
     *
     * @param string $datetime
     * @param string $format
     * @return string
     */
    public function printDateTime($datetime, $timezone, $format='d M Y')
    {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
            return '';
        } else {

            // change to correct timezone
            if (!empty($timezone) || $timezone != null) {
                $date = Carbon::createFromFormat('Y-m-d H:i:s', $datetime, 'UTC');
                $date->setTimezone($timezone);
                $datetime = $date;
            } else {
                $datetime = $datetime;
            }
        }

        // format the datetime if needed
        if ($format == 'no') {
            $result = $datetime;
        } else {
            $time = strtotime($datetime);
            $result = date($format, $time);
        }

        return $result;
    }

    /**
     * Yes no formatter.
     *
     * @param string $input
     * @return string
     */
    public function printYesNoFormatter($input)
    {
        if (strtolower($input) === 'y') {
            return 'YES';
        }

        return 'NO';
    }

    /**
     * Unlimited/infinity formatter.
     *
     * @param string $input
     * @return string
     */
    public function printUnlimitedFormatter($input)
    {
        if ($input === '0') {
            return 'Unlimited';
        }

        return $input;
    }


    /**
     * Get timezone mall
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * @param string $current_mall
     * @return string
     */

    public function getTimezoneMall($current_mall) 
    {
        // get timezone based on current_mall
        if (! empty($current_mall) ) {
            $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                          ->where('merchants.merchant_id','=', $current_mall)
                          ->first();

            // if timezone not found
            if ( count($timezone) == 0 ) {
                $timezone = null;
            } else {
                $timezone = $timezone->timezone_name; // if timezone found
            }
        } else {
            $timezone = null;
        }

        return $timezone;
    }


    public function printDatePeriod($startDate = null, $endDate = null, $format='d M Y') 
    {
        $datePeriod = null;
        if (! empty($startDate) ) {
            $time = strtotime($startDate);
            $datePeriod = date($format, $time);
        }

        if(! empty($endDate) ) {
            $time = strtotime($endDate);
            $datePeriod = $datePeriod. ' - ' . date($format, $time);
        }

        return $datePeriod;
    }

}