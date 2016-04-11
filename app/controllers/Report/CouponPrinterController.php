<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use Activity;
use CouponAPIController;
use Response;
use Mall;
use Carbon\Carbon as Carbon;

class CouponPrinterController extends DataPrinterController
{
    public function getCouponPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');
        $currentDateAndTime = OrbitInput::get('currentDateAndTime');
        $timezone = $this->getTimezoneMall($current_mall);

        $user = $this->loggedUser;

        // Instantiate the CouponAPIController to get the query builder of Coupons
        $response = CouponAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getSearchCoupon();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        // get total data
        $coupon = $response['builder'];
        $totalRec = $response['count'];

        $pdo = DB::Connection()->getPdo();

        $prepareUnbufferedQuery = $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

        $sql = $coupon->toSql();
        $binds = $coupon->getBindings();

        $statement = $pdo->prepare($sql);
        $statement->execute($binds);

        // Filter mode
        $couponName = OrbitInput::get('promotion_name_like');
        $etcFrom = OrbitInput::get('etc_from');
        $etcTo = OrbitInput::get('etc_to');
        $status = OrbitInput::get('campaign_status');
        $beginDate = OrbitInput::get('begin_date');
        $endDate = OrbitInput::get('end_date');
        $ruleType = OrbitInput::get('rule_type');
        $tenantName = OrbitInput::get('tenant_name_like');
        $mallName = OrbitInput::get('mall_name_like');

        $pageTitle = 'Coupon List';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $this->getFilename(preg_replace("/[\s_]/", "-", $pageTitle), '.csv', null) );

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Coupon List', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Coupons', round($totalRec), '', '', '','');

                // Filtering
                if ($couponName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Coupon Name', htmlentities($couponName), '', '', '','');
                }

                if ($tenantName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Tenant Name', htmlentities($tenantName), '', '', '','');
                }
                
                if ($mallName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Mall Name', htmlentities($mallName), '', '', '','');
                }

                if ( is_array($ruleType) && count($ruleType) > 0) {
                    $rule_type_string = '';
                    foreach ($ruleType as $key => $valrule){
                        $rule_type = $valrule;
                        if ($rule_type === 'auto_issue_on_first_signin') {
                            $rule_type = 'coupon blast upon first sign in';
                        } elseif ($rule_type === 'auto_issue_on_signup') {
                            $rule_type = 'coupon blast upon sign up';
                        } elseif ($rule_type === 'auto_issue_on_every_signin') {
                            $rule_type = 'coupon blast upon every sign in';
                        } elseif ($rule_type === 'manual') {
                            $rule_type = 'manual issued';
                        }

                        $rule_type_string .= $rule_type . ', ';
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Coupon Rule', htmlentities(rtrim($rule_type_string, ', ')), '', '', '','');
                }

                if ($etcFrom != '' && $etcTo != ''){
                    printf("%s,%s,%s - %s,%s,%s,%s\n", '', 'Filter by Estimated Total Cost', str_replace(',', '', $etcFrom),  str_replace(',', '', $etcTo), '', '', '','');
                }

                if ($etcFrom != '' && $etcTo == ''){
                    printf("%s,%s,%s,%s,%s,%s\n", '', 'Filter by Estimated Total Cost (From)', str_replace(',', '', $etcFrom), '', '', '','');
                }

                if ($etcFrom == '' && $etcTo != ''){
                    printf("%s,%s,%s,%s,%s,%s\n", '', 'Filter by Estimated Total Cost (To)',  str_replace(',', '', $etcTo), '', '', '','');
                }

                if ( is_array($status) && count($status) > 0) {
                    $statusString = '';
                    foreach ($status as $key => $valstatus){
                        $statusString .= ucwords($valstatus) . ', ';
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Status', htmlentities(rtrim($statusString, ', ')), '', '', '','');
                }

                if ($beginDate != '' && $endDate != ''){
                    $beginDateRangeMallTime = date('d F Y', strtotime($beginDate));
                    $endDateRangeMallTime = date('d F Y', strtotime($endDate));
                    $dateRange = $beginDateRangeMallTime . ' - ' . $endDateRangeMallTime;
                    if ($beginDateRangeMallTime === $endDateRangeMallTime) {
                        $dateRange = $beginDateRangeMallTime;
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Campaign Date', $dateRange, '', '', '','');
                }

                printf("%s,%s,%s,%s,%s,%s,%s,\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,\n", 'No', 'Coupon Name', 'Start Date & Time', 'End Date & Time', 'Location(s)', 'Status', 'Last Update');
                printf("%s,%s,%s,%s,%s,%s,%s,\n", '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $count,
                            $row->name_english,
                            date('d F Y H:i', strtotime($row->begin_date)),
                            date('d F Y H:i', strtotime($row->end_date)),
                            str_replace(', ', "\n", $row->campaign_location_names),
                            $row->campaign_status,
                            $this->printDateTime($row->updated_at, null, 'd F Y H:i:s')
                    );
                    $count++;
                }
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-coupon-view.php';
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

        return (is_null($timezone) ? $result . ' (UTC)' : $result);
    }

    /**
     * Get timezone mall
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * @param string $current_mall
     * @return string
     */

    public function getTimezoneMall($current_mall){
        // get timezone based on current_mall
        if (!empty($current_mall)) {
            $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                          ->where('merchants.merchant_id','=', $current_mall)
                          ->first();

            // if timezone not found
            if (count($timezone)==0) {
                $timezone = null;
            } else {
                $timezone = $timezone->timezone_name; // if timezone found
            }
        } else {
            $timezone = null;
        }

        return $timezone;
    }

    public function getFilename($pageTitle, $ext = ".csv", $currentDateAndTime=null)
    {
        $utc = '';
        if (empty($currentDateAndTime)) {
            $currentDateAndTime = Carbon::now();
            $utc = '_UTC';
        }
        return 'orbit-export-' . $pageTitle . '-' . Carbon::createFromFormat('Y-m-d H:i:s', $currentDateAndTime)->format('D_d_M_Y_Hi') . $utc . $ext;
    }
}