<?php namespace Report;
/**
 * Base Intermediate Controller for all controller which need authentication.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use IntermediateAuthBrowserController;
use TenantAPIController;
use View;
use Config;
use Retailer;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class DataPrinterController extends IntermediateAuthBrowserController
{
    /**
     * Store the PDO Object
     *
     * @var PDO
     */
    protected $pdo = NULL;

    /**
     * Get list of tenant in printer friendly view.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    public function getTenantListPrintView()
    {
        $tenants = TenantAPIController::create('raw')->getSearchTenant();

        if ($tenants->code === 0) {
            $this->viewData['pageTitle'] = 'Report List of Tenant';
            $this->viewData['date'] = date('D, d/m/Y');
            $this->viewData['tenants'] = $tenants->data->records;
            $this->viewData['total_tenants'] = $tenants->data->total_records;
            $this->viewData['rowCounter'] = 0;
            $this->viewData['currentRetailer'] = $this->getCurrentRetailer();
            $this->viewData['me'] = $this;

            return View::make('printer/list-tenant-view', $this->viewData);
        }

        if (Config::get('app.debug') === FALSE) {
            return View::make('errors/500', $this->viewData);
        }

        return print_r($tenants, TRUE);
    }

    /**
     * Get list of lucky draw issued in printer friendly view.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    public function getLuckyDrawNumberPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $luckyDrawId = OrbitInput::get('lucky_draw_id', 1);
        $start = 0;
        $take = 250000;
        $keyword = OrbitInput::get('keyword', '');

        // Set page title
        $pageTitle = 'Report - Lucky Draw Number';

        // Get current mall
        $mallId = Config::get('orbit.shop.id', 0);
        $result = $this->pdo->query("SELECT * FROM {$prefix}merchants where merchant_id='$mallId' limit 1");
        if (! $result) {
            return 'Could not find mall configuration.';
        }
        $currentRetailer = $result->fetch(PDO::FETCH_OBJ);

        // Counter for row number in table
        $rowCounter = 0;

        // Keyword for email or membership number
        $whereKeyword = '';
        if (! empty($keyword)) {
            $keyword = $this->pdo->quote($keyword);
            $whereKeyword = "and (u.user_email='$keyword' or u.membership_number='$keyword')";
        }

        $result = $this->pdo->query("select count(*) as total
                                    from {$prefix}lucky_draw_numbers ldn
                                    where ldn.lucky_draw_id='$luckyDrawId'");
        if (! $result) {
            return 'Could not fetch count of lucky draw number data.';
        }

        $totalLuckyDrawNumber = $result->fetch(PDO::FETCH_OBJ);
        $totalLuckyDrawNumber = $totalLuckyDrawNumber->total;

        $result = $this->pdo->query("select count(*) as total
                                    from {$prefix}lucky_draw_numbers ldn
                                    where ldn.lucky_draw_id='$luckyDrawId' and
                                    (ldn.user_id is not null or ldn.user_id != 0)");
        if (! $result) {
            return 'Could not fetch issued count of lucky draw number data.';
        }

        $totalIssuedLuckyDrawNumber = $result->fetch(PDO::FETCH_OBJ);
        $totalIssuedLuckyDrawNumber = $totalIssuedLuckyDrawNumber->total;

        $result = $this->pdo->query("select * from {$prefix}lucky_draws where lucky_draw_id='$luckyDrawId' limit 1");
        if (! $result) {
            return 'Could not fetch lucky draw data.';
        }

        $luckyDraw = $result->fetch(PDO::FETCH_OBJ);

        $this->prepareUnbufferedQuery();
        $result = $this->pdo->query("select ldn.*,
                                    u.user_email, u.membership_number,
                                    u.user_firstname, u.user_lastname
                                    from {$prefix}lucky_draw_numbers ldn
                                    left join {$prefix}users u on u.user_id='ldn.user_id'
                                    where ldn.lucky_draw_id='$luckyDrawId' and
                                    (ldn.user_id is not null or ldn.user_id != 0)
                                    $whereKeyword
                                    group by ldn.lucky_draw_number_id
                                    order by ldn.issued_date desc
                                    limit $start, $take");

        if (! $result) {
            return "NO RESULT";
        }

        $getFullName = function($user, $format='with_email')
        {
            $fullname = trim($user->user_firstname . ' ' . $user->user_lastname);

            // return the full name with email
            switch ($format) {
                case 'no_email':
                    return $fullname;

                case 'with_email':
                default:
                    if (empty($fullname)) {
                        // return only email
                        return $user->user_email;
                    }

                    return sprintf('%s (%s)', $fullname, $user->user_email);
            }
        };

        $formatDate = function($date, $with='noseconds')
        {
            switch ($with) {
                case 'with_seconds':
                    return date('d-M-Y H:i:s', strtotime($date));
                    break;

                case 'csv':
                    return date('d-M-Y H:i:s', strtotime($date));
                    break;

                default:
                    return date('d-M-Y H:i', strtotime($date));
            }
        };

        switch ($mode) {
            case 'csv':
                $filename = 'lucky-draw-number-report-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                // CSV Header
                // ----------
                printf("%s,%s,%s,%s,%s,%s\n\n", '', '', strtoupper($luckyDraw->lucky_draw_name), 'ISSUED LUCKY DRAW NUMBERS REPORT', '', '');

                // Lucky Draw Name
                printf("%s,%s,%s,%s,%s,%s\n", '', 'Lucky Draw Name', $luckyDraw->lucky_draw_name, '', '', '');

                // Period
                $period = sprintf('%s - %s', $formatDate($luckyDraw->start_date), $formatDate($luckyDraw->end_date));
                printf("%s,%s,%s,%s,%s,%s\n", '', 'Period', $period, '', '', '');

                // Total Number
                printf("%s,%s,%s,%s,%s,%s\n", '', 'Total Numbers', $totalLuckyDrawNumber, '', '', '');

                // Total Issued Number
                printf("%s,%s,%s,%s,%s,%s\n", '', 'Total Issued Numbers', $totalIssuedLuckyDrawNumber, '', '', '');

                // Total Number Left
                $totalNumberLeft = $totalLuckyDrawNumber - $totalIssuedLuckyDrawNumber;
                printf("%s,%s,%s,%s,%s,%s\n\n", '', 'Total Unissued Numbers', $totalNumberLeft, '', '', '');

                // CSV Format
                // #, LUCKY DRAW NUMBER, ISSUED DATE, FULL NAME, EMAIL
                printf("NO,LUCKY DRAW NUMBER,ISSUED DATE,FULL NAME,EMAIL\n");
                while ($row = $result->fetch(PDO::FETCH_OBJ)) {
                    printf("%s,%s,%s,%s,%s",
                            ++$rowCounter,
                            $row->lucky_draw_number_code,
                            $formatDate($row->issued_date, 'csv'),
                            $getFullName($row, 'no_email'),
                            $row->user_email
                    );
                }
                exit;
                break;

            case 'print':
            default:
                require app_path() . '/views/printer/list-lucky-draw-number-view.php';
        }
    }

    /**
     * Get list of lucky draw number list grouped by lucky draw number.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    protected function getLuckyDrawNumberListPrintView()
    {
        $this->prepareUnbufferedQuery();
    }

    /**
     * Get current retailer (mall)
     *
     * @author Rio Astamal <me@rioastamla.net>
     * @return Retailer
     */
    public function getCurrentRetailer()
    {
        $current = OrbitInput::get('merchant_id', OrbitInput::get('mall_id'));
        $retailer = Mall::find($current);

        return $retailer;
    }

    /**
     * Method to prepare the PDO Object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    protected function preparePDO()
    {
        $prefix = DB::getTablePrefix();
        $default = Config::get('database.default');
        $dbConfig = Config::get('database.connections.' . $default);

        $this->pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['database']}", $dbConfig['username'], $dbConfig['password']);
    }

    /**
     * Method to prepare the unbuffered queries to the MySQL server. It useful
     * because we want to show all the lists and does not want the result
     * to be stored in application memory.
     *
     * The result should be kept on MySQL server and fetched one-by-one using
     * cursor.
     *
     * Call the method preparePDO first.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    protected function prepareUnbufferedQuery()
    {
        $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);
    }

    /**
     * Concat the list of collection.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Collection|Array $collection
     * @param string $attribute You want to get
     * @param string $separator Separator for concat result
     * @return String
     */
    public function concatCollection($collection, $attribute, $separator=', ')
    {
        $result = [];

        foreach ($collection as $item) {
            $result[] = $item->{$attribute};
        }

        return implode($separator, $result);
    }
}
