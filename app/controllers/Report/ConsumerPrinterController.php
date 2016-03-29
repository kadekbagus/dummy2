<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Mall;
use Carbon\Carbon as Carbon;
use UserAPIController;
use Setting;

class ConsumerPrinterController extends DataPrinterController
{
    public function getConsumerPrintView()
    {
        $this->preparePDO();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;

        $current_mall = OrbitInput::get('current_mall');
        $flagMembershipEnable = false;

        $timezone = $this->getTimeZone($current_mall);

        // read from setting table membership is enable or not
        $membershipSetting = Setting::where('setting_name', '=','enable_membership_card')
            ->where('object_id', '=', $current_mall)
            ->first();

        if (!empty($membershipSetting)) {
            if ($membershipSetting->setting_value === 'true') {
                $flagMembershipEnable = true;
            }
        }

        // Instantiate the UserAPIController to get the query builder of Users
        $response = UserAPIController::create('raw')
            ->setReturnBuilder(true)
            ->setDetail(true)
            ->getConsumerListing();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $users = $response['builder'];
        $totalRec = $response['count'];

        $firstVisitBeginDate = Carbon::createFromFormat('Y-m-d H:i:s', \Input::get('first_visit_begin_date'), 'UTC')->setTimezone($timezone)->format('d M Y');
        $firstVisitEndDate = Carbon::createFromFormat('Y-m-d H:i:s', \Input::get('first_visit_end_date'), 'UTC')->setTimezone($timezone)->format('d M Y');

        $firstVisitDates = $firstVisitBeginDate;

        if ($firstVisitEndDate !== $firstVisitBeginDate) {
            $firstVisitDates .= ' - '.$firstVisitEndDate;
        }

        $this->prepareUnbufferedQuery();

        $sql = $users->toSql();
        $binds = $users->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Customer';
        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Customer List', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Customers', $totalRec, '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'First Visit Date', $firstVisitDates, '', '', '', '','','','','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');
                if ($flagMembershipEnable) {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Email', 'Name', 'Gender', 'Mobile Phone', 'First Visit Date & Time', 'Membership Join Date', 'Membership Number', 'Issued Coupon', 'Redeemed Coupon', 'Issued Lucky Draw Numbers', 'Status', 'Last Update Date & Time');
                }
                else {
                    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Email', 'Name', 'Gender', 'Mobile Phone', 'First Visit Date & Time', 'Issued Coupon', 'Redeemed Coupon', 'Issued Lucky Draw Numbers', 'Status', 'Last Update Date & Time');
                }
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $gender = $this->printGender($row);
                    $customerSince = $this->printDateTime($row->first_visit_date, $timezone, 'no');
                    $lastUpdateDate = $this->printDateTime($row->updated_at, $timezone, 'no');
                    $membershipJoinDate = $this->printDateTime($row->join_date, $timezone, 'Y-m-d');

                    if ($flagMembershipEnable) {
                        printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            '', $row->user_email,$this->printUtf8($row->user_firstname) . ' ' . $this->printUtf8($row->user_lastname),
                            $gender, $row->phone, $customerSince, $membershipJoinDate, $row->membership_number,
                            $this->printUtf8($row->total_usable_coupon), $this->printUtf8($row->total_redeemed_coupon), $this->printUtf8($row->total_lucky_draw_number), $this->printUtf8($row->status), $lastUpdateDate);
                    }
                    else {
                        printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            '', $row->user_email,$this->printUtf8($row->user_firstname) . ' ' . $this->printUtf8($row->user_lastname),$gender, $row->phone, $customerSince,
                            $this->printUtf8($row->total_usable_coupon), $this->printUtf8($row->total_redeemed_coupon), $this->printUtf8($row->total_lucky_draw_number), $this->printUtf8($row->status), $lastUpdateDate);
                    }

                }
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-consumer-view.php';
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
     * Print expiration date type friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printAddress($consumer)
    {
        if(!empty($consumer->city) && !empty($consumer->country)){
            $result = $consumer->city.', '.$consumer->country;
        }
        else if(empty($consumer->city) && !empty($consumer->country)){
            $result = $consumer->country;
        }
        else if(!empty($consumer->city) && empty($consumer->country)){
            $result = $consumer->city;
        }
        else if(empty($consumer->city) && empty($consumer->country)){
            $result = '';
        }

        return $this->printUtf8($result);
    }


    /**
     * Print Gender friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printGender($consumer)
    {
        $gender = $consumer->gender;
        $gender = strtolower($gender);
        switch ($gender) {
            case 'm':
                $result = 'Male';
                break;

            case 'f':
                $result = 'Female';
                break;
            default:
                $result = '';
        }

        return $result;
    }

    /**
     * Print Language friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printLanguage($consumer)
    {
        $lang = $consumer->preferred_language;
        $lang = strtolower($lang);
        switch ($lang) {
            case 'en':
                $result = 'English';
                break;

            case 'id':
                $result = 'Indonesian';
                break;
            default:
                $result = $lang;
        }

        return $result;
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
     * Print date friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printMemberSince($consumer)
    {
        if($consumer->membership_since == null || empty($consumer->membership_since) || $consumer->membership_since=="0000-00-00 00:00:00"){
            $result = "";
        }
        else {
            $date = $consumer->membership_since;
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d F Y',$time);
            $result = $newformat;
        }


        return $result;
    }


    /**
     * Print Birthdate friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printBirthDate($consumer)
    {
        if($consumer->birthdate == null || empty($consumer->birthdate)) {
            $result = '';
        }
        else {
            $date = $consumer->birthdate;
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d F Y',$time);
            $result = $newformat;
        }

        return $result;
    }


    /**
     * Print last visit date friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printLastVisitDate($consumer)
    {
        $date = $consumer->last_visit_date;
        if ($consumer->last_visit_date == null || empty($consumer->last_visit_date)) {
            $result = '';
        }
        else {
            $date = explode(' ',$date);
            $time = strtotime($date[0]);
            $newformat = date('d F Y',$time);
            $result = $newformat;
        }

        return $result;
    }

    /**
     * Print Last Spent Amount friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printLastSpentAmount($consumer)
    {
        $retailer = $this->getRetailerInfo();
        $currency = strtolower($retailer->parent->currency);
        if ($currency == 'usd') {
            $result = number_format($consumer->last_spent_amount, 2);
        } else {
            $result = number_format($consumer->last_spent_amount);
        }
        return $result;
    }


    /**
     * Print Occupation friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printOccupation($consumer)
    {
        $occupation = $consumer->occupation;
        switch ($occupation) {
            case 'p':
                $result = 'Part-Time';
                break;

            case 'f':
                $result = 'Full Time Employee';
                break;

            case 'v':
                $result = 'Voluntary';
                break;

            case 'u':
                $result = 'Unemployed';
                break;

            case 'r':
                $result = 'Retired';
                break;

            case 's':
                $result = 'Student';
                break;

            default:
                $result = $occupation;
        }

        return $result;
    }


    /**
     * Print Sector of Activity friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printSectorOfActivity($consumer)
    {
        $sector_of_activity = $consumer->sector_of_activity;
        switch ($sector_of_activity) {
            case 'ma':
                $result = 'Management';
                break;

            case 'bf':
                $result = 'Business and Financial Operations';
                break;

            case 'cm':
                $result = 'Computer and Mathematical';
                break;

            case 'ae':
                $result = 'Architecture and Engineering';
                break;

            case 'lp':
                $result = 'Life, Physical, and Social Science';
                break;

            case 'cs':
                $result = 'Community and Social Service';
                break;

            case 'lg':
                $result = 'Legal';
                break;

            case 'et':
                $result = 'Education, Training, and Library';
                break;

            case 'ad':
                $result = 'Arts, Design, Entertainment, Sports, and Media';
                break;

            case 'hp':
                $result = 'Healthcare Practitioners and Technical';
                break;

            case 'hs':
                $result = 'Healthcare Support';
                break;

            case 'ps':
                $result = 'Protective Service';
                break;

            case 'fp':
                $result = 'Food Preparation and Serving Related';
                break;

            case 'bg':
                $result = 'Building and Grounds Cleaning and Maintenance';
                break;

            case 'pc':
                $result = 'Personal Care and Services';
                break;

            case 'sr':
                $result = 'Sales and Related';
                break;

            case 'oa':
                $result = 'Office and Administrative Support';
                break;

            case 'ff':
                $result = 'Farming, Fishing, and Forestry';
                break;

            case 'ce':
                $result = 'Construction and Extraction';
                break;

            case 'im':
                $result = 'Installation, Maintenance, and Repair';
                break;

            case 'pr':
                $result = 'Production';
                break;

            case 'tm':
                $result = 'Transportation and Material Moving';
                break;

            default:
                $result = $sector_of_activity;
        }

        return $result;
    }


    /**
     * Print Average Annual Income friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printAverageAnnualIncome($consumer)
    {
        $avg_income = $consumer->avg_annual_income1;
        switch ($avg_income) {
            case ($avg_income <= 20000000 ):
                $result = '< 20.000.000';
                break;

            case ($avg_income > 20000000 && $avg_income <= 50000000):
                $result = '20.000.000 - 50.000.000';
                break;

            case ($avg_income > 50000000 && $avg_income <= 100000000):
                $result = '50.000.000 - 100.000.000';
                break;

            case ($avg_income > 100000000 && $avg_income <= 200000000):
                $result = '100.000.000 - 200.000.000';
                break;

            case ($avg_income > 200000000):
                $result = '200.000.000 +++';
                break;

            default:
                $result = $avg_income;
        }

        return $result;
    }


    /**
     * Print Average Shopping friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printAverageShopping($consumer)
    {
        $avg_monthly_spent = $consumer->avg_monthly_spent1;
        switch ($avg_monthly_spent) {
            case ($avg_monthly_spent <= 200000 ):
                $result = '< 200.000';
                break;

            case ($avg_monthly_spent > 200000 && $avg_monthly_spent <= 500000):
                $result = '200.000 - 500.000';
                break;

            case ($avg_monthly_spent > 500000 && $avg_monthly_spent <= 1000000):
                $result = '500.000 - 1.000.000';
                break;

            case ($avg_monthly_spent > 1000000 && $avg_monthly_spent <= 2000000):
                $result = '1.000.000 - 2.000.000';
                break;

            case ($avg_monthly_spent > 2000000 && $avg_monthly_spent <= 5000000):
                $result = '2.000.000 - 5.000.000';
                break;

            case ($avg_monthly_spent > 5000000):
                $result = '5.000.000 +++';
                break;

            default:
                $result = $avg_monthly_spent;
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

}
