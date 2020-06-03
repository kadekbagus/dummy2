<?php

namespace Orbit\Controller\API\v1\Pub\Product;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\BrandProduct\Repository\BrandProductRepository;
use Orbit\Controller\API\v1\Pub\Product\Request\BrandWithProductListRequest;
use Orbit\Controller\API\v1\Pub\Product\Resource\BrandWithProductAffiliationCollection;

/**
 * Brand with product affiliation list controller.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandWithProductAffiliationListAPIController extends PubControllerAPI
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

            $brands = $repo->brandsWithProductAffiliation($request);

            $this->response->data = new BrandWithProductAffiliationCollection(
                $brands,
                $brands->count()
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
