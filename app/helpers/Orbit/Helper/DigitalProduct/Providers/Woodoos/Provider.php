<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos;

use Exception;
use Orbit\Helper\DigitalProduct\Providers\BaseProvider;
use Orbit\Helper\DigitalProduct\Providers\Woodoos\API\PurchaseAPI;
use Orbit\Helper\DigitalProduct\Providers\Woodoos\API\ReversalAPI;
use Orbit\Helper\DigitalProduct\Providers\Woodoos\API\StatusAPI;

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
        throw new Exception("Confirm API not supported by Woodoos!");
    }

    /**
     * Implement reversal ability.
     *
     * @param  array  $params [description]
     * @return [type]         [description]
     */
    public function reversal($params = [])
    {
        return ReversalAPI::create($params)->run();
    }
}
