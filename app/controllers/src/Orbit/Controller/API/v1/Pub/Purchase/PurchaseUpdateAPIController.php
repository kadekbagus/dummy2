<?php namespace Orbit\Controller\API\v1\Pub\Purchase;

use App;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct\UpdatePurchase as DigitalProductUpdatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Order\UpdatePurchase as UpdateOrderPurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Request\DigitalProductUpdatePurchaseRequest as UpdatePurchaseRequest;

/**
 * Handle purchase update request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseUpdateAPIController extends PubControllerAPI
{
    /**
     * Handle purchase update request.
     *
     * @return [type] [description]
     */
    public function postUpdate()
    {
        try {
            // $this->enableQueryLog();

            $request = new UpdatePurchaseRequest();

            switch (App::make('purchase')->getProductType()) {
                case 'digital_product':
                    $this->response->data = (new DigitalProductUpdatePurchase)
                        ->update($request);
                    break;

                case 'order':
                    $this->response->data = (new UpdateOrderPurchase)
                        ->update($request);
                    break;

                case 'bill':
                    $this->response->data = (new UpdateBillPurchase)
                        ->update($request);

                default:
                    throw new Exception('Unknown product type.');
                    break;
            }

        } catch(Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
