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
            'trx_id' => time(),
            'item_code' => $providerProduct->code,
            'amount' => $purchase->amount,
            'electric_id' => $purchase->extra_data,
        ];
    }
}
