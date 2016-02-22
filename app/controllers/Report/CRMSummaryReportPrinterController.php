<?php namespace Report;

use Config;
use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Mall;
use DateTime;
use DateTimeZone;
use DateInterval;
use DatePeriod;


class CRMSummaryReportPrinterController extends DataPrinterController
{
    public function getCRMSummaryReportPrintView()
    {
        $this->preparePDO();
        $tablePrefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $flag_7days = false;
        $flag_noconfig = false;

        $current_mall = OrbitInput::get('current_mall');
        $activityGroups = OrbitInput::get('activity_groups');
        $activityGroupSearch = OrbitInput::get('activity_group_search');
        $start_date = OrbitInput::get('start_date');
        $end_date = OrbitInput::get('end_date');

        // check if the days is more than 7 or not
        $_startDate = strtotime($start_date);
        $_endDate = strtotime($end_date);
        $dateDiff = $_startDate - $_endDate;
        $days = abs(floor($dateDiff / (60 * 60 * 24)));

        if ($days > 7) {
            $flag_7days = true;
        }

        if ( $start_date > $end_date ) {
            $flag_7days = true;
        }

        $timezone = $this->getTimezone($current_mall);
        $timezoneOffset = $this->getTimezoneOffset($timezone);

        $begin = new DateTime($start_date, new DateTimeZone('UTC'));
        $endtime = new DateTime($end_date, new DateTimeZone('UTC'));
        $begin->setTimezone(new DateTimeZone($timezone));
        $endtime->setTimezone(new DateTimeZone($timezone));

        // get periode per 1 day
        $interval = DateInterval::createFromDateString('1 day');
        $_dateRange = new DatePeriod($begin, $interval, $endtime);

        $dateRange = [];
        $responses = [];
        $records = [];
        $columns = [];

        foreach ($_dateRange as $date) {
            $dateRange[] = $date->format("Y-m-d");
        }


        if (!$flag_7days) {


            $sql = "select date_format(convert_tz(created_at, '+00:00', ?), '%Y-%m-%d') activity_date, activity_name_long, count(activity_id) as `count`
                    from {$tablePrefix}activities
                    -- filter by date
                    where (`group` = 'mobile-ci'
                        or (`group` = 'portal' and activity_type in ('activation','create'))
                        or (`group` = 'cs-portal' and activity_type in ('registration')))
                        {{where:longActivityName}}
                        and response_status = 'OK' and location_id = ?
                        and created_at between ? and ?
                    group by 1, 2;";

            // Filter with activity names (activity_name_long)
            $longActivityNameWhere = '';
            $activityValues = [];
            if ($activityGroups) {
                foreach ($activityGroups as $activityGroup) {
                    foreach (Config::get('orbit_activity.groups.'.$activityGroup) as $key) {
                        $activityValues[] = Config::get('orbit.activity_columns.'.$key);
                    }
                }
            }

            if ($activityGroupSearch) {
                $column = Config::get('orbit.activity_columns.'.ucwords($activityGroupSearch));

                if ($column) {
                    $activityValues[] = $column;
                }
            }

            if ($activityValues) {
                $longActivityNameWhere = "AND activity_name_long IN ('".implode("','", $activityValues)."')";
            }
            
            $sql = str_replace('{{where:longActivityName}}', $longActivityNameWhere, $sql);
            $activities = DB::select($sql, array($timezoneOffset, $current_mall, $start_date, $end_date));

            // sel = selected
            $selActivityGroups = $activityGroups;
            if ($selActivityGroups) {
                foreach ($selActivityGroups as $selActivityGroup) {

                    // Retrieve from config
                    $selActivityGroupArray = Config::get('orbit_activity.groups.'.$selActivityGroup);

                    // Not found in config
                    if (!$selActivityGroupArray) {
                        continue;
                    }

                    foreach ($selActivityGroupArray as $key) {
                        $columns = array_merge($columns, [$key => Config::get('orbit.activity_columns.'.$key)]);
                    }
                }
            }

            // e.g. 'Email sign up'
            if ($activityGroupSearch) {
                $activityColumns = Config::get('orbit.activity_columns');
                
                $activityColumnsKeys = array_keys($activityColumns);

                $lowerActivityColumns = array_change_key_case($activityColumns, CASE_LOWER);
                $lowerActivityGroupSearch = strtolower($activityGroupSearch);
                
                $columnKey = array_search($lowerActivityGroupSearch, array_keys($lowerActivityColumns));

                if ($columnKey) {
                    $key = $activityColumnsKeys[$columnKey];
                    $columns = array_merge($columns, [$key => $activityColumns[$key]]);
                }
            }

            if (!($selActivityGroups || $activityGroupSearch)) {
                // get column name from config
                $columns = Config::get('orbit.activity_columns');
            }

            $records['columns'] = $columns;


            foreach ($dateRange as $key => $value) {

                foreach ($activities as $x => $y) {
                    if ($y->activity_date === $value) {

                        $date = [];
                        $date['name'] = $y->activity_name_long;
                        $date['count'] = $y->count;

                        $responses[$value][] = $date;
                    }
                }
            }

            // if there is date that have no data
            $dateRange2 = $dateRange;

            foreach ($responses as $a => $b) {
                $length = count($dateRange);
                for ($i = 0; $i < $length; $i++) {
                    if ($a === $dateRange[$i]) {
                        unset($dateRange2[$i]);
                    }
                }
            }

            foreach ($dateRange2 as $x => $y) {
                $responses[$dateRange2[$x]] = array();
            }

        }

        $activity_columns = $columns;

        if (count($activity_columns) > 0) {
            $columns = [];

            $i = 0;
            foreach ($activity_columns as $key => $value) {
                $colTemp = [];
                $colTemp['order'] = $i;
                $colTemp['value'] = $value;
                $colTemp['label'] = $key;
                array_push($columns, $colTemp);
                $i++;
            }
        }
        else {
            $flag_7days = true;
            $flag_noconfig = true;
        }



        if (!$flag_7days) {

            $dates = [];
            $data = [];
            $i = 0;
            foreach ($responses as $key => $value) {
                $dateTemp = [];
                $dateTemp['order'] = $i;
                $dateTemp['label'] = $key;
                array_push($dates, $dateTemp);

                foreach ($columns as $keyA => $valueA) {
                    $index = $this->in_array_r($value, 'name', $valueA['value']);
                    $data[$i][$valueA['order']] = $index > -1 ? $value[$index]['count'] : 0;
                }

                $i++;
            }

            usort($dates, function ($a, $b) {
                return strtotime($a['label']) - strtotime($b['label']);
            });

        }

        $pageTitle = 'CRM Summary Report';
        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'CRM Summary', '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');

