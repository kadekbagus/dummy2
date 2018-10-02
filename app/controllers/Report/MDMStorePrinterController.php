<?php namespace Report;

use Report\DataPrinterController;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Response;
use Carbon\Carbon as Carbon;
use Orbit\Controller\API\v1\Merchant\Store\StoreListAPIController;

class MDMStorePrinterController extends DataPrinterController
{
    public function getPrintMDMStoreReport()
    {
        try {
            $currentDateAndTime = OrbitInput::get('currentDateAndTime');
            $user = $this->loggedUser;

            // Instantiate the StoreListAPIController to get the query builder of Coupons
            $response = StoreListAPIController::create('raw')
                                                ->setReturnBuilder(TRUE)
                                                ->setUseChunk(TRUE)
                                                ->getSearchStore();

            if (! is_array($response)) {
                return Response::make($response->message);
            }

            // get total data
            $stores = $response['stores'];
            $totalRec = $response['count'];
            $activeStore = $response['active_store'];
            $inactiveStore = $response['inactive_store'];

            $pageTitle = 'MDM Store List';

            @header('Content-Description: File Transfer');
            @header('Content-Type: text/csv');
            @header('Content-Disposition: attachment; filename=' . $this->getFilename(preg_replace("/[\s_]/", "-", $pageTitle), '.csv', null) );

            printf("%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '');
            printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'MDM Store List', '', '', '', '','');
            printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Store', round($totalRec), '', '', '','');
            printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Active Store', round($activeStore), '', '', '','');
            printf("%s,%s,%s,%s,%s,%s,%s\n", '', 'Inactive Store', round($inactiveStore), '', '', '','');

            printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,\n", '', '', '', '', '', '', '', '', '');
            printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,\n", 'No', 'Merchant', 'Country', 'Location', 'Floor', 'Unit', 'Phone', 'Verification Number', 'Status');
            printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,\n", '', '', '', '', '', '', '', '', '');

            $count = 1;
            foreach ($stores as $store) {
                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                        $count,
                        $store->merchant,
                        $store->country_name,
                        $store->location,
                        $store->floor,
                        $store->unit,
                        $store->phone,
                        $store->verification_number,
                        $store->status
                );
                $count++;
            }
            exit;
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function getFilename($pageTitle, $ext = ".csv", $currentDateAndTime=null)
    {
        $utc = '';
        if (empty($currentDateAndTime)) {
            $currentDateAndTime = Carbon::now();
            $utc = '_UTC';
        }
        return 'gotomalls-export-' . $pageTitle . '-' . Carbon::createFromFormat('Y-m-d H:i:s', $currentDateAndTime)->format('D_d_M_Y_Hi') . $utc . $ext;
    }
}