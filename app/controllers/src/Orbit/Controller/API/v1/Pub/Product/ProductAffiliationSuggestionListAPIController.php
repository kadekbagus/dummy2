<?php

namespace Orbit\Controller\API\v1\Pub\Product;

use ProductSuggestion;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Product\Request\ListRequest;
use Orbit\Controller\API\v1\Pub\Product\Resource\ProductAffiliationSuggestionCollection;

/**
 * Product Affiliation Suggestion list controller.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ProductAffiliationSuggestionListAPIController extends PubControllerAPI
{
    /**
     * Handle product list request.
     *
     * @param  BrandProduct   $brandProduct    [description]
     * @param  ListRequest    $request         [description]
     * @return [type]                          [description]
     */
    public function handle(ProductSuggestion $product, ListRequest $request)
    {
        try {
            // Search/get list of brand products.
            $products = $product->search($request);

            // Map product list result from search provider
            // to a client-ready collection.
            $this->response->data = new ProductAffiliationSuggestionCollection(
                $products['hits']['hits'],
                $products['hits']['total']
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
