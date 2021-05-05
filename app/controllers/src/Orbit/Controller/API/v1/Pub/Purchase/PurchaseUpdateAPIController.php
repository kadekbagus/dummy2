<?php namespace Orbit\Controller\API\v1\Pub\Purchase;

use App;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct\UpdatePurchase as DigitalProductUpdatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Request\DigitalProductUpdatePurchaseRequest;

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
    public function postUpdate(DigitalProductUpdatePurchaseRequest $request)
    {
        $httpCode = 200;

        try {
            // $this->enableQueryLog();

            $purchase = App::make('purchase');

            switch ($purchase->getProductType()) {
                case 'coupon':
                    break;

                case 'pulsa':
                case 'data_plan':
                    break;

                case 'digital_product':
                    $purchase = (new DigitalProductUpdatePurchase)->update($request);
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
