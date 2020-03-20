<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct;

use BrandProduct;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\BrandProduct\BrandProductRepository;
use Orbit\Controller\API\v1\Pub\BrandProduct\Request\ListRequest;
use Orbit\Controller\API\v1\Pub\BrandProduct\Resource\BrandProductCollection;

/**
 * Brand product list controller.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductListAPIController extends PubControllerAPI
{
    /**
     * Handle product list request.
     *
     * @param  Repository     $productRepo     [description]
     * @param  BrandProductES $productProvider [description]
     * @param  ListRequest    $request         [description]
     * @return [type]                          [description]
     */
    public function handle(BrandProduct $brandProduct, ListRequest $request)
    {
        try {
            $brandProducts = $brandProduct->search($request);

            // Map product list from elastic search to a client-ready data.
            $this->response->data = new BrandProductCollection(
                $brandProducts['hits'],
                $brandProducts['total']
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
