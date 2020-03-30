<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct;

use BrandProduct;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\BrandProduct\BrandProductRepository as Repository;
use Orbit\Controller\API\v1\Pub\BrandProduct\Request\ReserveRequest;
use Orbit\Controller\API\v1\Pub\BrandProduct\Resource\ProductReservationResource;

/**
 * Brand product reservation controller.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductReservationAPIController extends PubControllerAPI
{
    /**
     * Handle product reservation request.
     *
     * @param  Repository     $productRepo     [description]
     * @param  BrandProductES $productProvider [description]
     * @param  ListRequest    $request         [description]
     * @return [type]                          [description]
     */
    public function handle(Respository $repo, ReserveRequest $request)
    {
        try {

            $this->response->data = new ProductReservationResource(
                $repo->reserve($request->reservation_id)
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
