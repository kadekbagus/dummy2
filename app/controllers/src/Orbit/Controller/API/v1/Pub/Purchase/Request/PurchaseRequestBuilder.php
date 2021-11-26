<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Request;

use Exception;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use Orbit\Controller\API\v1\Pub\Purchase\Request\Order\NewOrderPurchaseRequest;
use Orbit\Controller\API\v1\Pub\Purchase\Request\DigitalProductPurchaseRequest;
use Request;

/**
 * Digital Product Purchase Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseRequestBuilder
{
    public function build($controller = null)
    {
        $productType = Request::input('object_type');

        switch ($productType) {
            case 'digital_product':
                return new DigitalProductPurchaseRequest($controller);
                break;

            case 'order':
                return new NewOrderPurchaseRequest();
                break;

            // other type goes here...

            default:
                throw new InvalidArgsException('Unknown product type.');
                break;
        }
    }
}