                if (!$flag_noconfig) {
                    printf("Date,");
                    foreach ($columns as $x => $y) {
                        if ($x > 0) {
                            printf(",");
                        }
                        printf("\"%s\"", $y['label']);
                        if (count($columns) - 1 === $x) {
                            printf("\n");
                        }
                    }
                }

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '', '', '');

                if (!$flag_7days) {
                    foreach ($dates as $x => $y) {
                        printf("%s,", $this->printDateTime($y['label'], 'd/m/Y'));
                        foreach ($columns as $i => $j) {
                            if ($i > 0) {
                                printf(",");
                            }
                            printf("%s", $data[$y['order']][$j['order']]);
                            if (count($columns) - 1 === $i ) {
                                printf("\n");
                            }
                        }
                    }
                }

                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-crm-summary-report-view.php';
        }
    }

    public function getTimezone($current_mall)
    {
        $timezone = Mall::leftJoin('timezones', 'timezones.timezone_id', '=', 'merchants.timezone_id')
            ->where('merchants.merchant_id', '=', $current_mall)
            ->first();

        return $timezone->timezone_name;
    }

    public function getTimezoneOffset($timezone)
    {
        $dt = new DateTime('now', new DateTimeZone($timezone));

        return $dt->format('P');
    }

    /**
     * Print date and time friendly name.
     *
     * @param string $datetime
     * @param string $format
     * @return string
     */
    public function printDateTime($datetime, $format='d M Y')
    {
        // format the datetime if needed
        if ($format == 'no') {
            $result = $datetime;
        } else {
            $time = strtotime($datetime);
            $result = date($format, $time);
        }

        return $result;
    }


    public function in_array_r($products, $field, $value)
    {
        foreach($products as $key => $product)
        {
            if ( $product[$field] === $value )
                return $key;
        }
        return false;
    }

    public function printFormatNumber($number)
    {
        return number_format($number, 0,'.','.');
    }

}