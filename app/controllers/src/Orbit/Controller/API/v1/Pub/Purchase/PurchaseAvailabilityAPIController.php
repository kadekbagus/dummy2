<?php namespace Orbit\Controller\API\v1\Pub\Purchase;

use App;
use DB;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct\CreatePurchase as DigitalProductCreatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Request\PurchaseRequestBuilder;

/**
 * Handle new purchase request.
 *
 * @todo  Move the 'create purchase handler' into a builder/factory class.
 * @todo  Support other (older) type of product: coupon and pulsa/data plan.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAvailabilityAPIController extends PubControllerAPI
{
    public function postNew()
    {
        $httpCode = 200;

        try {
            // $this->enableQueryLog();

            // Use request builder to determine which validation rules should be used
            // depends on the object_type in the request.
            $requestBuilder = new PurchaseAvailabilityRequest();
            with($request = $requestBuilder->build($this))->validate();

            // Then create a new Purchase
            $purchase = null;
            switch ($request->object_type) {
                // case 'pulsa':
                //      'data_plan':
                //     $this->purchase = PulsaProductCreatePurchase::create($request);
                //     break;

                // case 'coupon':
                //     $this->purchase = CouponProductCreatePurchase::create($request);
                //     break;

                case 'digital_product':
                    $purchase = (new DigitalProductCreatePurchase())->build($request);
                    break;

                default:
                    throw new Exception('Unknown product type.');
                    break;
            }

            $this->response->data = $purchase;

        } catch(Exception $e) {
            return $this->handleException($e);
        }

        return $this->render($httpCode);
    }
}
