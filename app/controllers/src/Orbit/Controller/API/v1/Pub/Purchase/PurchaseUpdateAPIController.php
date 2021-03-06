<?php namespace Orbit\Controller\API\v1\Pub\Purchase;

use App;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Purchase\Bill\BpjsBillUpdatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Bill\ElectricityBillUpdatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Bill\InternetProviderBillUpdatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Bill\PBBTaxBillUpdatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Bill\UpdatePurchase as BillUpdatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Bill\WaterBillUpdatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct\UpdatePurchase as DigitalProductUpdatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Order\UpdatePurchase as UpdateOrderPurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Request\DigitalProductUpdatePurchaseRequest as UpdatePurchaseRequest;
use Orbit\Helper\MCash\API\Bill;

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
            $purchase = App::make('purchase');

            switch ($purchase->getProductType()) {
                case 'digital_product':
                    $this->response->data = $this->handleDigitalProductUpdate(
                        $purchase,
                        $request
                    );
                    break;

                case 'order':
                    $this->response->data = (new UpdateOrderPurchase)
                        ->update($request);
                    break;

                default:
                    throw new Exception('Unknown product type.');
                    break;
            }

        } catch(Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

    private function handleDigitalProductUpdate($purchase, $request)
    {
        $purchase->load('details.provider_product');

        switch($purchase->getBillProductType()) {
            case Bill::ELECTRICITY_BILL:
                return (new ElectricityBillUpdatePurchase())->update($request);
                break;

            case Bill::PDAM_BILL:
                return (new WaterBillUpdatePurchase())->update($request);
                break;

            case Bill::PBB_TAX_BILL:
                return (new PBBTaxBillUpdatePurchase())->update($request);
                break;

            case Bill::BPJS_BILL:
                return (new BpjsBillUpdatePurchase())->update($request);
                break;

            case Bill::ISP_BILL:
                return (new InternetProviderBillUpdatePurchase())->update($request);
                break;

            default:
                return (new DigitalProductUpdatePurchase)->update($request);
                break;
        }
    }
}
