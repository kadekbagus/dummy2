<?php

namespace Orbit\Controller\API\v1\Pub\Rating;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Rating\Repository\RatingRepository as Repository;
use Orbit\Controller\API\v1\Pub\Rating\Request\UpdateRequest;

/**
 * Controller which handle rating/review update.
 * Fully rewritten using new Repository/Request and DataBuilder helpers.
 *
 * @author Budi <budi@gotomalls.com>
 */
class RatingUpdateAPIController extends PubControllerAPI
{
    /**
     * Handle update rating/review.
     *
     * @param  Repository    $rating  [description]
     * @param  UpdateRequest $request [description]
     * @return Illuminate\Http\Response
     */
    public function postUpdateRating(Repository $rating, UpdateRequest $request)
    {
        try {

            $this->response->data = $rating->update($request);

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
