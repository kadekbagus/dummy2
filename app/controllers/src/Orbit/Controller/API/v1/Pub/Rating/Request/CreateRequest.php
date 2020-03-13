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
    private $isPromotionalEvent = false;

    // Location of the object being reviewed (which mall/store, etc).
    private $location = null;

    public function __construct()
    {
        // Resolve promotional event status.
        $this->resolvePromotionalEvent();

        // Resolve review location information.
        $this->resolveReviewLocation();

        parent::__construct();
    }

    public function rules()
    {
        $rules = [
            'review'      => 'required|max:1000',
            'object_id'   => 'required|orbit.unique.review_object_location',
            'object_type' => 'required',
            'rating'      => 'required',
            'location_id' => 'required',
            'is_reply'    => 'sometimes|required|in:true',
            'parent_id'   => 'required_with:is_reply',
            'user_id_replied' => 'required_with:is_reply',
            'review_id_replied' => 'required_with:is_reply',
        ];

        // Remove location_id rule if it is Promotional Event.
        if ($this->isPromotionalEvent() || $this->isReply()) {
            unset($rules['location_id']);
        }

        return $rules;
    }

    public function messages()
    {
        return [
            // Uncomment following line to enable specific duplicate rating
            // validation error message.
            // 'orbit.unique.review_object_location' => 'DUPLICATE_RATING_REVIEW',
            'max' => 'REVIEW_FAILED_MAX_CHAR_EXCEEDED',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.unique.review_object_location',
            RatingValidator::class . '@uniqueLocation'
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
            ["\r", "\n"], '', $validationData['review']
        );

        if ($this->isReply()) {
            $validationData['rating'] = 0;
        }

        return $validationData;
    }

    /**
     * Display specific validation error message.
     *
     * @return string $errorMessage validation error message.
     */
    // public function getValidationErrorMessage()
    // {
    //     $errorMessage = parent::getValidationErrorMessage();

    //     if ($errorMessage === 'DUPLICATE_RATING_REVIEW') {
    //         $duplicateRating = App::make('duplicateRating')->data->records[0];

    //         if ($this->object_type === 'mall') {
    //             $errorMessage = trans(
    //                 'validation.orbit.unique.review_object_location_mall',
    //                 ['mall' => $duplicateRating->mall_name]
    //             );
    //         }
    //         else if ($this->object_type === 'store') {
    //             $errorMessage = trans(
    //                 'validation.orbit.unique.review_object_location_store',
    //                 [
    //                     'store' => $duplicateRating->store_name,
    //                     'mall' => $duplicateRating->mall_name,
    //                 ]
    //             );
    //         }
    //         else {
    //             $errorMessage = trans(
    //                 'validation.orbit.unique.review_object_location_campaign',
    //                 [
    //                     'campaign' => $this->object_name
    //                         ? "'{$this->object_name}'" : '',
    //                     'store' => $duplicateRating->store_name,
    //                     'mall' => $duplicateRating->mall_name,
    //                 ]
    //             );
    //         }
    //     }

    //     return $errorMessage;
    // }

    /**
     * Determine if current review is a Reply or not.
     *
     * @return bool
     */
    public function isReply()
    {
        return ! empty($this->is_reply);
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
        return $this->location;
    }

    /**
     * Determine if current rating is for a promotional event or not.
     */
    private function resolvePromotionalEvent()
    {
        if ($this->object_type === 'news') {
            $this->isPromotionalEvent = News::select(
                    'news_id', 'is_having_reward'
                )
                ->findOrFail($this->object_id)
                ->is_having_reward === 'Y';
        }
    }

    /**
     * Resolve rating location (store, mall, country)
     */
    public function resolveReviewLocation()
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
    }

    /**
     * Override default implementation of handling file upload, specific for
     * rating.
     */
    public function handleUpload()
    {
        $uploadMedias = Event::fire(
            'orbit.rating.postnewmedia',
            [$this->user, ['object_id' => $this->object_id]]
        );

        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        if (count($uploadMedias[0]) > 0) {
            foreach ($uploadMedias[0] as $key => $medias) {
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
