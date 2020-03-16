<?php

namespace Orbit\Controller\API\v1\Rating\Repository;

use App;
use Carbon\Carbon;
use Event;
use Orbit\Controller\API\v1\Pub\Rating\DataBuilder\NewRatingDataBuilder;
use Orbit\Controller\API\v1\Pub\Rating\DataBuilder\UpdateRatingDataBuilder;
use Orbit\Controller\API\v1\Rating\RatingModelInterface;
use Queue;

/**
 * Rating Repository. Provide abstraction to several
 *
 * @author Budi <budi@gotomalls.com>
 */
class RatingRepository
{
    // Rating model.
    private $ratingModel;

    function __construct(RatingModelInterface $ratingModel)
    {
        $this->ratingModel = $ratingModel;
    }

    /**
     * Save rating from pub/landing page request.
     *
     * @param  ValidateRequest $request
     * @return Object rating object from Mongo (or any db storage).
     */
    public function save($request)
    {
        // Build rating data...
        $ratingData = (new NewRatingDataBuilder($request))->build();

        // Save to DB...
        $rating = $this->ratingModel->save($ratingData);

        // Add location info into rating data.
        $location = $request->getLocation();
        if (! $request->isPromotionalEvent() && ! empty($location)) {
            $ratingData['merchant_name'] = $location->name;
            $ratingData['country'] = $location->country;
        }

        // Fire necessary events...
        Event::fire('orbit.rating.postrating.after.commit', [
            $ratingData, $request->user()
        ]);

        return $rating->data;
    }

    /**
     * Update rating.
     *
     * @todo update orbit mongo node to return updated document
     *       (returnNewDocument = true) instead of the old one.
     * @param  [type] $id      [description]
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function update($request)
    {
        $ratingData = (new UpdateRatingDataBuilder($request))->build();

        $rating = $this->ratingModel->update($request->rating_id, $ratingData);

        $location = $request->getLocation();
        if (! empty($location)) {
            $ratingData['merchant_name'] = $location->name;
            $ratingData['country'] = $location->country;
        }

        Event::fire('orbit.rating.postrating.after.commit', [
            $ratingData, $request->user()
        ]);

        return $rating->data;
    }

    /**
     * Save reply (from review portal).
     *
     * @param  ValidateRequest $request
     * @return Object the reply object from mongo (or any db storage)
     */
    public function saveReply($request)
    {
        $reply = $this->ratingModel->save(
            (new NewReplyDataBuilder($request))->build()
        );

        $reply->data->user_name = $request->user()->fullName;

        return $reply;
    }
}
