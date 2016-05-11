<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use Activity;
use Mall;
use Carbon\Carbon as Carbon;

class DatabaseSimulationPrinterController extends DataPrinterController
{
    public function getDatabaseSimulationPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $now = date('Y-m-d H:i:s');

        // Builder object
        $with = array('user', 'retailer', 'promotion', 'coupon', 'product', 'productVariant', 'children', 'staff');
        // Include other relationship
        OrbitInput::get('with', function($_with) use (&$with) {
            $with = array_merge($with, $_with);
        });

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

        $activities = Activity::with($with)->select('activities.activity_id',
                                                    'activities.activity_name',
                                                    'activities.activity_name_long',
                                                    'activities.activity_type',
                                                    'activities.module_name',
                                                    'activities.user_id',
                                                    'activities.user_email',
                                                    'activities.full_name',
                                                    'activities.group',
                                                    'activities.role',
                                                    'activities.role_id',
                                                    'activities.object_id',
                                                    'activities.object_name',
                                                    'activities.product_id',
                                                    'activities.product_name',
                                                    'activities.coupon_id',
                                                    'activities.coupon_name',
                                                    'activities.promotion_id',
                                                    'activities.promotion_name',
                                                    'activities.event_id',
                                                    'activities.event_name',
                                                    'activities.location_id',
                                                    'activities.location_name',
                                                    'activities.ip_address',
                                                    'activities.user_agent',
                                                    'activities.staff_id',
                                                    'activities.staff_name',
                                                    'activities.metadata_user',
                                                    'activities.metadata_object',
                                                    'activities.metadata_location',
                                                    'activities.metadata_staff',
                                                    'activities.notes',
                                                    'activities.http_method',
                                                    'activities.request_uri',
                                                    'activities.post_data',
                                                    'activities.status',
                                                    'activities.parent_id',
                                                    'activities.response_status',
                                                    'activities.created_at',
                                                    'activities.updated_at',
                                                    'activities.object_display_name',
                                                    DB::Raw("DATE_FORMAT({$prefix}activities.created_at, '%d-%m-%Y %H:%i:%s') as created_at_reverse"),
                                                    'user_details.gender as gender')
                                            ->leftJoin('user_details', 'user_details.user_id', '=', 'activities.user_id')
                                            ->joinRetailer()
                                            ->joinNews()
                                            ->joinPromotionNews()
                                            ->groupBy('activities.activity_id');

        // Filter by ids
        OrbitInput::get('id', function($activityIds) use ($activities) {
            $activities->whereIn('activities.activity_id', $activityIds);
        });

        // Filter by activity type
        OrbitInput::get('activity_types', function($types) use ($activities) {
            $activities->whereIn('activities.activity_type', $types);
        });

        // Filter by activity name
        if (! empty($_GET['activity_names'])) {
            OrbitInput::get('activity_names', function($names) use ($activities) {
                $activities->whereIn('activities.activity_name', $names);
            });
        } else {
            $activities->whereNotNull('activities.activity_name');
        }

        // Filter by activity name long
        OrbitInput::get('activity_name_longs', function($nameLongs) use ($activities) {
            $activities->whereIn('activities.activity_name_long', $nameLongs);
        });

        // Filter by matching activity_name_long pattern
        OrbitInput::get('activity_name_long_like', function($name) use ($activities) {
            $activities->where('activities.activity_name_long', 'like', "%$name%");
        });

        // Filter by module_name
        OrbitInput::get('module_names', function($names) use ($activities) {
            $activities->whereIn('activities.module_name', $names);
        });

        // Filter by matching module_name pattern
        OrbitInput::get('module_name_like', function($name) use ($activities) {
            $activities->where('activities.module_name', 'like', "%{$name}%");
        });

        // Filter by merchant ids
        OrbitInput::get('merchant_ids', function($merchantIds) use ($activities) {
            $activities->whereIn('activities.location_id', $merchantIds);
        });

        // Filter by retailer ids
        // OrbitInput::get('retailer_ids', function($retailerIds) use ($activities) {
        //     $activities->whereIn('activities.location_id', $retailerIds);
        // });

        // Filter by user emails
        OrbitInput::get('user_emails', function($emails) use ($activities) {
            $activities->whereIn('activities.user_email', $emails);
        });

        // Filter by matching user_email pattern
        OrbitInput::get('user_email_like', function($userEmail) use ($activities) {
            $activities->where('activities.user_email', 'like', "%$userEmail%");
        });

        // Filter by gender
        OrbitInput::get('genders', function($genders) use ($activities) {
            $activities->whereIn('user_details.gender', $genders);
        });

