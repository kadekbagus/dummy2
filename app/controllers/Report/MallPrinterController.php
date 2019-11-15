<?php namespace Report;

/**
 * Deprecated
 */

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Mall;
use Carbon\Carbon as Carbon;
use MallAPIController;
use Setting;
use Response;

class MallPrinterController extends DataPrinterController
{
    public function getMallPrintView()
    {
        $this->preparePDO();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;

        $current_mall = OrbitInput::get('current_mall');
        $current_date_and_time = OrbitInput::get('currentDateAndTime');

        $timezone = $this->getTimeZone($current_mall);

        // Instantiate the MallAPIController to get the query builder of Malls
        $response = MallAPIController::create('raw')
            ->setReturnBuilder(true)
            ->getSearchMall();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $malls = $response['builder'];
        $totalRec = $response['count'];

        $this->prepareUnbufferedQuery();

        $sql = $malls->toSql();
        $binds = $malls->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Mall List';

        // the frontend send the current date and time, because admin doesn't have timezone
        if ( !empty($current_date_and_time) ) {
            $filename = $this->getFilename(preg_replace("/[\s_]/", "-", $pageTitle), '.csv', $current_date_and_time);
        } else {
            $filename = OrbitText::exportFilename(preg_replace("/[\s_]/", "-", $pageTitle), '.csv', $timezone);
        }

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename );

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Mall List', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Malls', $totalRec, '', '', '', '','','','','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '','','','','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Mall Name', 'Subscription', 'Location', 'Start Date', 'End Date', 'Mall Group', 'Status');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '','','','','');

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                        '', $this->printUtf8($row->name),
                            $this->printSubscription($row->is_subscribed),
                            $this->printLocation($row),
                            $this->printDateTime($row->start_date_activity, $timezone, 'd F Y'),
                            $this->printDateTime($row->end_date_activity, $timezone, 'd F Y'),
                            $this->printUtf8($row->mall_group_name),
                            $row->status
                       );

                }
                exit;
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-mall-view.php';
        }
    }

    public function getRetailerInfo()
    {
        try {
            $retailer_id = Config::get('orbit.shop.id');
            $retailer = \Mall::with('parent')->where('merchant_id', $retailer_id)->first();

            return $retailer;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
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
     * output utf8.
     *
     * @param string $input
     * @return string
     */
    public function printUtf8($input)
    {
        return utf8_encode($input);
    }


    /**
     * output subscription.
     *
     * @param string $input
     * @return string
     */
    public function printSubscription($is_subscribed)
    {
        $subscription = 'Not Subscribed';

        if ($is_subscribed === 'Y') {
            $subscription = 'Subscribed';
        }

        return utf8_encode($subscription);
    }


    /**
     * output timezone name.
     *
     * @param string
     * @return string
     */
    public function getTimeZone($currentMall) {
        // get timezone based on current_mall
        if (!empty($currentMall)) {
            $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                ->where('merchants.merchant_id','=', $currentMall)
                ->first();

            // if timezone not found
            if (count($timezone)==0) {
                $timezone = null;
            }
            else {
                $timezone = $timezone->timezone_name; // if timezone found
            }
        }
        else {
            $timezone = null;
        }

        return $timezone;
    }

    /**
     * output location.
     *
     * @param string $input
     * @return string
     */
    public function printLocation($row)
    {
        return utf8_encode($row->city.", ".$row->country);
    }


    public function getFilename($pageTitle, $ext = ".csv", $current_date_and_time=null)
    {
        if (empty($current_date_and_time)) {
            $current_date_and_time = Carbon::now();
        }
        return 'gotomalls-export-' . $pageTitle . '-' . Carbon::createFromFormat('Y-m-d H:i:s', $current_date_and_time)->format('D_d_M_Y_Hi') . $ext;
    }

}
