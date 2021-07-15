<?php namespace Orbit\Controller\API\v1\Pub\Purchase;

use App;
use DB;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct\CreatePurchase as DigitalProductCreatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Order\CreatePurchase as OrderProductPurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Request\PurchaseRequestBuilder;

/**
 * Handle new purchase request.
 *
 * @todo  Move the 'create purchase handler' into a builder/factory class.
 * @todo  Support other (older) type of product: coupon and pulsa/data plan.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseNewAPIController extends PubControllerAPI
{
    public function postNew()
    {
        try {
            $this->enableQueryLog();

            // Use request builder to determine which validation rules should be used
            // depends on the object_type in the request.
            $request = (new PurchaseRequestBuilder())->build();

            // Then create a new Purchase
            switch ($request->object_type) {
                case 'digital_product':
                    $this->response->data = (new DigitalProductCreatePurchase())
                        ->build($request);
                    break;

                case 'order':
                    $this->response->data = (new OrderProductPurchase())
                        ->create($request);
                    break;

                // other type goes here...

                default:
                    throw new Exception('Unknown product type.');
                    break;
            }

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
