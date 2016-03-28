<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Mall;
use Carbon\Carbon as Carbon;
use MallAPIController;
use Setting;
use Response;
use NewsAPIController;

class PromotionPrinterController extends DataPrinterController
{
	public function getPromotionPrintView()
    {
        $this->preparePDO();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;

        $current_mall = OrbitInput::get('current_mall');
        $timezone = $this->getTimeZone($current_mall);

        // Instantiate the UserAPIController to get the query builder of Users
        $response = NewsAPIController::create('raw')
            ->setReturnBuilder(true)
            ->getSearchNews();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $promotions = $response['builder'];
        $totalRec = $response['count'];

        $this->prepareUnbufferedQuery();

        $sql = $promotions->toSql();
        $binds = $promotions->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        // Filter mode
        $promotionName = OrbitInput::get('news_name_like');
        $tenantName = OrbitInput::get('tenant_name_like');
        $mallName = OrbitInput::get('mall_name_like');
        $etcFrom = OrbitInput::get('etc_from');
        $etcTo = OrbitInput::get('etc_to');
        $status = OrbitInput::get('campaign_status');
        $beginDate = OrbitInput::get('begin_date');
        $endDate = OrbitInput::get('end_date');

        $pageTitle = 'Promotion List';
        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s\n", '', $pageTitle, '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s\n", '', 'Total Promotions', $totalRec, '', '', '');

                // Filtering
                if ($promotionName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Campaign Name', htmlentities($promotionName), '', '', '','');
                }
                if ($tenantName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Tenant Name', htmlentities($tenantName), '', '', '','');
                }
                if ($mallName != '') {
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Mall Name', htmlentities($mallName), '', '', '','');
                }

                if ($etcFrom != '' && $etcTo != ''){
                    $data_template = '%s,%s,%s - %s,%s,%s,%s' . "\n";
                    printf($data_template, '', 'Filter by Estimated Total Cost', str_replace(',', '', $etcFrom), str_replace(',', '', $etcTo), '', '', '','');
                }

                if ($etcFrom != '' && $etcTo == ''){
                    $data_template = '%s,%s,%s,%s,%s,%s' . "\n";
                    printf($data_template, '', 'Filter by Estimated Total Cost (From)', str_replace(',', '', $etcFrom), '', '', '','');
                }

                if ($etcFrom == '' && $etcTo != ''){
                    $data_template = '%s,%s,%s,%s,%s,%s' . "\n";
                    printf($data_template, '', 'Filter by Estimated Total Cost (To)', str_replace(',', '', $etcTo), '', '', '','');
                }

                if ( is_array($status) && count($status) > 0) {
                    $statusString = '';
                    foreach ($status as $key => $valstatus){
                        $statusString .= $valstatus . ', ';
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Filter by Status', htmlentities(rtrim($statusString, ', ')), '', '', '','');
                }

                if ($beginDate != '' && $endDate != ''){
                    $beginDateRangeMallTime = date('d F Y', strtotime($beginDate));
                    $endDateRangeMallTime = date('d F Y', strtotime($endDate));
                    $dateRange = $beginDateRangeMallTime . ' - ' . $endDateRangeMallTime;
                    if ($beginDateRangeMallTime === $endDateRangeMallTime) {
                        $dateRange = $beginDateRangeMallTime;
                    }
                    printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Campaign Date', $dateRange, '', '', '','');
                }

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Promotion Name', 'Start Date & Time', 'End Date & Time', 'Locations', 'Status', 'Last Update');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $startDateTime = date('d F Y H:i', strtotime($row->begin_date));
                    $endDateTime = date('d F Y H:i', strtotime($row->end_date));
                    $lastUpdateDate = $this->printDateTime($row->updated_at, $timezone, 'd F Y H:i:s');

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                        $count, $row->news_name, $startDateTime, $endDateTime, str_replace(', ', "\n", $row->campaign_location_names), $this->printUtf8($row->campaign_status), $lastUpdateDate);
                    $count++;
                }
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-promotion-view.php';
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
}
