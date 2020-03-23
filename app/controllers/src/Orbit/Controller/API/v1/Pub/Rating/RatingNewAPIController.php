<?php

namespace Orbit\Controller\API\v1\Pub\Rating;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Rating\Request\CreateRequest;
use Orbit\Controller\API\v1\Rating\Repository\RatingRepository as Repository;

/**
 * Controller which handle rating/review update.
 * Fully rewritten using new Repository/Request and DataBuilder helpers.
 *
 * @todo Separate handler for a Rating and a Reply.
 *
 * @author Budi <budi@gotomalls.com>
 */
class RatingNewAPIController extends PubControllerAPI
{
    /**
     * Handle new rating request.
     *
     * @param  Repository    $repo    [description]
     * @param  ValidateRequest $request [description]
     * @return [type]                 [description]
     */
    public function postNewRating(Repository $rating, CreateRequest $request)
    {
        try {

            $this->response->data = $rating->save($request);

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
