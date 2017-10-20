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
use Orbit\Controller\API\v1\MerchantTransaction\MerchantTransactionReportAPIController;

class MerchantTransactionPrinterController extends DataPrinterController
{
    public function getPrintMerchantTransaction()
    {
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

        // Instantiate the MerchantTransactionReportAPIController to get the query builder of Coupons
        $response = MerchantTransactionReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getSearchMerchantTransactionReport();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $queryBuilder = $response['builder'];
        $totalRecord = $response['count'];

        $pdo = DB::Connection()->getPdo();

        $prepareUnbufferedQuery = $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

        $sql = $queryBuilder->toSql();
        $binds = $queryBuilder->getBindings();

        $statement = $pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Merchant Transaction Report';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $this->getFilename(preg_replace("/[\s_]/", "-", $pageTitle), '.csv', null) );

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'Base Merchant ID', 'Merchant Name', 'Store ID', 'Store Name', 'Store Location (mall)', 'Coupon Campaign Name', 'Coupon ID', 'Coupon Redemption Code', 'Transaction Date and Time', 'Gtm Transaction ID', 'External Transaction ID', 'Payment Method(ewallet Operator Name OR Normal Redeem)', 'Transaction Amount (paid by user)', 'Currency', 'Transaction Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '','','','','','','','','','','','','','');

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                    printf("\"%s\", \"%s\",\"%s\", \"%s\", \"%s\", \"%s\", \"%s\", \"%s\", \"%s\", \"%s\", \"%s\", \"%s\", \"%s\", \"%s\", \"%s\"\n", $row->merchant_id, $row->merchant_name, $row->store_id, $row->store_at_building, $row->building_name, $row->object_name, $row->object_id, $row->coupon_redemption_code, $row->date_tz, $row->payment_transaction_id, $row->external_payment_transaction_id, $row->payment_method, number_format($row->amount, 2, '.', ','), $row->currency, $row->status);
                }
                exit;
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-merchant-transaction-report-view.php';
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