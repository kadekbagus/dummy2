<?php namespace Orbit\Helper\DigitalProduct\Providers\Ayopay;

use Orbit\Helper\DigitalProduct\Providers\Ayopay\API\PurchaseAPI;
use Orbit\Helper\DigitalProduct\Providers\Ayopay\API\StatusAPI;
use Orbit\Helper\DigitalProduct\Providers\BaseProvider;

/**
 * Purchase Provider for AyoPay.
 *
 * @author Budi <budi@gotomalls.com>
 */
class Provider extends BaseProvider
{
    /**
     * Purchase the product from provider.
     *
     * @param  array  $purchaseData [description]
     * @return [type]               [description]
     */
    public function purchase($purchaseData = [])
    {
        return PurchaseAPI::create($purchaseData)->run();
    }

    /**
     * Get purchase status from provider.
     *
     * @param  array  $requestParam [description]
     * @return [type]               [description]
     */
    public function status($requestParam = [])
    {
        return StatusAPI::create($requestParam)->run();
    }
}
