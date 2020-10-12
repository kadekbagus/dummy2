<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos;

use Exception;
use Orbit\Helper\DigitalProduct\Providers\BaseProvider;
use Orbit\Helper\DigitalProduct\Providers\Woodoos\API\ConfirmAPI;
use Orbit\Helper\DigitalProduct\Providers\Woodoos\API\StatusAPI;
use Orbit\Helper\DigitalProduct\Providers\Woodoos\API\PurchaseAPI;

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
     * implement confirm purchase.
     */
    public function confirm($params = [])
    {
        return ConfirmAPI::create($params)->run();
    }
}
