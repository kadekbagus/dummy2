<?php

namespace Orbit\Controller\API\v1\Pub\Rating\DataBuilder;

use App;
use Carbon\Carbon;
use Orbit\Helper\DataBuilder\DataBuilder;
use Queue;

/**
 * Update rating data builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class UpdateRatingDataBuilder extends DataBuilder
{
    public function build()
    {
        $user = $this->request->user();
        $rating = App::make('currentRating')->getRating()->data;
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->toDateTimeString();

        $ratingData = [
            'rating'             => $this->request->rating,
            'review'             => $this->request->review,
            'status'             => $rating->status,
            'approval_status'    => $rating->approval_status,
            'updated_at'         => $dateTime,
            '_id'                => $this->request->rating_id,
            'object_id'          => $rating->object_id,
            'object_type'        => $rating->object_type,
        ];

        $location = $this->request->getLocation();
        if (! empty($location)) {
            $ratingData = array_merge($ratingData, [
                'location_id'        => $rating->location_id,
                'city'               => $location->city,
                'country_id'         => $location->country_id,
            ]);
        }

        // Update and send email to admin,
        // only if there's new images on the request.
        $images = $this->request->getUploadedFiles();
        if (! empty($images)) {
            $ratingData = array_merge($ratingData, [
                'images' => $images,
                'is_image_reviewing' => 'n',
            ]);

            Queue::push('Orbit\\Queue\\ReviewImageNeedApprovalMailQueue', [
                'subject' => 'There is a review with image(s) that needs your approval',
                'object_id' => $this->request->object_id,
                'user_email' => $user->user_email,
                'user_fullname' => $user->fullName,
            ]);
        }

        return $ratingData;
    }
}
