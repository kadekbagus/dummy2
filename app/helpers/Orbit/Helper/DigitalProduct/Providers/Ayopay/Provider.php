<?php namespace Orbit\Helper\DigitalProduct\Providers\Ayopay;

use Exception;
use Orbit\Helper\DigitalProduct\Providers\BaseProvider;
use Orbit\Helper\DigitalProduct\Providers\Ayopay\API\StatusAPI;
use Orbit\Helper\DigitalProduct\Providers\Ayopay\API\PurchaseAPI;

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

    /**
     *
     */
    public function confirm($params = [])
    {
        throw new Exception("Confirm Purchase not supported by Ayopay.");
    }
}
