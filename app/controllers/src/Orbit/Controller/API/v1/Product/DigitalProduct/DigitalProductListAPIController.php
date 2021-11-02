<?php

namespace Orbit\Controller\API\v1\Product\DigitalProduct;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\DigitalProduct\Request\ListRequest;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductCollection;
use Orbit\Controller\API\v1\Product\Repository\DigitalProductRepository as Repo;

/**
 * Get list of digital product.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductListAPIController extends ControllerAPI
{
    /**
     * Handle Digital Product list request.
     *
     * @param  DigitalProductRepository $repo
     * @param  ListRequest $request
     *
     * @return Illuminate\Http\Response
     */
    public function getList(Repo $repo, ListRequest $request)
    {
        $httpCode = 200;

        try {

            // Fetch the digital products
            $digitalProducts = $repo->findProducts();
            $digitalProducts = $digitalProducts->with('games');

            $total = clone $digitalProducts;
            $total = $total->count();
            $digitalProducts = $digitalProducts->skip($request->skip)
                ->take($request->take)->get();

            foreach ($digitalProducts as $digitalProduct) {
                $gameNames = [];
                foreach ($digitalProduct->games as $game) {
                    $gameNames[] = $game->game_name;
                }
                $digitalProduct->game_name = implode(', ', $gameNames);
            }

            $this->response->data = new DigitalProductCollection(
                $digitalProducts,
                $total
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
