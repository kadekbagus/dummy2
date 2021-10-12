<?php

namespace Orbit\Controller\API\v1\Pub\Order;

use App;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Order\Request\OrderStatusUpdateRequest;
use Order;

/**
 * Order purchase status update.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderStatusUpdateAPIController extends PubControllerAPI
{
    /**
     * Handle order update status.
     *
     * @param  OrderStatusUpdateRequest $request [description]
     * @return [type]                            [description]
     */
    function handle(OrderStatusUpdateRequest $request)
    {
        try {

            $this->beginTransaction();

            App::make('currentOrder')->update([
                'status' => Order::STATUS_PICKED_UP,
            ]);

            $this->commit();

        } catch (Exception $e) {
            return $this->handleException($e);
        }
        return $this->render();
    }
}
