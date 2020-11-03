<?php namespace Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct;

use Log;
use Exception;
use Illuminate\Support\Facades\App;
use Orbit\Helper\DigitalProduct\Providers\PurchaseProviderInterface;
use Orbit\Helper\Exception\OrbitCustomException;

trait WoodoosHelper
{
    protected function buildWoodoosParams($purchase)
    {
        $providerProduct = $purchase->getProviderProduct();

        return [
            'trx_id' => $purchase->payment_transaction_id,
            'item_code' => $providerProduct->code,
            'amount' => $providerProduct->price,
            'electric_id' => $this->cleanUpElectricID($purchase->extra_data),
        ];
    }

    private function cleanUpElectricID($electricID = '')
    {
        return str_replace([' ', '-'], '', $electricID);
    }
}
