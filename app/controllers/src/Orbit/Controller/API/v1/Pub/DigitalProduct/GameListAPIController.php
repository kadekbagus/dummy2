<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use Exception;
use Illuminate\Support\Facades\App;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\GameRepository;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Request\GameListRequest;

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
    public function getList(GameRepository $gameRepo, GameListRequest $request)
    {
        $httpCode = 200;

        try {

            $games = $gameRepo->findGames();
            $total = clone $games;

            $total = $total->count();
            $games = $games->skip($skip)->take($take)->get();

            $this->response->data = new GameCollection($games, $total);

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
