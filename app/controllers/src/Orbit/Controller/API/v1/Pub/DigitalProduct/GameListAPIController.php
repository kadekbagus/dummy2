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
    public function getList()
    {
        $httpCode = 200;

        try {
            // $this->enableQueryLog();

            $this->authorize(['guest', 'consumer']);

            //TODO: need cleaner way to inject this
            (new GameListRequest($this))->validate();

            $this->response->data = App::make(GameRepository::class)->findGames();

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
