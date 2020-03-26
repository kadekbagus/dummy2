<?php

namespace Orbit\Controller\API\v1\BrandProduct\Variant;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\BrandProduct\BrandProductRepository;
use Orbit\Controller\API\v1\BrandProduct\Variant\Request\ListRequest;
use Orbit\Controller\API\v1\BrandProduct\Variant\Resource\VariantCollection;

/**
 * Variant List Controller.
 *
 * @author Budi <budi@gotomalls.com>
 */
class VariantListAPIController extends ControllerAPI
{
    public function handle(BrandProductRepository $repo, ListRequest $request)
    {
        try {

            $this->response->data = new VariantCollection(
                $repo->variants($request)
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}