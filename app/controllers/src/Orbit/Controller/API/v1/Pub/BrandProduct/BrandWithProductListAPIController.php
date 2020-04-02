<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\BrandProduct\BrandProductRepository;
use Orbit\Controller\API\v1\Pub\BrandProduct\Request\BrandWithProductListRequest;
use Orbit\Controller\API\v1\Pub\BrandProduct\Resource\BrandWithProductCollection;

/**
 * Brand list controller.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandWithProductListAPIController extends PubControllerAPI
{
    /**
     * Handle brand list request, specific only brand which has brand products.
     *
     * @param  Repository  $repo    [description]
     * @param  ListRequest $request [description]
     * @return [type]               [description]
     */
    public function handle(
        BrandProductRepository $repo,
        BrandWithProductListRequest $request
    ) {
        try {

            $this->response->data = new BrandWithProductCollection(
                $repo->brands($request)
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}