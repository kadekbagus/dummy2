<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Request;

use Exception;
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
    public function build($controller)
    {
        $productType = Request::input('object_type');

        switch ($productType) {
            // case 'pulsa':
            //      'data_plan':
            //     return new PulsaPurchaseRequest($controller);
            //     break;

            // case 'coupon':
            //     return new CouponPurchaseRequest($controller);
            //     break;

            case 'digital_product':
                return new DigitalProductPurchaseRequest($controller);
                break;

            default:
                throw new Exception('Unknown product type.');
                break;
        }
    }
}
