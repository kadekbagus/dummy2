<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use Exception;
use Illuminate\Support\Facades\App;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\GameRepository;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Request\GameDetailRequest;

/**
 * Get detail of a Game.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameDetailAPIController extends PubControllerAPI
{
    /**
     * Handle Game detail request.
     *
     * @return Illuminate\Http\Response
     */
    public function getDetail()
    {
        $httpCode = 200;

        try {
            // $this->enableQueryLog();

            (new GameDetailRequest($this))->validate();

            $this->response->data = App::make(GameRepository::class)->findGame();

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
