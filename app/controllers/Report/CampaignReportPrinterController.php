<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use Activity;
use CampaignReportAPIController;
use Response;
use Mall;
use Carbon\Carbon as Carbon;

class CampaignReportPrinterController extends DataPrinterController
{
    public function getPrintCampaignSummaryReport()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');

        $timezone = $this->getTimezoneMall($current_mall);

        $user = $this->loggedUser;

        // Instantiate the CampaignReportAPIController to get the query builder of Coupons
        $response = CampaignReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getCampaignReportSummary();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        // get total data
        $campaign = $response['builder'];
        $totalRecord = $response['count'];
        $totalPageViews = $response['totalPageViews'];
        $totalPopUpViews = $response['totalPopUpViews'];
        $totalEstimatedCost = $response['totalEstimatedCost'];
        $totalSpending = $response['totalSpending'];

        $pdo = DB::Connection()->getPdo();

        $prepareUnbufferedQuery = $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

        $sql = $campaign->toSql();
        $binds = $campaign->getBindings();

        $statement = $pdo->prepare($sql);
        $statement->execute($binds);

        // Filter mode
        $filter = '';
        $campaignName = OrbitInput::get('campaign_name');
        $campaignType = OrbitInput::get('campaign_type');
        $tenantName = OrbitInput::get('tenant_name');
        $mallName = OrbitInput::get('mall_name');
        $startDate = OrbitInput::get('start_date');
        $endDate = OrbitInput::get('end_date');
        $status = OrbitInput::get('status');

