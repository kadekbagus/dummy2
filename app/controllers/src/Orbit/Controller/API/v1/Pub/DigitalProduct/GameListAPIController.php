<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Product\Repository\GameRepository;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Request\GameListRequest;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Resource\GameCollection;

/**
 * Get list of Game.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameListAPIController extends PubControllerAPI
{
    /**
     * Handle Game list request.
     *
     * @return Illuminate\Http\Response
     */
    public function getList(GameRepository $repo, GameListRequest $request)
    {
        $httpCode = 200;

        try {

            $games = $repo->findGames();
            $total = clone $games;

            $games = $games->skip($request->skip)->take($request->take)->get();
            $total = $total->count();

            $this->response->data = new GameCollection($games, $total);

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
