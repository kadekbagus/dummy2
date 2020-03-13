<?php

namespace Orbit\Controller\API\v1\Rating\Validator;

use App;
use Orbit\Controller\API\v1\Rating\RatingModelInterface;

/**
 * Custom validator related to Rating/Review.
 *
 * @author Budi <budi@gotomalls.com>
 */
class RatingValidator
{
    function exists($attributes, $ratingId, $parameters)
    {
        return App::make(RatingModelInterface::class)->exists($ratingId);
    }

    public function uniqueLocation($attributes, $objectId, $parameters, $validator)
    {
        $validatorData = $validator->getData();

        $rating = App::make(RatingModelInterface::class)->findByQuery([
            'user_id' => App::make('currentUser')->user_id,
            'object_id' => $objectId,
            'store_id' => $validatorData['location_id'],
            'status' => 'active',
        ]);

        App::instance('duplicateRating', $rating->getRating());

        return $rating->isEmpty();
    }
}
