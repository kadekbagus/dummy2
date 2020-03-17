<?php

namespace Orbit\Controller\API\v1\Rating\Validator;

use App;
use News;
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
    public function unique($attributes, $objectId, $parameters, $validator)
    {
        $validatorData = $validator->getData();
        $locationId = isset($validatorData['location_id'])
            ? $validatorData['location_id'] : null;

        if (empty($locationId)) {
            return false;
        }

        $rating = App::make(RatingModelInterface::class)->findByQuery([
            'user_id' => App::make('currentUser')->user_id,
            'object_id' => $objectId,
            'store_id' => $locationId,
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

    /**
     * Validate that rating location is valid and needed. For a reply and
     * promotional event, rating location is not required.
     *
     * @param  [type] $attributes [description]
     * @param  [type] $locationId [description]
     * @param  [type] $paramters  [description]
     * @param  [type] $validator  [description]
     * @return [type]             [description]
     */
    public function ratingLocation(
        $attributes,
        $locationId,
        $paramters,
        $validator
    ) {
        $data = $validator->getData();
        $locationRequired = true;

        if (isset($data['is_reply'])) {
            $locationRequired = false;
        }

        $promotionalEvent = [];
        if (isset($data['object_id']) && isset($data['object_type'])) {
            if ($data['object_type'] === 'news') {
                $promotionalEvent = News::select('news_id', 'is_having_reward')
                    ->where('news_id', $data['object_id'])->first();

                if (! empty($promotionalEvent)) {
                    $locationRequired =
                        $promotionalEvent->is_having_reward !== 'Y';
                }
            }
        }

        App::instance('promotionalEvent', $promotionalEvent);

        if ($locationRequired && empty(trim($locationId))) {
            return false;
        }

        return true;
    }
}
