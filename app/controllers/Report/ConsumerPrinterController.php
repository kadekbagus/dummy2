<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use User;
use Role;
use Mall;
use Carbon\Carbon as Carbon;

class ConsumerPrinterController extends DataPrinterController
{
    public function getConsumerPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $now = date('Y-m-d H:i:s');

        // Available merchant to query
        $listOfMerchantIds = [];

        // Available retailer to query
        $listOfRetailerIds = [];

        $details = OrbitInput::get('details');
        $current_mall = OrbitInput::get('current_mall');

        // get timezone based on current_mall
        if (!empty($current_mall)) {
            $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                          ->where('merchants.merchant_id','=', $current_mall)
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

        // get user mall_ids
        $listOfMallIds = $user->getUserMallIds($current_mall);

        if (empty($listOfMallIds)) { // invalid mall id
            $filterMallIds = 'and 0';
        } elseif ($listOfMallIds[0] === 1) { // if super admin
            $filterMallIds = '';
        } else { // valid mall id
            $filterMallIds = ' and p.merchant_id in ("' . join('","', $listOfMallIds) . '") ';
        }

        // Builder object
        $users = User::Consumers()
                    ->select('users.*',
                        'merchants.name as merchant_name',
                        'user_details.city as city',
                        'user_details.birthdate as birthdate',
                        'user_details.gender as gender',
                        'user_details.country as country',
                        'user_details.phone as phone',
                        'user_details.last_visit_any_shop as last_visit_date',
                        'user_details.merchant_acquired_date as merchant_acquired_date',
                        'user_details.last_spent_any_shop as last_spent_amount',
                        'user_details.relationship_status as relationship_status',
                        'user_details.number_of_children as number_of_children',
                        'user_details.occupation as occupation',
                        'user_details.sector_of_activity as sector_of_activity',
                        'user_details.last_education_degree as last_education_degree',
                        'user_details.avg_annual_income1 as avg_annual_income1',
                        'user_details.avg_monthly_spent1 as avg_monthly_spent1',
                        'user_details.preferred_language as preferred_language',
                        'activities.created_at as first_visit_date',
                        DB::raw("count({$prefix}tmp_lucky.user_id) as total_lucky_draw_number"),
                        DB::raw("(select count(cp.user_id) from {$prefix}issued_coupons cp
                                    inner join {$prefix}promotions p on cp.promotion_id = p.promotion_id {$filterMallIds}
                                    where cp.status='active' and cp.user_id={$prefix}users.user_id and
                                    current_date() <= date(cp.expired_date)) as total_usable_coupon,
                                    (select count(cp2.user_id) from {$prefix}issued_coupons cp2
                                    inner join {$prefix}promotions p on cp2.promotion_id = p.promotion_id {$filterMallIds}
                                    where cp2.status='redeemed' and cp2.user_id={$prefix}users.user_id) as total_redeemed_coupon"))

                    ->join('user_details', 'user_details.user_id', '=', 'users.user_id')
                    ->leftJoin(
                                    // Table
                                    DB::raw("(select ldn.user_id from `{$prefix}lucky_draw_numbers` ldn
                                             join {$prefix}lucky_draws ld on ld.lucky_draw_id=ldn.lucky_draw_id
                                             where ldn.status='active' and ld.status='active'
                                             and (ldn.user_id is not null and ldn.user_id != 0))
                                             {$prefix}tmp_lucky"),
                                    // ON
                                    'tmp_lucky.user_id', '=', 'users.user_id')
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'user_details.last_visit_shop_id')
                    ->leftJoin('user_personal_interest', 'user_personal_interest.user_id', '=', 'users.user_id')
                    ->leftJoin('personal_interests', 'personal_interests.personal_interest_id', '=', 'user_personal_interest.personal_interest_id')
                    ->with(array('userDetail', 'userDetail.lastVisitedShop'))
                    ->excludeDeleted('users')
                    ->groupBy('users.user_id');

        // join to user_acquisitions
        $users->join('user_acquisitions', 'user_acquisitions.user_id', '=', 'users.user_id');

        $current_mall = OrbitInput::get('current_mall');
        
        $users->leftJoin('activities', function($join) use($current_mall) {
                        $join->on('activities.user_id', '=', 'users.user_id')
                             ->where('activities.activity_name', '=', 'login_ok')
                             ->where('activities.role', '=', 'Consumer')
                             ->where('activities.group', '=', 'mobile-ci')
                             ->where('activities.location_id', '=', $current_mall)
                             ;
                    });

        if (empty($listOfMallIds)) { // invalid mall id
            $users->whereRaw('0');
        } elseif ($listOfMallIds[0] === 1) { // if super admin
            // show all users
        } else { // valid mall id
            $users->whereIn('user_acquisitions.acquirer_id', $listOfMallIds);
        }

        // Filter by retailer (shop) ids
        OrbitInput::get('retailer_id', function($retailerIds) use ($users) {
            // $users->retailerIds($retailerIds);
            $listOfRetailerIds = (array)$retailerIds;
        });

        // @To do: Repalce this stupid hacks
        if (! $user->isSuperAdmin()) {
            $listOfRetailerIds = $user->getMyRetailerIds();

            if (empty($listOfRetailerIds)) {
                $listOfRetailerIds = [-1];
            }

            //$users->retailerIds($listOfRetailerIds);
        } else {
            // if (! empty($listOfRetailerIds)) {
            //     $users->retailerIds($listOfRetailerIds);
            // }
        }

        if (! $user->isSuperAdmin()) {
            // filter only by merchant_id, not include retailer_id yet.
            // @TODO: include retailer_id.
            // $users->where(function($query) use($listOfMerchantIds) {
            //     // get users registered in shop.
            //     $query->whereIn('users.user_id', function($query2) use($listOfMerchantIds) {
            //         $query2->select('user_details.user_id')
            //             ->from('user_details')
            //             ->whereIn('user_details.merchant_id', $listOfMerchantIds);
            //     })
            //     // get users have transactions in shop.
            //     ->orWhereIn('users.user_id', function($query3) use($listOfMerchantIds) {
            //         $query3->select('customer_id')
            //             ->from('transactions')
            //             ->whereIn('merchant_id', $listOfMerchantIds)
            //             ->groupBy('customer_id');
            //     });
            // });
        }

        // Filter user by Ids
        OrbitInput::get('user_id', function ($userIds) use ($users) {
            $users->whereIn('users.user_id', $userIds);
        });

        // Filter user by username
        OrbitInput::get('username', function ($username) use ($users) {
            $users->whereIn('users.username', $username);
        });

        // Filter user by matching username pattern
        OrbitInput::get('username_like', function ($username) use ($users) {
            $users->where('users.username', 'like', "%$username%");
        });

        // Filter user by their firstname
        OrbitInput::get('firstname', function ($firstname) use ($users) {
            $users->whereIn('users.user_firstname', $firstname);
        });

        // Filter user by their firstname pattern
        OrbitInput::get('firstname_like', function ($firstname) use ($users) {
            $users->where('users.user_firstname', 'like', "%$firstname%");
        });

        // Filter user by their lastname
        OrbitInput::get('lastname', function ($lastname) use ($users) {
            $users->whereIn('users.user_lastname', $lastname);
        });

        // Filter user by their lastname pattern
        OrbitInput::get('lastname_like', function ($lastname) use ($users) {
            $users->where('users.user_lastname', 'like', "%$lastname%");
        });

        // Filter user by name_like (first_name last_name)
        OrbitInput::get('name_like', function($data) use ($users) {
            $users->where(DB::raw('CONCAT(COALESCE(user_firstname, ""), " ", COALESCE(user_lastname, ""))'), 'like', "%$data%");
        });

        // Filter user by their email
        OrbitInput::get('email', function ($email) use ($users) {
            $users->whereIn('users.user_email', $email);
        });

        // Filter user by their email pattern
        OrbitInput::get('email_like', function ($email) use ($users) {
            $users->where('users.user_email', 'like', "%$email%");
        });

        // Filter user by their status
        OrbitInput::get('status', function ($status) use ($users) {
            $users->whereIn('users.status', $status);
        });

        // Filter user by gender
        OrbitInput::get('gender', function ($gender) use ($users) {
            $users->whereHas('userdetail', function ($q) use ($gender) {
                $q->whereIn('gender', $gender);
            });
        });

        // Filter user by their location('city, country') pattern
        OrbitInput::get('location_like', function ($location) use ($users) {
            $users->whereHas('userdetail', function ($q) use ($location) {
                $q->where(DB::raw('CONCAT(city, ", ", country)'), 'like', "%$location%");
            });
        });

        // Filter user by their city pattern
        OrbitInput::get('city_like', function ($city) use ($users) {
            $users->whereHas('userdetail', function ($q) use ($city) {
                $q->where('city', 'like', "%$city%");
            });
        });

        // Filter user by their last_visit_shop pattern
        OrbitInput::get('last_visit_shop_name_like', function ($shopName) use ($users) {
            $users->whereHas('userdetail', function ($q) use ($shopName) {
                $q->whereHas('lastVisitedShop', function ($q) use ($shopName) {
                    $q->where('name', 'like', "%$shopName%");
                });
            });
        });

        // Filter user by last_visit_begin_date
        OrbitInput::get('last_visit_begin_date', function($begindate) use ($users)
        {
            $users->whereHas('userdetail', function ($q) use ($begindate) {
                $q->where('last_visit_any_shop', '>=', $begindate);
            });
        });

        // Filter user by last_visit_end_date
        OrbitInput::get('last_visit_end_date', function($enddate) use ($users)
        {
            $users->whereHas('userdetail', function ($q) use ($enddate) {
                $q->where('last_visit_any_shop', '<=', $enddate);
            });
        });

        // Filter user by created_at for begin_date
        OrbitInput::get('created_begin_date', function($begindate) use ($users)
        {
            $users->where('users.created_at', '>=', $begindate);
        });

        // Filter user by created_at for end_date
        OrbitInput::get('created_end_date', function($enddate) use ($users)
        {
            $users->where('users.created_at', '<=', $enddate);
        });

        // Filter user by first_visit date begin_date
        OrbitInput::get('first_visit_begin_date', function($begindate) use ($users)
        {
            $users->having('first_visit_date', '>=', $begindate);
        });

        // Filter user by first visit date end_date
        OrbitInput::get('first_visit_end_date', function($enddate) use ($users)
        {
            $users->having('first_visit_date', '<=', $enddate);
        });

        // Clone the query builder which still does not include the take,
        // skip, and order by
        $_users = clone $users;

        // Default sort by
        $sortBy = 'users.created_at';
        // Default sort mode
        $sortMode = 'desc';

        OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'registered_date'         => 'users.created_at',
                'username'                => 'users.username',
                'email'                   => 'users.user_email',
                'lastname'                => 'users.user_lastname',
                'firstname'               => 'users.user_firstname',
                'gender'                  => 'gender',
                'city'                    => 'city',
                'mobile_phone'            => 'user_details.phone',
                'membership_number'       => 'users.membership_number',
                'status'                  => 'users.status',
                'last_visit_shop'         => 'merchants.name',
                'last_visit_date'         => 'user_details.last_visit_any_shop',
                'last_spent_amount'       => 'user_details.last_spent_any_shop',
                'total_usable_coupon'     => 'total_usable_coupon',
                'total_redeemed_coupon'   => 'total_redeemed_coupon',
                'total_lucky_draw_number' => 'total_lucky_draw_number',
                'first_visit_date'        => 'first_visit_date',
            );

