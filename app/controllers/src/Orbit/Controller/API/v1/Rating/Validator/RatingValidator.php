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
    /**
     * Validate that rating exists.
     *
     * @param  [type] $attributes [description]
     * @param  [type] $ratingId   [description]
     * @param  [type] $parameters [description]
     * @return [type]             [description]
     */
    function exists($attributes, $ratingId, $parameters)
    {
        $rating = App::make(RatingModelInterface::class)->find($ratingId);

        App::instance('currentRating', $rating);

        return $rating->isNotEmpty();
    }

    /**
     * Validate that rating is unique.
     *
     * @param  [type] $attributes [description]
     * @param  [type] $objectId   [description]
     * @param  [type] $parameters [description]
     * @param  [type] $validator  [description]
     * @return [type]             [description]
     */
    public function uniqueRating($attributes, $objectId, $parameters, $validator)
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

    /**
     * Validate that current User requesting update is the same User as the one
     * who made the current rating.
     *
     * @param  [type] $attributes [description]
     * @param  [type] $ratingId   [description]
     * @param  [type] $parameters [description]
     * @return [type]             [description]
     */
    public function sameUser($attributes, $ratingId, $parameters)
    {
        $user = App::make('currentUser');
        $rating = App::make('currentRating');

        if ($rating->isEmpty()) {
            return false;
        }

        $rating = $rating->getRating();

        return $rating->data->user_id === $user->user_id;
    }
}
