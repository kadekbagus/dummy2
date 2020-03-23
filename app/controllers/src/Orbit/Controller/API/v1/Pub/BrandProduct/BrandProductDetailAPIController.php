<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct;

use BrandProduct;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\BrandProduct\BrandProductRepository as Repository;
use Orbit\Controller\API\v1\Pub\BrandProduct\Request\DetailRequest;
use Orbit\Controller\API\v1\Pub\BrandProduct\Resource\BrandProductResource;

/**
 * Brand product detail controller.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductDetailAPIController extends PubControllerAPI
{
    /**
     * Handle product list request.
     *
     * @param  Repository     $productRepo     [description]
     * @param  BrandProductES $productProvider [description]
     * @param  ListRequest    $request         [description]
     * @return [type]                          [description]
     */
    public function handle(Repository $repo, DetailRequest $request)
    {
        try {

            $this->response->data = new BrandProductResource(
                $repo->get($request->brand_product_id)
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