            $sortBy = $sortByMapping[$_sortBy];
        });

        OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
            if (strtolower($_sortMode) !== 'desc') {
                $sortMode = 'asc';
            }
        });
        $users->orderBy($sortBy, $sortMode);

        $totalRec = RecordCounter::create($_users)->count();

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

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Customer List', '', '', '', '', '','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Customer', $totalRec, '', '', '', '','','','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Email', 'Name', 'Gender', 'Mobile Phone', 'First Visit Date & Time', 'Issued Coupon', 'Redeemed Coupon', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','');

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $customer_since = $this->printCustomerSince($row, $timezone, 'no');
                    $gender = $this->printGender($row);
                    $address = $this->printAddress($row);
                    $birthdate = $this->printBirthDate($row);
                    $last_visit_date = $this->printLastVisitDate($row);
                    $preferred_language = $this->printLanguage($row);
                    $occupation = $this->printOccupation($row);
                    $sector_of_activity = $this->printSectorOfActivity($row);
                    $avg_annual_income = $this->printAverageAnnualIncome($row);
                    $avg_monthly_spent = $this->printAverageShopping($row);

                    printf("\"%s\",\"%s\",\"%s\", %s,\"%s\", %s,\"%s\",\"%s\",\"%s\"\n",
                        '', $row->user_email,$this->printUtf8($row->user_firstname) . ' ' . $this->printUtf8($row->user_lastname),$gender, $row->phone, $customer_since,
                        $this->printUtf8($row->total_usable_coupon), $this->printUtf8($row->total_redeemed_coupon), $this->printUtf8($row->status));
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
     * Print date friendly name.
     *
     * @param $consumer $consumer
     * @return string
     */
    public function printCustomerSince($consumer, $timezone, $format='yes')
    {
        if ($consumer->first_visit_date==NULL || empty($consumer->first_visit_date) || $consumer->first_visit_date=="0000-00-00 00:00:00") {
            $result = "";
        }
        else {
                // change to correct timezone
                if (!empty($timezone) || $timezone != null) {
                    $date = Carbon::createFromFormat('Y-m-d H:i:s', $consumer->first_visit_date, 'UTC');
                    $date->setTimezone($timezone);
                    $_date = $date;
                } else {
                    $_date = $consumer->first_visit_date;
                }

                // show in format if needed
                if ($format == 'yes') {
                    $date = explode(' ',$_date);
                    $time = strtotime($date[0]);
                    $newformat = date('d F Y',$time);
                    $result = $newformat.' '.$date[1];
                }
                else {
                    $result = $_date;
                }
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
        if($consumer->membership_since==NULL || empty($consumer->membership_since) || $consumer->membership_since=="0000-00-00 00:00:00"){
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
        if($consumer->birthdate==NULL || empty($consumer->birthdate)){
            $result = "";
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
        if($consumer->last_visit_date==NULL || empty($consumer->last_visit_date)){
            $result = "";
        }else {
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
        if($currency=='usd'){
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

}
