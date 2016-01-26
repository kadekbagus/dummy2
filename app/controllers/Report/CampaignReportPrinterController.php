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

        $data = $response->data->records;

        // get total data
        $totalRecord = $response->data->total_records;
        $totalPageViews = $response->data->total_page_views;
        $totalPopUpViews = $response->data->total_pop_up_views;
        $totalEstimatedCost = $response->data->total_estimated_cost;
        $totalSpending = $response->data->total_spending;

        // Filter mode
        $filter = '';
        $campaignName = OrbitInput::get('campaign_name');
        $campaignType = OrbitInput::get('campaign_type');
        $tenant = OrbitInput::get('tenant');
        $mallName = OrbitInput::get('mall_name');
        $startDate = OrbitInput::get('start_date');
        $endDate = OrbitInput::get('end_date');
        $status = OrbitInput::get('status');


        $this->prepareUnbufferedQuery();

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

                $no  = 1;
                if ($totalRecord > 0) {
                    foreach ($data as $key => $value) {
                        $base_price_fix = str_replace('.00', '', $value->base_price);
                        $estimated_total_fix = str_replace('.00', '', $value->estimated_total);
                        $spending_fix = str_replace('.00', '', $value->spending);

                        printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s - %s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                $no,
                                $value->campaign_name,
                                $value->campaign_type,
                                $value->total_tenant,
                                $value->mall_name,
                                $this->printDateTime($value->begin_date, $timezone, 'd M Y'),
                                $this->printDateTime($value->end_date, $timezone, 'd M Y'),
                                $value->page_views,
                                $value->popup_views,
                                $value->popup_clicks,
                                $base_price_fix,
                                $estimated_total_fix,
                                $spending_fix,
                                $value->status
                        );
                        $no++;
                    }
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
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $current_mall = OrbitInput::get('current_mall');

        $timezone = $this->getTimezoneMall($current_mall);

        $user = $this->loggedUser;

        // Instantiate the CampaignReportAPIController to get the query builder of Coupons
        $response = CampaignReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getCampaignReportDetail();

        $data = $response->data->records;

        // get total data
        $totalRecord = $response->data->total_records;
        $activeCampaignDays = $response->data->active_campaign_days;
        $totalPageViews = $response->data->total_page_views;
        $totalPopupViews = $response->data->total_popup_views;
        $totalPopupClicks = $response->data->total_popup_clicks;
        $totalSpending = $response->data->total_spending;


        // Filter mode
        $filter = '';
        $tenant = OrbitInput::get('tenant');
        $mallName = OrbitInput::get('mall_name');
        $startDate = OrbitInput::get('start_date');
        $endDate = OrbitInput::get('end_date');


        $this->prepareUnbufferedQuery();

        $pageTitle = 'Campaign Detail Report';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Campaign Detail Report', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Active campaign days', $activeCampaignDays, '', '', '','');
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
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Date', 'Location', 'Unique users', 'Campaign page views', 'Campaign page view rate', 'Pop up views', 'Pop up view rate', 'Pop up clicks', 'Pop up click rate', 'Spending (IDR)');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                $no  = 1;
                if ($totalRecord > 0) {
                    foreach ($data as $key => $value) {
                        $spending_fix = str_replace('.00', '', $value['spending']);

                        printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                $no,
                                $value['campaign_date'],
                                htmlentities($value['mall_name']),
                                $value['unique_users'],
                                $value['campaign_pages_views'],
                                $value['campaign_pages_view_rate'] . ' %',
                                $value['popup_views'],
                                $value['popup_view_rate'] . ' %',
                                $value['popup_clicks'],
                                $value['popup_click_rate'] . ' %',
                                $spending_fix
                        );
                        $no++;
                    }
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