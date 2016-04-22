<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use Activity;

class CaptivePortalPrinterController extends DataPrinterController
{
    public function getCaptivePortalReportPrintView()
    {
        $mode = OrbitInput::get('export', 'print');
        /** @var \ActivityAPIController $controller */
        $controller = \ActivityAPIController::create('raw');
        $controller->setReturnQuery(true);
        $queries = $controller->getCaptivePortalReport();
        $this->preparePDO();
        $this->prepareUnbufferedQuery();

        $count = $this->pdo->prepare($queries['count_query']);
        $count->execute($queries['count_binds']);
        $totalRec = $count->fetch(PDO::FETCH_NUM)[0];
        $count->closeCursor();
        $statement = $this->pdo->prepare($queries['query']);
        $statement->execute($queries['binds']);

        $pageTitle = 'Orbit Captive Portal User Report';
        switch ($mode) {
            case 'csv':
                $fields = ['user_firstname', 'user_lastname', 'os', 'age', 'gender', 'total_visits', 'sign_up_method', 'user_email', 'first_visit', 'last_visit'];
                $header_template = substr(str_repeat('%s,', count($fields) + 1), 0, -1) . "\n";
                $data_template = '%s,"%s","%s","%s",%s,"%s",%s,"%s","%s","%s","%s"' . "\n";
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle));

                printf($header_template, '', '', '', '', '', '', '', '', '', '', '');
                printf($header_template, '', 'Orbit Captive Portal User Report', '', '', '', '', '', '', '', '', '');
                printf($header_template, '', 'Total Rows', $totalRec, '', '', '', '', '', '', '', '');

                printf($header_template, '', '', '', '', '', '', '', '', '', '', '');
                printf($header_template, '', '"First Name"', '"Last Name"', '"OS / Device"', '"Age"', '"Gender"', '"Visits"', '"Sign Up Method"', '"Email Address"', '"First Visit"', '"Last Visit"');
                printf($header_template, '', '', '', '', '', '', '', '', '', '', '');

                $count = 1;
                $today_year = (int) date("Y");
                $today_date = date("m-d");
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $age = $controller->calculateAge($row->birthdate, $today_date, $today_year);
                    printf($data_template,
                        $count,
                        $this->printUtf8($row->first_name),
                        $this->printUtf8($row->last_name),
                        $this->printOs($row->os),
                        $this->printAge($age),
                        $this->printGender($row->gender),
                        $row->total_visits,
                        ucfirst($row->sign_up_method),
                        $row->email,
                        $row->first_visit,
                        $row->last_visit);
                    $count++;

                }
                exit;
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-captiveportalreport-view.php';
        }

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

    public function printGender($gender)
    {
        if ($gender === null) {
            return 'Unknown';
        }
        $gender = strtolower($gender);
        switch ($gender) {
            case 'm':
                $result = 'Male';
                break;

            case 'f':
                $result = 'Female';
                break;
            default:
                $result = 'Unknown';
        }

        return $result;
    }

    /**
     * @param $age
     * @return string
     */
    protected function printAge($age)
    {
        return $age === null ? '' : $age;
    }

    public function printDate($date)
    {
        if ($date === null) {
            return '';
        }
        $date = explode(' ',$date);
        $time = strtotime($date[0]);
        $new_format = date('d M Y', $time);
        $result = $new_format.' '.$date[1];
        return $result;
    }

    public function printOs($os) {
        switch ($os) {
            case 'android':
                return 'Android';
            case 'ios':
                return 'iOS';
            case 'blackberry':
                return 'BlackBerry';
            case 'windows_phone':
                return 'Windows Phone';
            default:
                return 'Other';
        }
    }


}
