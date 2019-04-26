<?php namespace Orbit\Controller\API\v1\Pub\Rating;
/**
 * @author firmansyah <firmansyah@dominopos.com>
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

class RatingDetailAPIController extends PubControllerAPI
{
    protected $withoutScore = FALSE;
    protected $validLanguage = NULL;

    /**
     * GET - get rating review list
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
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
    public function getRatingList()
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
                    'language'   => $id,
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

            $queryString = [
                '_id'         => $id,
            ];

            $mongoClient = MongoClient::create($mongoConfig);

            $endPoint = "reviews";
            if (! empty($arrayQuery)) {
                $endPoint = "reviews?" . $arrayQuery;
                $mongoClient = $mongoClient->setCustomQuery(TRUE);
            }

            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $review = null;

            $listOfRec = $response->data;

            $reviewData = null;
            $userData = null;
            $objectData = null;

            if (! empty($listOfRec->records)) {
                $review = $listOfRec->records[0];
                // build review data
                $reviewData = new stdclass();
                $reviewData->review = $review->review;
                $reviewData->rating = $review->rating;
                $reviewData->created_at = $review->created_at;
                $reviewData->images = isset($review->images) ? $review->images : null;
                $reviewData->total_reply = isset($review->total_reply) ? $review->total_reply : 0;

                // build user data
                $userId = $review->user_id;

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
                    ->where('users.user_id', $userId)
                    ->first();

                $roleOfficial = ['Merchant Review Admin', 'Master Review Admin'];

                $userData = new stdClass();
                $userData->user_name = $reviewUser->user_name;
                $userData->user_picture = $reviewUser->user_picture;
                $userData->is_official_user = in_array($reviewUser->role_name, $roleOfficial) ? 'y' : 'n';

                // build object data
                $objectType = $review->object_type;
                $objectId = $review->object_id;
                $objectData = new stdclass();

                $prefix = DB::getTablePrefix();
                $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);

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
                            ->where(DB::raw("oms.country"), $review->country_id)
                            ->first();

                        $objectData->id = $object->merchant_id;
                        $objectData->name = $object->name;
                        $objectData->type = 'store';
                        $objectData->image = $object->mediaLogo->path;

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
                            ->leftJoin('news_translations', function ($q) use ($validLanguage) {
                                $q->on('news_translations.news_id', '=', 'news.news_id')
                                  ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($validLanguage->language_id)}"));
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

                    case 'coupon':
                        $object = Coupon::select(
                                'promotions.promotion_id as promotion_id',
                                DB::Raw("
                                        CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                        CASE WHEN (SELECT {$image}
                                            FROM {$prefix}media m
                                            WHERE m.media_name_long = 'coupon_translation_image_orig'
                                            AND m.object_id = {$prefix}coupon_translations.coupon_translation_id
                                            AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            LIMIT 1
                                            ) is null
                                        THEN
                                            (SELECT {$image}
                                            FROM {$prefix}media m
                                            WHERE m.media_name_long = 'coupon_translation_image_orig'
                                            AND m.object_id = default_translation.coupon_translation_id
                                            AND default_translation.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            LIMIT 1
                                            )
                                        ELSE
                                            (SELECT {$image}
                                            FROM {$prefix}media m
                                            WHERE m.media_name_long = 'coupon_translation_image_orig'
                                            AND m.object_id = {$prefix}coupon_translations.coupon_translation_id
                                            AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            LIMIT 1)
                                        END AS original_media_path
                                    "),
                                'promotions.end_date',
                                DB::raw("default_translation.promotion_name as default_name"),
                                'promotions.promotion_type as coupon_type',
                                DB::raw("m.country as coupon_country"),
                                'promotions.promotion_type',
                                DB::raw("CASE WHEN m.object_type = 'tenant' THEN m.parent_id ELSE m.merchant_id END as mall_id")
                            )
                            ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                            ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')

                            ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                  ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
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
                        $objectData->image = $object->mediaLogo->path;

                    default:
                        # code...
                        break;
                }
            }

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