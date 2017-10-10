<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use Activity;
use Response;
use Carbon\Carbon as Carbon;
use MerchantTransactionReportAPIController;

class MerchantTransactionPrinterController extends DataPrinterController
{
    public function getPrintMerchantTransaction()
    {

        echo "<pre>";
        print_r('Merchant Transaction Report');
        die();

        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $currentDateAndTime = OrbitInput::get('currentDateAndTime');

        // Filter
        $payment_transaction_id = OrbitInput::get('payment_transaction_id');
        $object_name = OrbitInput::get('object_name');
        $building_id = OrbitInput::get('building_id');
        $status = OrbitInput::get('status');
        $merchant_id = OrbitInput::get('merchant_id');
        $start_date = OrbitInput::get('start_date');
        $end_date = OrbitInput::get('end_date');

        $user = $this->loggedUser;

        // Instantiate the CouponReportAPIController to get the query builder of Coupons
        $response = MerchantTransactionReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getSearchMerchantTransactionReport();

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

        $pageTitle = 'Merchant Transaction Report';

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



                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'Merchant Name','Store ID','Store Name','Store Location (mall)','Coupon Campaign Name','Coupon ID','Coupon Redemption Code','Transaction Date and Time','Gtm Transaction ID','External Transaction ID','Payment Method (ewallet Operator Name OR Normal Redeem)','Transaction Amount (paid by user)','Currency','Transaction Status');



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