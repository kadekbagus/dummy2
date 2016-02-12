<?php namespace Report;

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
        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Mall List', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Malls', $totalRec, '', '', '', '','','','','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Mall Name', 'Location', 'Start Date', 'End Date', 'Mall Group', 'Status');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                        '', $this->printUtf8($row->name), 
                            $this->printLocation($row), 
                            $this->printDateTime($row->start_date_activity, 'UTC', 'Y-m-d H:i:s'),
                            $this->printDateTime($row->end_date_activity, 'UTC', 'Y-m-d H:i:s'),
                            $this->printUtf8($row->mall_group_name),
                            $row->status
                       );

                }
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

}
