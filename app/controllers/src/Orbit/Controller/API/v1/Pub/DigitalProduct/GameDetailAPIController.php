<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\GameRepository;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Request\GameDetailRequest;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Resource\GameResource;

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
    public function getDetail(
        GameRepository $gameRepo,
        GameDetailRequest $request)
    {
        $httpCode = 200;

        try {

            $this->response->data = new GameResource(
                $gameRepo->findGame($request->slug)
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
