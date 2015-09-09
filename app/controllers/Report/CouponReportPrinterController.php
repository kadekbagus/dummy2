<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use Activity;
use CouponReportAPIController;
use Response;

class CouponReportPrinterController extends DataPrinterController
{
    public function getPrintCouponReport()
    {
        $reportType = OrbitInput::get('report_type');

        switch ($reportType)
        {
            case 'by-coupon-name':
                return $this->getPrintCouponByName();
                break;

            case 'by-tenant':
                return $this->getPrintCouponByTenant();
                break;

            case 'issued-coupon':
                return $this->getPrintIssuedCoupon();
                break;

            default:
                return Response::make('Page Not Found');
                break;
        }
    }

    public function getPrintCouponByName()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $couponName = OrbitInput::get('coupon_name', 'Coupon Name');
        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;

        // Instantiate the CouponReportAPIController to get the query builder of Coupons
        $response = CouponReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getCouponReportByCouponName();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $coupons = $response['builder'];
        $totalCoupons = $response['count'];

        $this->prepareUnbufferedQuery();

        $sql = $coupons->toSql();
        $binds = $coupons->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Redeemed Coupon Report By ' . $couponName;

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle));

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Redeemed Coupon Report By ' . $couponName, '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Tenant(s)', 'Redeemed/Issued', 'Coupon Code', 'Customer', 'Redeemed Date & Time', 'Tenant Verification Number');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $count,
                            $row->redeem_retailer_name,
                            $row->total_redeemed . ' / ' . $row->total_issued,
                            $row->issued_coupon_code,
                            $row->user_email,
                            $row->redeemed_date,
                            $row->redeem_verification_code
                    );
                    $count++;
                }
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-coupon-report-view.php';
        }
    }

    public function getPrintCouponByTenant()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $tenantName = OrbitInput::get('tenant_name', 'Tenant');
        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;

        // Instantiate the CouponReportAPIController to get the query builder of Coupons
        $response = CouponReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getCouponReportByTenant();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $coupons = $response['builder'];
        $totalCoupons = $response['count'];

        $this->prepareUnbufferedQuery();

        $sql = $coupons->toSql();
        $binds = $coupons->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Redeemed Coupon Report By ' . $tenantName;

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle));

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Redeemed Coupon Report By ' . $tenantName, '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Coupon Name', 'Redeemed/Issued', 'Customer', 'Coupon Code', 'Redeemed Date & Time', 'Tenant Verification Number');
                printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $count,
                            $row->promotion_name,
                            $row->total_redeemed . ' / ' . $row->total_issued,
                            $row->user_email,
                            $row->issued_coupon_code,
                            $row->redeemed_date,
                            $row->redeem_verification_code
                    );
                    $count++;
                }
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-coupon-report-by-tenant-view.php';
        }
    }

    public function getPrintIssuedCoupon()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;

        // Instantiate the CouponReportAPIController to get the query builder of Coupons
        $response = CouponReportAPIController::create('raw')
                                            ->setReturnBuilder(TRUE)
                                            ->getIssuedCouponReport();

        if (! is_array($response)) {
            return Response::make($response->message);
        }

        $coupons = $response['builder'];
        $totalCoupons = $response['count'];

        $this->prepareUnbufferedQuery();

        $sql = $coupons->toSql();
        $binds = $coupons->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        $pageTitle = 'Issued Coupon Report';

        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle));

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Issued Coupon Report', '', '', '', '', '', '', '');

                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", 'No', 'Coupon Name', 'Coupon Dates', 'Auto-Issuance Status', 'Coupon Code', 'Customer', 'Issued Date & Time', 'Issued/Maximum', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '', '', '');

                $count = 1;
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                    $beginDate = $this->printDateTime($row->begin_date);
                    $endDate = $this->printDateTime($row->end_date);
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"=\"\"%s\"\"\",\"%s\"\n",
                            $count,
                            $row->promotion_name,
                            $beginDate . ' - ' . $endDate,
                            $this->printYesNoFormatter($row->is_auto_issue_on_signup),
                            $row->issued_coupon_code,
                            $row->user_email,
                            $this->printDateTime($row->issued_date, 'd M Y H:i'),
                            $row->total_issued . ' / ' . $this->printUnlimitedFormatter($row->maximum_issued_coupon),
                            $row->status
                    );
                    $count++;
                }
                break;

            case 'print':
            default:
                $me = $this;
                $rowCounter = 0;
                require app_path() . '/views/printer/list-issued-coupon-report.php';
        }
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
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
            return 'none';
        }

        $time = strtotime($datetime);
        $result = date($format, $time);

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
            return '∞';
        }

        return $input;
    }
}