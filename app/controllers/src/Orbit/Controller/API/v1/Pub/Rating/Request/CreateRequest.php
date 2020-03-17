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
use Orbit\Helper\Request\Validators\CommonValidator;
use Validator;

/**
 * Create Review request validation.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CreateRequest extends ValidateRequest implements RequestWithUpload
{
    // Provide a base implementation of RequestWithUpload.
    use InteractsWithUpload;

    // Only allow role consumer to post new request.
    protected $roles = ['consumer'];

    // Only allow active User to post new request.
    protected $userStatus = ['active'];

    // Indicate that is current object being reviewed
    // is a promotional event or not.
    protected $isPromotionalEvent = false;

    // Location of the object being reviewed (which mall/store, etc).
    protected $location = null;

    public function rules()
    {
        return [
            'review'      => 'required|trim:newline,strip_tags|max:1000',
            'object_id'   => 'required|orbit.rating.unique',
            'object_type' => 'required',
            'rating'      => 'required',
            'location_id' => 'orbit.rating.location',
            'is_reply'    => 'sometimes|required',
            'parent_id'   => 'required_with:is_reply',
            'user_id_replied' => 'required_with:is_reply',
            'review_id_replied' => 'required_with:is_reply',
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
            'orbit.rating.unique',
            RatingValidator::class . '@unique'
        );

        Validator::extend(
            'orbit.rating.location',
            RatingValidator::class . '@ratingLocation'
        );

        Validator::extend('trim', CommonValidator::class . '@trimInput');
    }

    /**
     * Determine if current review is a Reply or not.
     *
     * @return bool
     */
    public function isReply()
    {
        return $this->is_reply;
    }

    /**
     * Get the promotional event status.
     *
     * @return bool
     */
    public function isPromotionalEvent()
    {
        return $this->isPromotionalEvent;
    }

    /**
     * Get the rating location.
     *
     * @return CampaignLocation the location.
     */
    public function getLocation()
    {
        return ! empty($this->location)
            ? $this->location
            : $this->resolveReviewLocation();
    }

    /**
     * After completing validation, try resolving promotional event status
     * and location information.
     */
    protected function afterValidation()
    {
        $this->resolvePromotionalEvent();
    }

    /**
     * Determine if current rating is for a promotional event or not.
     * Here we assume there is an instance of promotional event exists in the
     * container (from the validation steps).
     */
    protected function resolvePromotionalEvent()
    {
        $promotionalEvent = App::make('promotionalEvent');
        $this->isPromotionalEvent = ! empty($promotionalEvent)
            && $promotionalEvent->is_having_reward === 'Y';
    }

    /**
     * Resolve rating location (store, mall, country)
     *
     * @return CampaignLocation $location the campaign location
     */
    protected function resolveReviewLocation()
    {
        if (! $this->isPromotionalEvent()) {

            $prefix = DB::getTablePrefix();
            $this->location = CampaignLocation::select(
                'merchants.name',
                'merchants.country',
                'merchants.object_type',
                DB::raw("
                    IF({$prefix}merchants.object_type = 'tenant', oms.merchant_id, {$prefix}merchants.merchant_id) as location_id,
                    IF({$prefix}merchants.object_type = 'tenant', {$prefix}merchants.name, '') as store_name,
                    IF({$prefix}merchants.object_type = 'tenant', oms.name, {$prefix}merchants.name) as mall_name,
                    IF({$prefix}merchants.object_type = 'tenant', oms.city, {$prefix}merchants.city) as city,
                    IF({$prefix}merchants.object_type = 'tenant', oms.country_id, {$prefix}merchants.country_id) as country_id
                "))
                ->leftJoin(
                    DB::raw("{$prefix}merchants as oms"),
                    DB::raw('oms.merchant_id'),
                    '=',
                    'merchants.parent_id'
                )
                ->where('merchants.merchant_id', '=', $this->location_id)
                ->first();
        }

        return $this->location;
    }

    /**
     * Implement file upload handling specific for Rating.
     */
    public function handleUpload()
    {
        $maxFiles = 4;
        $uploadMedias = Event::fire(
            'orbit.rating.postnewmedia',
            [$this->user, ['object_id' => $this->object_id]]
        );

        if (count($uploadMedias[0]) > 0) {
            $defaultUrlPrefix = Config::get(
                'orbit.cdn.providers.default.url_prefix', null
            );

            $urlPrefix = ! empty($defaultUrlPrefix)
                ? $defaultUrlPrefix . '/' : '';

            foreach ($uploadMedias[0] as $key => $medias) {

                // Limit the number of files that will be recorded.
                if ($key >= $maxFiles) {
                    break;
                }

                foreach ($medias->variants as $keyVar => $variant) {
                    $this->uploads[$key][$keyVar]['media_id'] = $variant->media_id ;
                    $this->uploads[$key][$keyVar]['variant_name'] = $variant->media_name_long ;
                    $this->uploads[$key][$keyVar]['url'] = $urlPrefix . $variant->path ;
                    $this->uploads[$key][$keyVar]['cdn_url'] = '' ;
                    $this->uploads[$key][$keyVar]['metadata'] = $variant->metadata;
                    $this->uploads[$key][$keyVar]['approval_status'] = 'pending';
                    $this->uploads[$key][$keyVar]['rejection_message'] = '';
                }
            }
        }
    }
}
