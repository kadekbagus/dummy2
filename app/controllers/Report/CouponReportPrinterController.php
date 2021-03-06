<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use Activity;
use CouponReportAPIController;
use Response;
use Mall;
use Carbon\Carbon as Carbon;

class CouponReportPrinterController extends DataPrinterController
{
    public function getPrintCouponReport()
    {
        $reportType = OrbitInput::get('report_type');

        switch ($reportType)
        {
            case 'list-coupon':
                return $this->getPrintCouponSummary();
                break;

            case 'by-coupon-name':
                return $this->getPrintCouponByName();
                break;

            case 'by-tenant':
                return $this->getPrintCouponByTenant();
                break;

            case 'issued-coupon':
                return $this->getPrintIssuedCoupon();
                break;

            default:
                return Response::make('Page Not Found');
                break;
        }
    }

    public function getPrintCouponSummary()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $couponName = OrbitInput::get('coupon_name', 'Coupon Name');
        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');
        $currentDateAndTime = OrbitInput::get('currentDateAndTime');

        $timezone = $this->getTimezoneMall($current_mall);

        // Filter
        $promotion_name = OrbitInput::get('promotion_name');
        $tenant_name = OrbitInput::get('tenant_name');
        $mall_name = OrbitInput::get('mall_name');
        $rule_type = OrbitInput::get('rule_type');
        $status = OrbitInput::get('campaign_status');
        $start_validity_date = OrbitInput::get('start_validity_date');
        $end_validity_date = OrbitInput::get('end_validity_date');

        $user = $this->loggedUser;

        // Instantiate the CouponReportAPIController to get the query builder of Coupons
        $response = CouponReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getCouponReportGeneral();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $coupons = $response['builder'];
        $totalCoupons = $response['count'];
        $totalIssued = $response['total_issued'];
        $totalRedeemed = $response['total_redeemed'];

        $pdo = DB::Connection()->getPdo();

        $prepareUnbufferedQuery = $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

        $sql = $coupons->toSql();
        $binds = $coupons->getBindings();

