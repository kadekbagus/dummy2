<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Carbon\Carbon as Carbon;
use TenantAPIController;
use Setting;
use Response;
use Mall;

class TenantPrinterController extends DataPrinterController
{
    public function getTenantPrintView()
    {
        $this->preparePDO();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;

        $current_mall = OrbitInput::get('current_mall');
        $filterName = OrbitInput::get('name_like');
        $filterCategory = OrbitInput::get('categories_like');
        $filterFloor = OrbitInput::get('floor_like');
        $filterUnit = OrbitInput::get('unit_like');
        $filterStatus = OrbitInput::get('status');

        $timezone = $this->getTimeZone($current_mall);

        // Instantiate the TenantAPIController to get the query builder of Malls
        $response = TenantAPIController::create('raw')
            ->setReturnBuilder(true)
            ->getSearchTenant();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $tenants = $response['builder'];
        $totalRec = $response['count'];

        $this->prepareUnbufferedQuery();

        $sql = $tenants->toSql();
        $binds = $tenants->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Tenant List';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'Tenant List', '', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'Total Tenants', $totalRec, '', '', '', '', '','','','','');

                if ($filterName != '') {
                    printf("%s,%s,\n", 'Filter by Tenant Name', $filterName);
                }

                if ($filterCategory != '') {
                    printf("%s,%s,\n", 'Filter by Tenant Category', $filterCategory);
                }

                if ($filterFloor != '') {
                    printf("%s,%s,\n", 'Filter by Tenant Floor', $filterFloor);
                }

                if ($filterUnit != '') {
                    printf("%s,%s,\n", 'Filter by Tenant Unit', $filterUnit);
                }

                if ($filterStatus != '') {
                    printf("%s,%s,\n", 'Filter by Tenant Status', implode(' ', $filterStatus));
                }

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');

                printf("%s,%s,%s,%s,%s,%s,%s\n", 'Tenant Name', 'Categories', 'Location', 'Status', 'Last Update', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $this->printUtf8($row->name),
                            $this->printUtf8($row->tenant_categories),
                            $this->printUtf8($row->location),
                            $row->status,
                            $this->printDateTime($row->updated_at, $timezone, 'd F Y  H:i:s')
                       );

                }
                exit;
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-tenant-view.php';
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
        return utf8_encode($row->floor." - ".$row->unit);
    }


    public function getFilename($pageTitle, $ext = ".csv", $current_date_and_time=null)
    {
        if (empty($current_date_and_time)) {
            $current_date_and_time = Carbon::now();
        }
        return 'gotomalls-export-' . $pageTitle . '-' . Carbon::createFromFormat('Y-m-d H:i:s', $current_date_and_time)->format('D_d_M_Y_Hi') . $ext;
    }

}
