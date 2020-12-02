<?php namespace Orbit\Controller\API\v1\Pub\Purchase;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Purchase\Request\PurchaseAvailabilityRequest;

/**
 * Handle purchase availability request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAvailabilityAPIController extends PubControllerAPI
{
    public function getAvailability(PurchaseAvailabilityRequest $request)
    {
        try {
            // $this->enableQueryLog();

            $limit = Config::get('orbit.coupon_reserved_limit_time', 10);
            $purchase = new \stdClass;
            $purchase->availability = true;
            $purchase->limit_time = Carbon::now()->addMinutes($limit);

            $this->response->data = $purchase;

        } catch(Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
