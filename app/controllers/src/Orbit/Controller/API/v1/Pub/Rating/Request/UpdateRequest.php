<?php

namespace Orbit\Controller\API\v1\Pub\Rating\Request;

use App;
use CampaignLocation;
use Config;
use DB;
use Event;
use News;
use Orbit\Controller\API\v1\Rating\Validator\RatingValidator;
use Orbit\Helper\Request\Contracts\RequestWithUpload;
use Orbit\Helper\Request\Helpers\InteractsWithUpload;
use Orbit\Helper\Request\ValidateRequest;
use Validator;

/**
 * Update Review request validation.
 *
 * @author Budi <budi@gotomalls.com>
 */
class UpdateRequest extends ValidateRequest implements RequestWithUpload
{
    use InteractsWithUpload;

    protected $roles = ['consumer'];

    protected $userStatus = ['active'];

    protected $isPromotionalEvent = false;

    protected $location = null;

    public function rules()
    {
        return [
            'review'      => 'required|max:1000',
            'rating_id'   => 'required|orbit.exists.rating|orbit.same_user',
            'rating'      => 'required',
        ];
    }

    public function messages()
    {
        return [
            'max' => 'REVIEW_FAILED_MAX_CHAR_EXCEEDED',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.exists.rating', RatingValidator::class . '@exists'
        );

        Validator::extend(
            'orbit.same_user', RatingValidator::class . '@sameUser'
        );
    }

    /**
     * Trim and clean up review text before validating.
     *
     * @return array
     */
    protected function validationData()
    {
        $validationData = parent::validationData();
        $validationData['review'] = str_replace(
            ["\r", "\n"], '', trim($validationData['review'])
        );

        return $validationData;
    }

    public function isPromotionalEvent()
    {
        return $this->isPromotionalEvent;
    }

    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Determine if current rating is for a promotional event or not.
     */
    protected function resolvePromotionalEvent($rating)
    {
        if ($rating->data->object_type === 'news') {
            $this->isPromotionalEvent = News::select(
                    'news_id', 'is_having_reward'
                )
                ->findOrFail($rating->data->object_id)
                ->is_having_reward === 'Y';
        }
    }

    protected function resolveReviewLocation($rating)
    {
        if (! $this->isPromotionalEvent()) {
            $prefix = DB::getTablePrefix();
            $this->location = CampaignLocation::select(
                'merchants.name',
                'merchants.country',
                DB::raw("
                    IF({$prefix}merchants.object_type = 'tenant', oms.city, {$prefix}merchants.city) as city,
                    IF({$prefix}merchants.object_type = 'tenant', oms.country_id, {$prefix}merchants.country_id) as country_id
                "))
                ->leftJoin(
                    DB::raw("{$prefix}merchants as oms"),
                    DB::raw('oms.merchant_id'),
                    '=',
                    'merchants.parent_id'
                )
                ->where(
                    'merchants.merchant_id', '=', $rating->data->location_id
                )->first();
        }
    }

    protected function afterValidation()
    {
        $rating = App::make('currentRating')->getRating();

        $this->resolvePromotionalEvent($rating);

        $this->resolveReviewLocation($rating);
    }

    /**
     * Override default implementation of handling file upload, specific for
     * rating. Here we merge old images with the new ones.
     */
    public function handleUpload()
    {
        $rating = App::make('currentRating');

        $maxImages = 4;
        $oldImages = $rating->getImages();

        $uploadMedias = Event::fire('orbit.rating.postnewmedia', [
            $this->user,
            ['object_id' => $rating->getRating()->data->object_id]
        ]);

        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        // If we have new images uploaded, then set the old ones
        // as default value, then merge with the new ones.
        if (count($uploadMedias[0]) > 0) {

            // Set old images as default value.
            $this->uploads = $oldImages;
            $imageIndex = count($oldImages);

            // Then merge with the new ones.
            foreach ($uploadMedias[0] as $key => $medias) {

                // Limit the number of images that can be stored in rating
                // record, even if client send more than maximum allowed images.
                if ($imageIndex >= $maxImages) {
                    break;
                }

                foreach ($medias->variants as $keyVar => $variant) {
                    $this->uploads[$imageIndex][$keyVar] = [
                        'media_id' => $variant->media_id ,
                        'variant_name' => $variant->media_name_long ,
                        'url' => $urlPrefix . $variant->path ,
                        'cdn_url' => '' ,
                        'metadata' => $variant->metadata,
                        'approval_status' => 'pending',
                        'rejection_message' => '',
                    ];
                }

                $imageIndex++;
            }
        }
    }
}
