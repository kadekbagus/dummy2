<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Mall;
use Carbon\Carbon as Carbon;
use EmployeeAPIController;
use Setting;
use Response;

class EmployeePrinterController extends DataPrinterController
{
    public function getEmployeePrintView()
    {
        $this->preparePDO();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;

        $current_mall = OrbitInput::get('merchant_id');
        $currentDateAndTime = OrbitInput::get('currentDateAndTime');
        $timezone = $this->getTimeZone($current_mall);

        // Filter
        $full_name_like = OrbitInput::get('full_name_like');
        $user_email_like = OrbitInput::get('user_email_like');
        $role_names = OrbitInput::get('role_names');
        $employee_id_char_like = OrbitInput::get('employee_id_char_like');
        $status = OrbitInput::get('status');

        // Instantiate the MallAPIController to get the query builder of Malls
        $response = EmployeeAPIController::create('raw')
                                         ->setReturnBuilder(true)
                                         ->getSearchPMPEmployee();

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

        $pageTitle = 'Employee List';

        // the frontend send the current date and time, because pmp portal doesn't have timezone
        if ( !empty($currentDateAndTime) ) {
            $filename = $this->getFilename(preg_replace("/[\s_]/", "-", $pageTitle), '.csv', $currentDateAndTime);
        } else {
            $filename = OrbitText::exportFilename(preg_replace("/[\s_]/", "-", $pageTitle), '.csv', $timezone);
        }

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename );

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Employee List', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Employees', $totalRec, '', '', '', '','','','','');

                if ($full_name_like != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Employee Name', htmlentities($full_name_like), '', '', '', '','','','');
                }

                if ($user_email_like != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Email Address', htmlentities($user_email_like), '', '', '', '','','','');
                }

                if ( is_array($role_names) && count($role_names) > 0) {
                    $role_names_string = '';
                    foreach ($role_names as $key => $val){
                        $role_names_string .= $val . ', ';
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Role', htmlentities(rtrim($role_names_string, ', ')), '', '', '', '','','','');
                }


                if ($employee_id_char_like != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Employee ID', htmlentities($employee_id_char_like), '', '', '', '','','','');
                }

                if ( is_array($status) && count($status) > 0) {
                    $status_string = '';
                    foreach ($status as $key => $valstatus){
                        $status_string .= $valstatus . ', ';
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Status', htmlentities(rtrim($status_string, ', ')), '', '', '', '','','','');
                }

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Name', 'Email', 'Role', 'Employee ID', 'Status', 'Last Update');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                        '', $this->printUtf8($row->user_firstname . ' ' . $row->user_lastname),
                            $this->printUtf8($row->user_email),
                            $this->printUtf8($row->role_name),
                            $this->printUtf8($row->employee_id_char),
                            $this->printUtf8($row->status),
                            $this->printDateTime($row->updated_at, null, 'd F Y H:i:s')
                       );

                }
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-employee-view.php';
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

    public function getFilename($pageTitle, $ext = ".csv", $currentDateAndTime=null)
    {
        if (empty($currentDateAndTime)) {
            $currentDateAndTime = Carbon::now();
        }
        return 'orbit-export-' . $pageTitle . '-' . Carbon::createFromFormat('Y-m-d H:i:s', $currentDateAndTime)->format('D_d_M_Y_Hi') . $ext;
    }

}
