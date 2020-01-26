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
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseNewAPIController extends PubControllerAPI
{
    public function postNewPurchase()
    {
        $httpCode = 200;

        try {
            // $this->enableQueryLog();

            // Use request builder to determine which validation rules should be used
            // depends on the object_type in the request.
            $requestBuilder = new PurchaseRequestBuilder();
            with($request = $requestBuilder->build($this))->validate();

            // Then create a new Purchase
            DB::beginTransaction();

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
                    $purchase = DigitalProductCreatePurchase::create($request);
                    break;

                default:
                    throw new Exception('Unknown product type.');
                    break;
            }

            DB::commit();

            $this->response->data = $purchase;

            // Record activity...
            // App::make('currentUser')->activity(new TransactionStartingActivity($purchaseRepository->getPurchase()));

        } catch(Exception $e) {
            return $this->handleException($e);
        }

        return $this->render($httpCode);
    }
}
