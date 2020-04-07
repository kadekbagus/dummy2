<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct;

use BrandProductAvailableStore as AvailableStore;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\BrandProduct\Request\AvailableStoreListRequest as Request;
use Orbit\Controller\API\v1\Pub\BrandProduct\Resource\AvailableStoreCollection;

/**
 * Available store list for product list filtering.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AvailableStoreListAPIController extends PubControllerAPI
{
    /**
     * Handle product list request.
     *
     * @param  BrandProduct   $brandProduct    [description]
     * @param  ListRequest    $request         [description]
     * @return [type]                          [description]
     */
    public function handle(AvailableStore $availableStore, Request $request)
    {
        try {

            $availableStores = $availableStore->search($request);

            // Map product list result from search provider
            // to a client-ready collection.
            $this->response->data = new AvailableStoreCollection(
                $availableStores['hits']['hits'],
                $availableStores['hits']['total']
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
