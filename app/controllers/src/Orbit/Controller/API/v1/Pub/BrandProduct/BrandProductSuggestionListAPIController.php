<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct;

use BrandProductSuggestion;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\BrandProduct\Request\ListRequest;
use Orbit\Controller\API\v1\Pub\BrandProduct\Resource\BrandProductCollection;

/**
 * Brand product suggestion (you may also like) list controller.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductSuggestionListAPIController extends PubControllerAPI
{
    /**
     * Handle product list request.
     *
     * @param  BrandProduct   $brandProduct    [description]
     * @param  ListRequest    $request         [description]
     * @return [type]                          [description]
     */
    public function handle(
        BrandProductSuggestion $brandProduct,
        ListRequest $request
    ) {
        try {
            // Search/get list of brand products.
            $brandProducts = $brandProduct->search($request);

            // Map product list result from search provider
            // to a client-ready collection.
            $this->response->data = new BrandProductCollection(
                $brandProducts['hits']['hits'],
                $brandProducts['hits']['total']
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
