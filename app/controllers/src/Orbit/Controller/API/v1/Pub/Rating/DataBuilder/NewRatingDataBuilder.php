<?php

namespace Orbit\Controller\API\v1\Pub\Rating\DataBuilder;

use Carbon\Carbon;
use Orbit\Helper\DataBuilder\DataBuilder;
use Queue;

/**
 * New rating data builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class NewRatingDataBuilder extends DataBuilder
{
    public function build()
    {
        $user = $this->request->user();
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->toDateTimeString();
        $review = strip_tags($this->request->review);

        $ratingData = [
            'object_id'         => $this->request->object_id,
            'object_type'       => $this->request->object_type,
            'user_id'           => $user->user_id,
            'rating'            => $this->request->rating,
            'review'            => $review,
            'status'            => $this->request->status ?: 'active',
            'approval_status'   => $this->request->approval_status ?: 'approved',
            'created_at'        => $dateTime,
            'updated_at'        => $dateTime,
            'is_reply'          => 'n',
        ];

        // If reply, then adjust rating data...
        if ($this->request->isReply()) {
            $ratingData['rating'] = 0;
            $ratingData['is_reply'] = 'y';
            $ratingData['parent_id'] = $this->request->parent_id;
            $ratingData['user_id_replied'] = $this->request->user_id_replied;
            $ratingData['review_id_replied'] = $this->request->review_id_replied;
        }

        // If not promotional event, then add location info into rating data.
        if (! $this->request->isPromotionalEvent()) {

            // Resolve rating location...
            $location = $this->request->getLocation();

            if (! empty($location)) {
                $ratingData = array_merge($ratingData, [
                    'location_id'   => $location->location_id,
                    'store_id'      => $this->request->location_id,
                    'store_name'    => $location->store_name,
                    'mall_name'     => $location->mall_name,
                    'city'          => $location->city,
                    'country_id'    => $location->country_id,
                ]);
            }
        }

        $images = $this->request->getUploadedFiles();
        if (! empty($images)) {
            $ratingData = array_merge($ratingData, [
                'images' => $images,
                'is_image_reviewing' => 'n',
            ]);

            //send email to admin
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