        // Filter by groups
        if (! empty($_GET['groups'])) {
            OrbitInput::get('groups', function($groups) use ($activities) {
                $activities->whereIn('activities.group', $groups);
            });
        } else {
            $activities->where(function($q) use ($prefix) {
                $q->whereIn('activities.group', ['mobile-ci', 'pos'])
                  ->orWhere(function($q) {
                        $q->where('activities.activity_name', 'registration_ok')
                          ->where('activities.group', 'cs-portal');
                  })
                  ->orWhere(function($q) use ($prefix) {
                        $q->whereIn('activities.activity_name', ['activation_ok','issue_lucky_draw'])
                          ->whereIn('activities.activity_name_long', ['Customer Activation','Lucky Draw Number Issuance'])
                          ->where('activities.group', 'portal');
                  });
                });
        }

        // Filter by matching group pattern
        OrbitInput::get('group_like', function($group) use ($activities) {
            $activities->where('activities.group', 'like', "%$group%");
        });

        // Filter by role_ids
        OrbitInput::get('role_ids', function($roleIds) use ($activities) {
            $activities->whereIn('activities.role_id', $roleIds);
        });

        // Filter by object ids
        OrbitInput::get('object_ids', function($objectIds) use ($activities) {
            $activities->whereIn('activities.object_id', $objectIds);
        });

        // Filter by object names
        OrbitInput::get('object_names', function($names) use ($activities) {
            $activities->whereIn('activities.object_name', $names);
        });

        // Filter by matching object_name pattern
        OrbitInput::get('object_name_like', function($name) use ($activities) {
            $activities->where('activities.object_name', 'like', "%$name%");
        });

        OrbitInput::get('product_names', function($names) use ($activities) {
            $activities->whereIn('activities.product_name', $names);
        });

        // Filter by matching product_name pattern
        OrbitInput::get('product_name_like', function($name) use ($activities) {
            $activities->where('activities.product_name', 'like', "%$name%");
        });

        OrbitInput::get('promotion_names', function($names) use ($activities) {
            $activities->whereIn('activities.promotion_name', $names);
        });

        // Filter by matching promotion_name pattern
        OrbitInput::get('promotion_name_like', function($name) use ($activities) {
            $activities->where('activities.promotion_name', 'like', "%$name%");
        });

        OrbitInput::get('retailer_names', function($names) use ($activities) {
            $activities->whereIn('merchants.name', $names);
        });

        // Filter by matching retailer_name pattern
        OrbitInput::get('retailer_name_like', function($name) use ($activities) {
            $activities->where('merchants.name', 'like', "%$name%");
        });

        OrbitInput::get('news_names', function($names) use ($activities) {
            $activities->whereIn('news.news_name', $names);
        });

        // Filter by matching news_name pattern
        OrbitInput::get('news_name_like', function($name) use ($activities) {
            $activities->where('news.news_name', 'like', "%$name%");
        });

        OrbitInput::get('promotion_news_names', function($names) use ($activities) {
            $activities->whereIn(DB::raw('promotion_news.news_name'), $names);
        });

        // Filter by matching promotion_news_name pattern
        OrbitInput::get('promotion_news_name_like', function($name) use ($activities) {
            $activities->where(DB::raw('promotion_news.news_name'), 'like', "%$name%");
        });

        OrbitInput::get('coupon_names', function($names) use ($activities) {
            $activities->whereIn('activities.coupon_name', $names);
        });

        // Filter by matching coupon_name pattern
        OrbitInput::get('coupon_name_like', function($name) use ($activities) {
            $activities->where('activities.coupon_name', 'like', "%$name%");
        });

        OrbitInput::get('event_names', function($names) use ($activities) {
            $activities->whereIn('activities.event_name', $names);
        });

        // Filter by matching event_name pattern
        OrbitInput::get('event_name_like', function($name) use ($activities) {
            $activities->where('activities.event_name', 'like', "%$name%");
        });

        // Filter by staff Ids
        OrbitInput::get('staff_ids', function($staff) use ($activities) {
            $activities->whereIn('activities.staff_id', $staff);
        });

        // Filter by matching staff_name pattern
        OrbitInput::get('staff_name_like', function($name) use ($activities) {
            $activities->where('activities.staff_name', 'like', "%$name%");
        });

        // Filter by status
        OrbitInput::get('status', function ($status) use ($activities) {
            $activities->whereIn('activities.status', $status);
        });

        // Filter by status
        OrbitInput::get('ip_address_like', function ($ip) use ($activities) {
            $activities->where('activities.ip_address', 'like', "%{$ip}%");
        });

        OrbitInput::get('user_agent_like', function ($ua) use ($activities) {
            $activities->where('activities.user_agent', 'like', "%{$ua}%");
        });

        OrbitInput::get('full_name_like', function ($name) use ($activities) {
            $activities->where('activities.full_name', 'like', "%{$name}%");
        });

