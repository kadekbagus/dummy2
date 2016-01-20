<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use Activity;
use CampaignReportAPIController;
use Response;
use Mall;
use Carbon\Carbon as Carbon;

class CouponReportPrinterController extends DataPrinterController
{
    public function getCampaignSummaryReportPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $couponName = OrbitInput::get('coupon_name', 'Coupon Name');
        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');
        $redeemed_by = OrbitInput::get('redeemed_by');

        $timezoneCurrentMall = $this->getTimezoneMall($current_mall);

        $user = $this->loggedUser;
dd($user);
        // Instantiate the CampaignReportAPIController to get the query builder of Coupons
        $response = CampaignReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getCampaignReportSummary();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $coupons = $response['builder'];
        $totalCoupons = $response['count'];

        $this->prepareUnbufferedQuery();

        $sql = $coupons->toSql();
        $binds = $coupons->getBindings();

        $statement = $this->pdo->prepare($sql);
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
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-coupon-report-view.php';
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

}