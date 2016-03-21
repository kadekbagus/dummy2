<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Text as OrbitText;
use Carbon\Carbon as Carbon;
use LuckyDrawAPIController;
use Setting;
use Response;
use Mall;

class LuckyDrawPrinterController extends DataPrinterController
{
    public function getLuckyDrawPrintView()
    {
        $this->preparePDO();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;

        $currentMall = OrbitInput::get('current_mall');
        $filterName = OrbitInput::get('lucky_draw_name_like');
        $filterMinimumAmountFrom = OrbitInput::get('from_minimum_amount');
        $filterMinimumAmountTo = OrbitInput::get('to_minimum_amount');
        $filterStatus = OrbitInput::get('campaign_status');
        $filterBeginDate = OrbitInput::get('begin_date');
        $filterEndDate = OrbitInput::get('end_date');

        $timezone = $this->getTimeZone($currentMall);

        // Instantiate the LuckyDrawAPIController to get the query builder of Malls
        $response = LuckyDrawAPIController::create('raw')
            ->setReturnBuilder(true)
            ->getSearchLuckyDraw();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $luckyDraws = $response['builder'];
        $totalRec = $response['count'];

        $this->prepareUnbufferedQuery();

        $sql = $luckyDraws->toSql();
        $binds = $luckyDraws->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Lucky Draw List';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle, '.csv', $timezone));

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'Lucky Draw List', '', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'Total Lucky Draws', $totalRec, '', '', '', '', '','','','','');

                if ($filterBeginDate != '') {
                    printf("%s,%s,\n", 'Campaign Date', $this->printCampaignDate($filterBeginDate, $filterEndDate));
                }

                if ($filterName != '') {
                    printf("%s,%s,\n", 'Filter by Lucky Draw Name', $filterName);
                }

                if ($filterMinimumAmountFrom != '') {
                    printf("%s,%s,\n", 'Filter by Minimum Amount From', $filterMinimumAmountFrom);
                }

                if ($filterMinimumAmountTo != '') {
                    printf("%s,%s,\n", 'Filter by Minimum Amount To', $filterMinimumAmountTo);
                }

                if ($filterStatus != '') {
                    printf("%s,%s,\n", 'Filter by Status', $filterStatus);
                }


                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');

                printf("%s,%s,%s,%s,%s,%s,%s\n", 'Lucky Draw Name', 'Start Date & Time', 'End Date & Time', 'Amount to Obtain', 'Total Issued Numbers', 'Status', 'Last Update');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','','','','','');

                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $this->printUtf8($row->lucky_draw_name_english),
                            $this->printDateTime($row->start_date, 'UTC', 'd F Y H:i'),
                            $this->printDateTime($row->end_date, 'UTC', 'd F Y H:i'),
                            $row->minimum_amount,
                            $row->total_issued_lucky_draw_number,
                            $row->campaign_status,
                            $this->printDateTime($row->updated_at, $timezone, 'd F Y H:i:s')
                       );

                }

                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-lucky-draw-view.php';
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

    public function printCampaignDate($filterBeginDate, $filterEndDate)
    {
        if (!empty($filterBeginDate) && !empty($filterEndDate))
        {
            return $this->printDateTime($filterBeginDate, 'UTC').' - '.$this->printDateTime($filterEndDate, 'UTC');
        }
        else if (! empty($filterBeginDate))
        {
            return $this->printDateTime($filterBeginDate, 'UTC');
        }

    }

}
