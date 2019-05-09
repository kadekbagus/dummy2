<?php namespace Orbit\Controller\API\v1\Pub\Rating;
/**
 * @author ahmad <ahmad@dominopos.com>
 * @desc Controller for get rating review list
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use Validator;
use User;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use stdClass;
use Country;
use Tenant;
use News;
use Language;
use Mall;
use Coupon;

class RatingDetailAPIController extends PubControllerAPI
{
    protected $withoutScore = FALSE;
    protected $validLanguage = NULL;

    /**
     * GET - get rating review list
     *
     * @author ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string object_id
     * @param string object_type
     * @param string cities
     * @param string country
     * @param string mall_id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getDetail()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();
            $id = OrbitInput::get('review_id', null);
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'review_id'   => $id,
                    'language'   => $language,
                ),
                array(
                    'review_id' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);

            $endPoint = "reviews/" . $id;
            $response = $mongoClient->setEndPoint($endPoint)
                                    ->request('GET');

            $review = $response->data;

            if (! is_object($review)) {
                throw new OrbitCustomException('Review that you specify is not found', 404, NULL);
            }

            $reviewData = null;
            $userData = null;
            $objectData = null;

            // build review data
            $reviewData = new stdclass();
            $reviewData->review = $review->review;
            $reviewData->rating = $review->rating;
            $reviewData->created_at = $review->created_at;
            $reviewData->updated_at = $review->updated_at;
            $reviewData->images = isset($review->images) ? $review->images : null;
            $reviewData->total_reply = isset($review->total_reply) ? $review->total_reply : 0;
            $reviewData->store_id = isset($review->store_id) ? $review->store_id : null;
            $reviewData->store_name = isset($review->store_name) ? $review->store_name : null;
            $reviewData->location_id = isset($review->location_id) ? $review->location_id : null;
            $reviewData->mall_name = isset($review->mall_name) ? $review->mall_name : null;
            $reviewData->country_id = isset($review->country_id) ? $review->country_id : null;
            $reviewData->city = isset($review->city) ? $review->city : null;
            $reviewData->approval_status = isset($review->approval_status) ? $review->approval_status : null;
            $reviewData->status = isset($review->status) ? $review->status : null;
            $reviewData->is_image_reviewing = isset($review->is_image_reviewing) ? $review->is_image_reviewing : null;

            // build user data
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';
            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);

            $userImage = "(CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)) as user_picture";
            if ($usingCdn) {
                $userImage = "(CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END) as user_picture";
            }
            $reviewUser = User::select('users.user_id', 'roles.role_name', DB::raw("(CONCAT({$prefix}users.user_firstname, ' ', {$prefix}users.user_lastname)) as user_name"), DB::raw($userImage))
                ->leftJoin('media', function ($q) {
                        $q->on('media.object_id', '=', 'users.user_id')
                            ->on('media.media_name_long', '=', DB::raw("'user_profile_picture_orig'"));
                    })
                ->join('roles', 'roles.role_id', '=', 'users.user_role_id')
                ->where('users.user_id', $review->user_id)
                ->first();

            $roleOfficial = ['Merchant Review Admin', 'Master Review Admin'];

            $userData = new stdClass();
            $userData->user_id = $reviewUser->user_id;
            $userData->user_name = $reviewUser->user_name;
            $userData->user_picture = $reviewUser->user_picture;
            $userData->is_official_user = in_array($reviewUser->role_name, $roleOfficial) ? 'y' : 'n';

            // build object data
            $objectType = $review->object_type;
            $objectId = $review->object_type;
            $objectData = new stdclass();

            $mall = null;
            if (isset($review->location_id) && ! empty($review->location_id)) {
                $mall = Mall::excludeDeleted()->where('merchant_id', '=', $review->location_id)->first();
            }

            $objectReviewCounter = \Orbit\Helper\MongoDB\Review\ReviewCounter::create($mongoConfig)
                ->setObjectId($review->object_id)
                ->setObjectType($review->object_type)
                ->setMall($mall)
                ->request();

            $ratingAverage = $objectReviewCounter->getAverage();
            $ratingCounter = $objectReviewCounter->getCounter();

            switch ($objectType) {
                case 'store':
                    $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as path";
                    if ($usingCdn) {
                        $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as path";
                    }

                    $object = Tenant::select('merchants.*')
                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                        ->with(['mediaLogo' => function ($q) use ($image) {
                            $q->select(
                                    DB::raw("{$image}"),
                                    'media.object_id'
                                );
                        }])
                        ->where('merchants.status', '=', 'active')
                        ->where(DB::raw('oms.status'), '=', 'active')
                        ->where('merchants.name', $review->store_name)
                        ->where(DB::raw("oms.country_id"), $review->country_id)
                        ->first();

                    $objectData->id = $object->merchant_id;
                    $objectData->name = $object->name;
                    $objectData->type = 'store';
                    $objectData->image = isset($object->mediaLogo[0]) ? $object->mediaLogo[0]->path : null;

                    break;
                
                case 'news': 
                case 'promotion': 
                    $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
                    if ($usingCdn) {
                        $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
                    }

                    $object = News::select(
                            'news.news_id as news_id',
                            DB::Raw("
                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                CASE WHEN ({$prefix}news_translations.description = '' or {$prefix}news_translations.description is null) THEN default_translation.description ELSE {$prefix}news_translations.description END as description,
                                CASE WHEN (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = {$prefix}news_translations.news_translation_id) is null
                                THEN
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = default_translation.news_translation_id)
                                ELSE
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = {$prefix}news_translations.news_translation_id)
                                END AS original_media_path
                            "),
                            'news.object_type',
                            DB::raw("default_translation.news_name as default_name"),
                            // query for get status active based on timezone
                            DB::raw("
                                    CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                            THEN {$prefix}campaign_status.campaign_status_name
                                            ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                        FROM {$prefix}news_merchant onm
                                                                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                        WHERE onm.news_id = {$prefix}news.news_id)
                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                    CASE WHEN (SELECT count(onm.merchant_id)
                                                FROM {$prefix}news_merchant onm
                                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                WHERE onm.news_id = {$prefix}news.news_id
                                                AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                    THEN 'true' ELSE 'false' END AS is_started
                            ")
                        )
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                        ->leftJoin('news_translations', function ($q) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($this->validLanguage->language_id)}"));
                        })
                        ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                            $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                              ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                        })
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->where('news.news_id', $review->object_id)
                        ->where('news.object_type', '=', $objectType)
                        ->first();

                    $objectData->id = $object->news_id;
                    $objectData->name = $object->news_name;
                    $objectData->type = $objectType;
                    $objectData->image = $object->original_media_path;
                    break;

                case 'coupon':
                    $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
                    if ($usingCdn) {
                        $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
                    }

                    $object = Coupon::select(
                            'promotions.promotion_id as promotion_id',
                            DB::Raw("
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                    CASE WHEN (SELECT {$image}
                                        FROM {$prefix}media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id
                                        AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($this->validLanguage->language_id)}
                                        LIMIT 1
                                        ) is null
                                    THEN
                                        (SELECT {$image}
                                        FROM {$prefix}media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = default_translation.coupon_translation_id
                                        AND default_translation.merchant_language_id = {$this->quote($this->validLanguage->language_id)}
                                        LIMIT 1
                                        )
                                    ELSE
                                        (SELECT {$image}
                                        FROM {$prefix}media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id
                                        AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($this->validLanguage->language_id)}
                                        LIMIT 1)
                                    END AS original_media_path
                                ")
                        )
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')

                        ->leftJoin('coupon_translations', function ($q) {
                            $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                              ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($this->validLanguage->language_id)}"));
                        })
                        ->leftJoin('coupon_translations as default_translation', function ($q) {
                            $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                              ->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id');
                        })
                        ->where('promotions.promotion_id', $review->object_id)
                        ->where('promotions.is_visible', 'Y')
                        ->first();

                    $objectData->id = $object->promotion_id;
                    $objectData->name = $object->promotion_name;
                    $objectData->type = 'coupon';
                    $objectData->image = $object->original_media_path;
                    break;

                case 'mall':
                    $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as path";
                    if ($usingCdn) {
                        $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as path";
                    }

                    $object = Mall::select('merchants.*')
                        ->with(['mediaLogo' => function ($q) use ($image) {
                            $q->select(
                                    DB::raw("{$image}"),
                                    'media.object_id'
                                );
                        }])
                        ->where('merchants.status', '=', 'active')
                        ->where('merchant_id', $review->object_id)
                        ->first();

                    $objectData->id = $object->merchant_id;
                    $objectData->name = $object->name;
                    $objectData->type = 'mall';
                    $objectData->image = isset($object->mediaLogo[0]) ? $object->mediaLogo[0]->path : null;
                    break;

                default:
                    # code...
                    break;
            }
            
            $objectData->ratingAverage = $ratingAverage;
            $objectData->ratingCounter = $ratingCounter;

            $data = new \stdclass();
            $data->review_data = $reviewData;
            $data->user_data = $userData;
            $data->object_data = $objectData;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    /**
     * Force $withScore value to FALSE, ignoring previously set value
     * @param $bool boolean
     */
    public function setWithOutScore()
    {
        $this->withoutScore = TRUE;

        return $this;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    protected function registerCustomValidation()
    {
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->validLanguage = $language;
            return TRUE;
        });
    }

    protected function getValidLanguage()
    {
        return $this->validLanguage;
    }
}