        $statement = $pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Coupon Summary Report';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $this->getFilename(preg_replace("/[\s_]/", "-", $pageTitle), '.csv', null) );

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Coupon Summary Report', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Coupon Campaigns', number_format($totalCoupons, 0, '', ''), '', '', '', '','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Issued Coupons', number_format($totalIssued, 0, '', ''), '', '', '', '','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Redeemed Coupons', number_format($totalRedeemed, 0, '', ''), '', '', '', '','','','');

                // Filtering
                if ($promotion_name != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Coupon Name', htmlentities($promotion_name), '', '', '', '','','','');
                }

                if ($tenant_name != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Tenant', htmlentities($tenant_name), '', '', '', '','','','');
                }

                if ($mall_name != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Mall Name', htmlentities($mall_name), '', '', '', '','','','');
                }

                if ( is_array($rule_type) && count($rule_type) > 0) {
                    $rule_type_string = '';
                    foreach ($rule_type as $key => $val_rule_type){

                        $rule_type = $val_rule_type;
                        if ($rule_type === 'auto_issue_on_first_signin') {
                            $rule_type = 'Blast upon first sign in';
                        } elseif ($rule_type === 'auto_issue_on_signup') {
                            $rule_type = 'Blast upon sign up';
                        } elseif ($rule_type === 'auto_issue_on_every_signin') {
                            $rule_type = 'Blast upon every sign in';
                        } elseif ($rule_type === 'manual') {
                            $rule_type = 'Manual issued';
                        } elseif ($rule_type === 'blast_via_sms') {
                            $rule_type = 'Blast via SMS';
                        }

                        $rule_type_string .= $rule_type . ', ';
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Coupon Rule', htmlentities(rtrim($rule_type_string, ', ')), '', '', '', '','','','');
                }

                if ( is_array($status) && count($status) > 0) {
                    $status_string = '';
                    foreach ($status as $key => $valstatus){
                        $status_string .= $valstatus . ', ';
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Status', htmlentities(rtrim($status_string, ', ')), '', '', '', '','','','');
                }

                if ($start_validity_date != '' && $end_validity_date != ''){
                    $startDateRangeMallTime = date('d M Y', strtotime($start_validity_date));
                    $endDateRangeMallTime = date('d M Y', strtotime($end_validity_date));
                    $dateRange = $startDateRangeMallTime . ' - ' . $endDateRangeMallTime;
                    if ($startDateRangeMallTime === $endDateRangeMallTime) {
                        $dateRange = $startDateRangeMallTime;
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Validity Date', $dateRange, '', '', '', '','','','');
                }

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Coupon Name', 'Campaign Dates', 'Validity Date', 'Location(s)', 'Coupon Rule', 'Issued (Issued/Available)', 'Redeemed (Redeemed/Issued)','Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $rule_type = $row->rule_type;
                    if ($rule_type === 'auto_issue_on_first_signin') {
                        $rule_type = 'Blast upon first sign in';
                    } elseif ($rule_type === 'auto_issue_on_signup') {
                        $rule_type = 'Blast upon sign up';
                    } elseif ($rule_type === 'auto_issue_on_every_signin') {
                        $rule_type = 'Blast upon every sign in';
                    } elseif ($rule_type === 'manual') {
                        $rule_type = 'Manual issued';
                    } elseif ($rule_type === 'blast_via_sms') {
                        $rule_type = 'Blast via SMS';
                    }

                    printf("\"%s\",\"%s\",\"%s - %s\",\"%s\",\"%s\",\"%s\",\"%s / %s\",\"%s / %s\",\"%s\"\n",
                        $count,
                        $row->promotion_name,
                        date('d M Y', strtotime($row->begin_date)),
                        date('d M Y', strtotime($row->end_date)),
                        date('d M Y', strtotime($row->coupon_validity_in_date)),
                        str_replace(', ', "\n", $row->campaign_location_names),
                        $rule_type,
                        $row->total_issued != 'Unlimited' ? number_format($row->total_issued, 0, '', '') : 'Unlimited',
                        $row->available != 'Unlimited' ? number_format($row->available, 0, '', '') : 'Unlimited',
                        $row->total_redeemed != 'Unlimited' ? number_format($row->total_redeemed, 0, '', '') : 'Unlimited',
                        $row->total_issued != 'Unlimited' ? number_format($row->total_issued, 0, '', '') : 'Unlimited',
                        $row->campaign_status
                    );
                    $count++;
                }
                exit;
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-coupon-summary-report-view.php';
        }
    }

    public function getPrintCouponByName()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $couponName = OrbitInput::get('coupon_name', 'Coupon Name');
        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');
        $redeemed_by = OrbitInput::get('redeemed_by');

        $timezoneCurrentMall = $this->getTimezoneMall($current_mall);

        $user = $this->loggedUser;

        // Instantiate the CouponReportAPIController to get the query builder of Coupons
        $response = CouponReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getCouponReportByCouponName();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $coupons = $response['builder'];
        $totalCoupons = $response['count'];

        $pdo = DB::Connection()->getPdo();

        $prepareUnbufferedQuery = $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

        $sql = $coupons->toSql();
        $binds = $coupons->getBindings();

        $statement = $pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Redeemed Coupon Report for ' . $couponName;

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezoneCurrentMall));

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Redeemed Coupon Report for ' . $couponName, '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Redeemed Coupons', $totalCoupons, '', '', '', '','','','');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Tenant(s)', 'Redeemed/Issued', 'Coupon Code', 'Customer', 'Redeemed Date & Time', 'Verification Number');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                    printf("\"%s\",\"%s\",\"=\"\"%s\"\"\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $count,
                            $row->redeem_retailer_name,
                            '1 / ' . $row->total_issued,
                            $row->issued_coupon_code,
                            $row->user_email,
                            $this->printDateTime($row->redeemed_date, $timezoneCurrentMall, 'no'),
                            $row->redeem_verification_code
                    );
                    $count++;
                }
                exit;
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-coupon-report-view.php';
        }
    }

    public function getPrintCouponByTenant()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $couponName = OrbitInput::get('coupon_name', 'Coupon');
        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');
        $redeemed_by = OrbitInput::get('redeemed_by');

        //Filter
        $couponCode = OrbitInput::get('issued_coupon_code');
        $customerAge = OrbitInput::get('customer_age');
        $redemptionPlace = OrbitInput::get('redemption_place');
        $customerGender = OrbitInput::get('customer_gender');
        $issuedDateGte = OrbitInput::get('issued_date_gte');
        $issuedDateLte = OrbitInput::get('issued_date_lte');
        $redeemedDateGte = OrbitInput::get('redeemed_date_gte');
        $redeemedDateLte = OrbitInput::get('redeemed_date_lte');

        $timezoneCurrentMall = $this->getTimezoneMall($current_mall);

        $user = $this->loggedUser;

        // Instantiate the CouponReportAPIController to get the query builder of Coupons
        $response = CouponReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getCouponReportByTenant();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $coupons = $response['builder'];
        $totalCoupons = $response['count'];
        $totalRecord = $response['total_coupons'];
        $totalAcquiringCustomers = $response['total_acquiring_customers'];
        $totalActiveDays = $response['total_active_days'];
        $totalRedemptionPlace = $response['total_redemption_place'];

        $pdo = DB::Connection()->getPdo();

        $prepareUnbufferedQuery = $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

        $sql = $coupons->toSql();
        $binds = $coupons->getBindings();

        $statement = $pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Coupon Detail Report for ' . $couponName;

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $this->getFilename(preg_replace("/[\s_]/", "-", $pageTitle), '.csv', null) );

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Coupon Detail Report for ' . $couponName, '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Coupons', round($totalCoupons), '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Acquiring Customers', round($totalAcquiringCustomers), '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Active Campaign Days', round($totalActiveDays), '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Redemption Places', round($totalRedemptionPlace), '', '', '', '', '');

                // Filtering
                if ($couponCode != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Coupon Code', $couponCode, '', '', '', '', '');
                }

                if ($customerAge != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Customer Age', $customerAge, '', '', '', '', '');
                }

                if ($customerGender != '') {
                    $gender_string = '';
                    $count = 1;
                    foreach ($customerGender as $key => $valgender){
                        if ($count == 1) {
                            $gender_string .= $valgender ;
                        } else {
                            $gender_string .= ', ' .$valgender;
                        }

                        $count++;
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Customer Gender', $gender_string, '', '', '', '', '');
                }

                if ($redemptionPlace != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Redemption Place', $redemptionPlace, '', '', '', '', '');
                }

                if ($issuedDateGte != '' && $issuedDateLte != ''){
                    $startDate = $this->printDateTime($issuedDateGte, '', 'd M Y');
                    $endDate = $this->printDateTime($issuedDateLte, '', 'd M Y');
                    $dateRange = $startDate . ' - ' . $endDate;
                    if ($startDate === $endDate) {
                        $dateRange = $startDate;
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Issued Date', $dateRange, '', '', '', '','');
                }

                if ($redeemedDateGte != '' && $redeemedDateLte != ''){
                    $startDate = $this->printDateTime($redeemedDateGte, '', 'd M Y');
                    $endDate = $this->printDateTime($redeemedDateLte, '', 'd M Y');
                    $dateRange = $startDate . ' - ' . $endDate;
                    if ($startDate === $endDate) {
                        $dateRange = $startDate;
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Redeemed Date', $dateRange, '', '', '', '', '');
                }

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Coupon Code', 'Customer Age', 'Customer Gender', 'Customer Type', 'Customer Email', 'Issued Date & Time', 'Redeemed Date & Time', 'Redemption Place', 'Coupon Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '');

                $count = 1;

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    if ($row->status === 'active') {
                        $stat = 'issued';
                    } else {
                        $stat = $row->status;
                    }

                    if (empty($row->redeemed_date)) {
                        $dateRedeem = '--';
                    } else {
                        $dateRedeem = $this->printDateTime($row->redeemed_date, '', 'd M Y H:i')  . ' (UTC)';
                    }

                    if (empty($row->redemption_place)) {
                        $place = '--';
                    } else {
                        $place = $row->redemption_place;
                    }

                    if (empty($row->age)) {
                        $userAge = '--';
                    } else {
                        $userAge = $row->age;
                    }

                    if (empty($row->gender)) {
                        $userGender = '--';
                    } else {
                        $userGender = $row->gender;
                    }

                    if (empty($row->user_type)) {
                        $userType = '--';
                    } else {
                        $userType = $row->user_type;
                    }

                    if (empty($row->user_email)) {
                        $userEmail = '--';
                    } else {
                        $userEmail = $row->user_email;
                    }

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $count,
                            $row->issued_coupon_code,
                            $userAge,
                            $userGender,
                            $userType,
                            $userEmail,
                            $this->printDateTime($row->issued_date, '', 'd M Y H:i') . ' (UTC)',
                            $dateRedeem,
                            $place,
                            $stat
                    );
                    $count++;
                }
                exit;
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-coupon-report-by-tenant-view.php';
        }
    }

    public function getPrintIssuedCoupon()
    {

        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');

        $timezoneCurrentMall = $this->getTimezoneMall($current_mall);

        $user = $this->loggedUser;

        // Instantiate the CouponReportAPIController to get the query builder of Coupons
        $response = CouponReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getIssuedCouponReport();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $coupons = $response['builder'];
        $totalCoupons = $response['count'];

        $pdo = DB::Connection()->getPdo();

        $prepareUnbufferedQuery = $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

        $sql = $coupons->toSql();
        $binds = $coupons->getBindings();

        $statement = $pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Issued Coupon Report';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezoneCurrentMall));

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Issued Coupon Report', '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Issued Coupons', $totalCoupons, '', '', '', '','','','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Coupon Name', 'Coupon Dates', 'Auto-Issuance Status', 'Coupon Code', 'Customer', 'Issued Date & Time', 'Issued/Maximum', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                    $beginDate = $this->printDateTime($row->begin_date, $timezoneCurrentMall);
                    $endDate = $this->printDateTime($row->end_date, $timezoneCurrentMall);
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"=\"\"%s\"\"\",\"%s\"\n",
                            $count,
                            $row->promotion_name,
                            $beginDate . ' - ' . $endDate,
                            $this->printYesNoFormatter($row->is_auto_issue_on_signup),
                            $row->issued_coupon_code,
                            $row->user_email,
                            $this->printDateTime($row->issued_date, $timezoneCurrentMall, 'no'),
                            '1 / ' . $this->printUnlimitedFormatter($row->maximum_issued_coupon),
                            $row->status
                    );
                    $count++;
                }
                exit;
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-issued-coupon-report.php';
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
        return 'gotomalls-export-' . $pageTitle . '-' . Carbon::createFromFormat('Y-m-d H:i:s', $currentDateAndTime)->format('D_d_M_Y_Hi') . $utc . $ext;
    }

    /**
     * output utf8.
     *
     * @param string $input
     * @return string
     */
    public function printUtf8($input)
    {
        return utf8_encode($input);
    }
}