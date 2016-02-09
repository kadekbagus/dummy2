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
        $tenant = OrbitInput::get('tenant');
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

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Number of campaigns', $totalRecord, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total page views', $totalPageViews, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total pop up views', $totalPopUpViews, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total spending', $totalSpending, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Estimated total cost', $totalEstimatedCost, '', '', '','');

                // Filtering
                if($startDate != '' && $endDate != ''){
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Campaign date', $this->printDateTime($startDate, $timezone, 'd M Y') . ' - ' . $this->printDateTime($endDate, $timezone, 'd M Y'), '', '', '','');
                }

                if ($campaignName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Campaign Name : ', htmlentities($campaignName), '', '', '','');
                } elseif($campaignType != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Campaign Type :', htmlentities($campaignType), '', '', '','');
                } elseif($tenant != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Tenant :', htmlentities($tenant), '', '', '','');
                } elseif($mallName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by  Location : ', htmlentities($mallName), '', '', '','');
                } elseif($status != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Status :', $status, '', '', '','');
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
                            $this->printDateTime($row->begin_date, $timezone, 'd M Y'),
                            $this->printDateTime($row->end_date, $timezone, 'd M Y'),
                            $row->page_views,
                            $row->popup_views,
                            $row->popup_clicks,
                            number_format($row->base_price, 0),
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

        $pdo = DB::Connection()->getPdo();

        $prepareUnbufferedQuery = $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

        $sql = $campaign->toSql();
        $binds = $campaign->getBindings();

        $statement = $pdo->prepare($sql);
        $statement->execute($binds);

        // Filter mode
        $filter = '';
        $tenant = OrbitInput::get('tenant');
        $mallName = OrbitInput::get('mall_name');
        $startDate = OrbitInput::get('start_date');
        $endDate = OrbitInput::get('end_date');

        $pageTitle = 'Campaign Detail Report';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Campaign Detail Report', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Active campaign days', $totalCampaign, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total page views', $totalPageViews, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total pop up views', $totalPopupViews, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total pop up clicks', $totalPopupClicks, '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total spending', $totalSpending, '', '', '','');

                // Filtering
                if ($startDate != '' && $endDate != ''){
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Campaign date', $this->printDateTime($startDate, $timezone, 'd M Y') . ' - ' . $this->printDateTime($endDate, $timezone, 'd M Y'), '', '', '','');
                }

                if ($tenant != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Tenant :', htmlentities($tenant), '', '', '','');
                } elseif($mallName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by  Location : ', htmlentities($mallName), '', '', '','');
                }

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Date', 'Location', 'Unique Users', 'Campaign Page Views', 'Campaign Page View Rate (%)', 'Pop Up Views', 'Pop Up View Rate (%)', 'Pop Up Clicks', 'Pop Up Click Rate (%)', 'Spending (IDR)');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                        printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $count,
                            date('d M Y', strtotime($row->campaign_date)),
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