        if (! empty($_GET['response_statuses'])) {
            // Filter by response status
            OrbitInput::get('response_statuses', function ($status) use ($activities) {
                $activities->whereIn('activities.response_status', $status);
            });
        } else {
            $activities->whereIn('activities.response_status', ['OK']);
        }

        // Filter by start date
        OrbitInput::get('start_date', function($_start) use ($activities) {
            $activities->where('activities.created_at', '>=', $_start);
        });

        // Filter by end date
        OrbitInput::get('end_date', function($_end) use ($activities, $user) {
            $activities->where('activities.created_at', '<=', $_end);
        });

        // Only shows activities which belongs to this merchant
        if ($user->isSuperAdmin() !== TRUE) {
            $locationIds = $user->getMyRetailerIds();

            if (empty($locationIds)) {
                // Just to make sure it query the wrong one.
                $locationIds = [-1];
            }

            // Filter by user location id
            //$activities->whereIn('activities.location_id', $locationIds);
        } else {
            // Filter by user ids, Super Admin could filter all
            OrbitInput::get('user_ids', function($userIds) use ($activities) {
                $activities->whereIn('activities.user_id', $userIds);
            });

            // Filter by user location id
            OrbitInput::get('location_ids', function($locationIds) use ($activities) {
                $activities->whereIn('activities.location_id', $locationIds);
            });
        }

        // Clone the query builder which still does not include the take,
        // skip, and order by
        $_activities = clone $activities;

        // Prevent query leak, we select only field which should guarantee to be indexed
        $_activities->select('activities.activity_id');

        // Default sort by
        $sortBy = 'activities.activity_id';
        // Default sort mode
        $sortMode = 'desc';

        OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'id'                => 'activities.activity_id',
                'ip_address'        => 'activities.ip_address',
                'created'           => 'activities.created_at',
                'registered_at'     => 'activities.created_at',
                'email'             => 'activities.user_email',
                'full_name'         => 'activities.full_name',
                'object_name'       => 'activities.object_name',
                'product_name'      => 'activities.product_name',
                'coupon_name'       => 'activities.coupon_name',
                'promotion_name'    => 'activities.promotion_name',
                'news_name'         => 'news.news_name',
                'promotion_news_name' => DB::raw('promotion_news.news_name'),
                'event_name'        => 'activities.event_name',
                'action_name'       => 'activities.activity_name',
                'action_name_long'  => 'activities.activity_name_long',
                'activity_type'     => 'activities.activity_type',
                'staff_name'        => 'activities.staff_name',
                'gender'            => 'user_details.gender',
                'module_name'       => 'activities.module_name',
                'retailer_name'     => 'retailer_name',
            );

            if (array_key_exists($_sortBy, $sortByMapping)) {
                $sortBy = $sortByMapping[$_sortBy];
            }
        });

        OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
            if (strtolower($_sortMode) !== 'desc') {
                $sortMode = 'asc';
            }
        });
        $activities->orderBy($sortBy, $sortMode);

        $totalRec = RecordCounter::create($_activities)->count();

        $this->prepareUnbufferedQuery();

        $sql = $activities->toSql();
        $binds = $activities->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'CRM Data';
        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'CRM Data', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total CRM Data', $totalRec, '', '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Customer', 'Gender', 'Date & Time', 'Action', 'Tenant', 'News', 'Promotions', 'Coupons', 'Lucky Draws');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $gender = $this->printGender($row);
                    $date = $this->printDateTime($row, $timezone, 'no');
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n", $count, $row->user_email, $gender, $date, $row->activity_name_long, $this->printUtf8($row->retailer_name), $this->printUtf8($row->news_name), $this->printUtf8($row->promotion_news_name), $this->printUtf8($row->coupon_name), $this->printUtf8($row->object_display_name));
                    $count++;

                }
                exit;
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-databasesimulation-view.php';
        }
    }


    /**
     * Print gender friendly name.
     *
     * @param $databasesimulation $databasesimulation
     * @return string
     */
    public function printGender($databasesimulation)
    {
        $gender = $databasesimulation->gender;
        $gender = strtolower($gender);
        switch ($gender) {
            case 'm':
                $result = 'male';
                break;

            case 'f':
                $result = 'female';
                break;
            default:
                $result = '';
        }

        return $result;
    }

    /**
     * Print date and time friendly name.
     *
     * @param $databasesimulation $databasesimulation
     * @return string
     */
    public function printDateTime($databasesimulation, $timezone, $format='yes')
    {
        if ($databasesimulation->created_at==NULL || empty($databasesimulation->created_at)) {
            $result = "";
        }
        else {
                // change to correct timezone
                if (!empty($timezone) || $timezone != null) {
                    $date = Carbon::createFromFormat('Y-m-d H:i:s', $databasesimulation->created_at, 'UTC');
                    $date->setTimezone($timezone);
                    $_date = $date;
                } 
                else {
                    $_date = $databasesimulation->created_at;
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
