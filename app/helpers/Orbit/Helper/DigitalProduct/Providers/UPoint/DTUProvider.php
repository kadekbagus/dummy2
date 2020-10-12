<?php namespace Orbit\Helper\DigitalProduct\Providers\UPoint;

use Exception;
use Orbit\Helper\DigitalProduct\Providers\BaseProvider;
use Orbit\Helper\DigitalProduct\Providers\UPoint\API\DTU\ConfirmAPI;
use Orbit\Helper\DigitalProduct\Providers\UPoint\API\DTU\StatusAPI;
use Orbit\Helper\DigitalProduct\Providers\UPoint\API\DTU\PurchaseAPI;

/**
 * Purchase Provider for UPoint.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DTUProvider extends BaseProvider
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
     * implement confirm purchase to upoint server.
     */
    public function confirm($params = [])
    {
        return ConfirmAPI::create($params)->run();
    }
}
