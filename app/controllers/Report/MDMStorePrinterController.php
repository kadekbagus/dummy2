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
use Orbit\Controller\API\v1\Merchant\Store\StoreListAPIController;

class MDMStorePrinterController extends DataPrinterController
{
    public function getPrintMDMStoreReport()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');
        $currentDateAndTime = OrbitInput::get('currentDateAndTime');
        $timezone = $this->getTimezoneMall($current_mall);

        $user = $this->loggedUser;

        // Instantiate the CouponAPIController to get the query builder of Coupons
        $response = StoreListAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getSearchStore();

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

        $pageTitle = 'MDM Store List';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $this->getFilename(preg_replace("/[\s_]/", "-", $pageTitle), '.csv', null) );

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'MDM Store List', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Store', round($totalRec), '', '', '','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,\n", '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,\n", 'No', 'Merchant', 'Country', 'Location', 'Floor', 'Unit', 'Phone', 'Verification Number', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,\n", '', '', '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $count,
                            $row->merchant,
                            $row->country_name,
                            $row->location,
                            $row->floor,
                            $row->unit,
                            $row->phone,
                            $row->verification_number,
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