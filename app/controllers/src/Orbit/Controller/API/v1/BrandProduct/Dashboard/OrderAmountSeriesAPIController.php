<?php

namespace Orbit\Controller\API\v1\BrandProduct\Dashboard;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use stdclass;
use PaymentTransaction;
use Order;
use Exception;
use App;
use Carbon\Carbon;
use DB;

class OrderAmountSeriesAPIController extends ControllerAPI
{

    /**
     * Get time series data of order amount
     *
     * @author ahmad <ahmad@gotomalls.com>
     */
    public function get()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;
            $userType = $user->user_type;

            $stores = $user->stores()->get();
            $merchantIds = [];

            foreach ($stores as $store) {
                $merchantIds[] = $store->merchant_id;
            }
            // @todo: Cache the result based on the brand and/or merchant ids
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now();

            // @todo: Cache the result based on the brand and/or merchant ids
            $start2 = Carbon::now()->startOfMonth()->subHours(7);
            $end2 = Carbon::now()->subHours(7);

            $tablePrefix = DB::getTablePrefix();
            $quote = function($arg)
            {
                return DB::connection()->getPdo()->quote($arg);
            };

            $merchantsIdQuery = '';

            if ($userType === 'store') {
                $merchantsIdQuery = "and merchant_id in ({$quote(implode("','", $merchantIds))})";
            }

            // @todo: add filter to select all brands if user_type is GTM Admin
            $orders = DB::select(DB::raw("
                    SELECT
                        sequences.ts AS label, COALESCE(sum(datas.total), 0) AS total_daily_amount
                    FROM
                        (SELECT
                            DATE_FORMAT(DATE_ADD(CONVERT_TZ({$quote($start)}, '+00:00', '+07:00'), INTERVAL {$tablePrefix}sequence.sequence_number - 1 DAY), '%m/%d/%Y') AS ts
                        FROM
                            {$tablePrefix}sequence
                        WHERE
                            DATE_ADD({$quote($start)}, INTERVAL {$tablePrefix}sequence.sequence_number - 1 DAY) <= {$quote($end)}) sequences
                    LEFT JOIN
                        (
                            select DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '+07:00'), '%m/%d/%Y') as dtx, sum(total_amount) as total from {$tablePrefix}orders
                            where
                            status = 'done'
                            and created_at between {$quote($start2)} and {$quote($end2)}
                            and brand_id = {$quote($brandId)}
                            {$merchantsIdQuery}
                            group by dtx
                        ) as datas
                        ON datas.dtx = sequences.ts
                    group by sequences.ts
                "));

            $this->response->data = $orders;
        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