        $pageTitle = 'Campaign Summary Report';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Campaign Summary Report', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Number of Campaigns', $totalRecord, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Page Views', $totalPageViews, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Pop Up Views', $totalPopUpViews, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Estimated Total Cost (IDR)', $totalEstimatedCost, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Spending (IDR)', $totalSpending, '', '', '','');

                // Filtering
                if ($startDate != '' && $endDate != ''){
                    $startDateRangeMallTime = $this->printDateTime($startDate, $timezone, 'd M Y');
                    $endDateRangeMallTime = $this->printDateTime($endDate, $timezone, 'd M Y');
                    $dateRange = $startDateRangeMallTime . ' - ' . $endDateRangeMallTime;
                    if ($startDateRangeMallTime === $endDateRangeMallTime) {
                        $dateRange = $startDateRangeMallTime;
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Campaign Date', $dateRange, '', '', '','');
                }

                if ($campaignName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Campaign Name', htmlentities($campaignName), '', '', '','');
                }

                if ( is_array($campaignType) && count($campaignType) > 0) {
                    $campaignTypeString = '';
                    foreach ($campaignType as $key => $valCampaignType){
                        // Change singular to plural, because in DB campaign_type is singular
                        if ($valCampaignType !== 'news') {
                            $valCampaignType =  $valCampaignType . 's';
                        }
                        $campaignTypeString .= $valCampaignType . ', ';
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Campaign Type', htmlentities(rtrim($campaignTypeString, ', ')), '', '', '','');
                }

                if ($tenantName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Tenant', htmlentities($tenantName), '', '', '','');
                }

                if ($mallName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Mall', htmlentities($mallName), '', '', '','');
                }

                if ( is_array($status) && count($status) > 0) {
                    $statusString = '';
                    foreach ($status as $key => $valstatus){
                        $statusString .= $valstatus . ', ';
                    }
                    echo htmlentities(rtrim($statusString, ', '));


                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Status', htmlentities(rtrim($statusString, ', ')), '', '', '','');
                }

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Campaign Name', 'Campaign Type', 'Tenants', 'Mall', 'Campaign Dates', 'Page Views', 'Pop Up Views', 'Pop Up Clicks', 'Daily Cost (IDR)', 'Estimated Total Cost (IDR)', 'Spending (IDR)', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s - %s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $count,
                            $row->campaign_name,
                            $row->campaign_type,
                            $row->total_tenant,
                            $row->mall_name,
                            date('d M Y', strtotime($row->begin_date)),
                            date('d M Y', strtotime($row->end_date)),
                            $row->page_views,
                            $row->popup_views,
                            $row->popup_clicks,
                            number_format($row->daily, 0),
                            number_format($row->estimated_total, 0),
                            number_format($row->spending, 0),
                            $row->status
                    );
                    $count++;
                }
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-campaign-summary-report-view.php';
        }
    }


    public function getPrintCampaignDetailReport()
    {
        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');

        $timezone = $this->getTimezoneMall($current_mall);

        $user = $this->loggedUser;

        // Instantiate the CampaignReportAPIController to get the query builder of Coupons
        $response = CampaignReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getCampaignReportDetail();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $campaign = $response['builder'];
        $totalCampaign = $response['count'];
        $totalPageViews = $response['totalPageViews'];
        $totalPopupViews = $response['totalPopupViews'];
        $totalPopupClicks = $response['totalPopupClicks'];
        $totalSpending = $response['totalSpending'];
        $campaignName = $response['campaignName'];

        $pdo = DB::Connection()->getPdo();

        $prepareUnbufferedQuery = $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

        $sql = $campaign->toSql();
        $binds = $campaign->getBindings();

        $statement = $pdo->prepare($sql);
        $statement->execute($binds);

        // Filter mode
        $filter = '';
        $tenantName = OrbitInput::get('tenant_name');
        $mallName = OrbitInput::get('mall_name');
        $startDate = OrbitInput::get('start_date');
        $endDate = OrbitInput::get('end_date');

        $pageTitle = 'Campaign Detail Report for ' . $campaignName;

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', $pageTitle, '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Active Campaign Days', $totalCampaign, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Page Views', $totalPageViews, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Pop Up Views', $totalPopupViews, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Pop Up Clicks', $totalPopupClicks, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Spending (IDR)', $totalSpending, '', '', '','');

                // Filtering
                if ($startDate != '' && $endDate != ''){
                    $startDateRangeMallTime = $this->printDateTime($startDate, $timezone, 'd M Y');
                    $endDateRangeMallTime = $this->printDateTime($endDate, $timezone, 'd M Y');
                    $dateRange = $startDateRangeMallTime . ' - ' . $endDateRangeMallTime;
                    if ($startDateRangeMallTime === $endDateRangeMallTime) {
                        $startDateRangeMallTime;
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Campaign Date', $dateRange, '', '', '','');
                }

                if ($tenantName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Tenant', htmlentities($tenantName), '', '', '','');
                }

                if ($mallName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by  Mall', htmlentities($mallName), '', '', '','');
                }

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Date', 'Tenants', 'Mall', 'Unique Users', 'Campaign Page Views', 'Campaign Page View Rate (%)', 'Pop Up Views', 'Pop Up View Rate (%)', 'Pop Up Clicks', 'Pop Up Click Rate (%)', 'Spending (IDR)');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $count,
                            $this->printDateTime($row->campaign_date . '00:00:00', $timezone, 'd M Y'),
                            $row->total_tenant,
                            htmlentities($row->mall_name),
                            $row->unique_users,
                            $row->campaign_pages_views,
                            round($row->campaign_pages_view_rate, 2),
                            $row->popup_views,
                            round($row->popup_view_rate, 2),
                            $row->popup_clicks,
                            round($row->popup_click_rate, 2),
                            number_format($row->spending, 0)
                    );
                    $count++;
                }
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-campaign-detail-report-view.php';
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


    /**
     * Get timezone mall
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * @param string $current_mall
     * @return string
     */

    public function getTimezoneMall($current_mall){
        // get timezone based on current_mall
        if (!empty($current_mall)) {
            $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                          ->where('merchants.merchant_id','=', $current_mall)
                          ->first();

            // if timezone not found
            if (count($timezone)==0) {
                $timezone = null;
            } else {
                $timezone = $timezone->timezone_name; // if timezone found
            }
        } else {
            $timezone = null;
        }

        return $timezone;
    }

}