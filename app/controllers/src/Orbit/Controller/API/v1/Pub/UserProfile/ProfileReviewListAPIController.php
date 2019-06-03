<?php namespace Orbit\Controller\API\v1\Pub\UserProfile;
/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for news list and search in landing page
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
use Language;
use User;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use stdClass;
use Country;
use Orbit\Helper\Net\SessionPreparer;
use Tenant;
use News;
use Mall;
use Coupon;
use Orbit\Controller\API\v1\Pub\UserProfile\UserProfileHelper;

class ProfileReviewListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - get active news in all mall, and also provide for searching
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string keyword
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getReviewList()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();
            $objectId = OrbitInput::get('object_id', null);
            $objectType = OrbitInput::get('object_type', null);
            $cityFilters = OrbitInput::get('cities', null);
            $countryFilter = OrbitInput::get('country', null);
            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();
            $mongoConfig = Config::get('database.mongodb');
            $mallId = OrbitInput::get('mall_id', null);
            $userId = OrbitInput::get('user_id', null);
            $language = OrbitInput::get('language', 'id');
            $session = SessionPreparer::prepareSession();

            $profileHelper = ProfileHelper::create();
            $profileHelper->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'language' => $language,
                    'user_id' => $userId,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'user_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $profileHelper->getValidLanguage();

            // should always check the role
            // $role = $user->role->role_name;
            // if (strtolower($role) !== 'consumer') {
            //     $message = 'You must login to access this.';
            //     ACL::throwAccessForbidden($message);
            // }

            $prefix = DB::getTablePrefix();

            $queryString = [
                'take'        => $take,
                'skip'        => $skip,
                'sortBy'      => 'updated_at',
                'sortMode'    => 'desc',
                'user_id'     => $userId
            ];

            $prefix = DB::getTablePrefix();

            // if (isset($userId)) {
            //     $queryString['user_id'] = $userId;
            // } else {
            //     $queryString['user_id'] = $user->user_id;
            // }

            $mongoClient = MongoClient::create($mongoConfig);

            $endPoint = "reviews";
            if (! empty($arrayQuery)) {
                $endPoint = "reviews?" . $arrayQuery;
                $mongoClient = $mongoClient->setCustomQuery(TRUE);
            }

            // check if promotional event, remove filter location
            if ($objectType === 'news') {
                $news = News::where('news_id', $objectId)->first();
                $isPromotionalEvent = $news->is_having_reward;

                if ($isPromotionalEvent === 'Y') {
                    unset($queryString['cities']);
                    unset($queryString['country_id']);
                    unset($queryString['location_id']);
                }
            }

            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $listOfRec = $response->data;

            if (! empty($listOfRec->records)) {
                // get user name and photo
                $prefix = DB::getTablePrefix();
                $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
                $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
                $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

                $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
                if ($usingCdn) {
                    $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
                }

                foreach ($listOfRec->records as $rating) {
                    $getName = null;
                    $getImage = null;
                    $getLocation = null;
                    switch ($rating->object_type) {
                        case 'mall':
                            $mall = Mall::select('name as display_name', DB::raw("{$image} AS cdn_url"))
                                            ->leftJoin('media', function ($q) {
                                                                $q->on('media.object_id', '=', 'merchants.merchant_id')
                                                                ->on('media.media_name_long', '=', DB::raw("'mall_logo_orig'"));
                                                        })
                                            ->where('merchant_id', $rating->object_id)
                                            ->first();

                            $getName = $mall->display_name;
                            $getImage =  $mall->cdn_url;

                            break;

                        case 'store':
                            $store = Tenant::select(DB::raw("{$prefix}merchants.name as display_name"),
                                                    DB::raw("{$image} AS cdn_url"))
                                            ->leftJoin('media', function ($q) {
                                                                $q->on('media.object_id', '=', 'merchants.merchant_id')
                                                                ->on('media.media_name_long', '=', DB::raw("'retailer_logo_orig'"));
                                                        })
                                            ->leftJoin('merchants as parent', DB::raw('parent.merchant_id'), '=', 'merchants.parent_id' )
                                            ->where('merchants.merchant_id', $rating->object_id)
                                            ->first();

                            $getName = $store->display_name;
                            $getImage = $store->cdn_url;
                            if (isset($rating->store_name) && isset($rating->mall_name)) {
                                $getLocation = $rating->store_name.' at '.$rating->mall_name;
                            }

                            break;

                        case 'promotion':
                            $promotion = News::select(
                                            DB::Raw("
                                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                                CASE WHEN ({$prefix}news_translations.description = '' or {$prefix}news_translations.description is null) THEN default_translation.description ELSE {$prefix}news_translations.description END as description,
                                                CASE WHEN (SELECT {$image}
                                                    FROM {$prefix}media
                                                    WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                                                    AND {$prefix}media.object_id = {$prefix}news_translations.news_translation_id) is null
                                                THEN
                                                    (SELECT {$image}
                                                    FROM {$prefix}media
                                                    WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                                                    AND {$prefix}media.object_id = default_translation.news_translation_id)
                                                ELSE
                                                    (SELECT {$image}
                                                    FROM {$prefix}media
                                                    WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                                                    AND {$prefix}media.object_id = {$prefix}news_translations.news_translation_id)
                                                END AS cdn_url
                                            ")
                                        )
                                        ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                                            $q->on('news_translations.news_id', '=', 'news.news_id')
                                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                                        })
                                        ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                                            $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                                              ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                                        })
                                        ->where('news.news_id', $rating->object_id)
                                        ->where('news.object_type', '=', 'promotion')
                                        ->first();

                            $getName = $promotion->news_name;
                            $getImage = $promotion->cdn_url;
                            if (isset($rating->store_name) && isset($rating->mall_name)) {
                                $getLocation = $rating->store_name.' at '.$rating->mall_name;
                            }

                            break;

                        case 'news':
                            $news = News::select(
                                            DB::Raw("
                                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                                CASE WHEN ({$prefix}news_translations.description = '' or {$prefix}news_translations.description is null) THEN default_translation.description ELSE {$prefix}news_translations.description END as description,
                                                CASE WHEN (SELECT {$image}
                                                    FROM {$prefix}media
                                                    WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                                                    AND {$prefix}media.object_id = {$prefix}news_translations.news_translation_id) is null
                                                THEN
                                                    (SELECT {$image}
                                                    FROM {$prefix}media
                                                    WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                                                    AND {$prefix}media.object_id = default_translation.news_translation_id)
                                                ELSE
                                                    (SELECT {$image}
                                                    FROM {$prefix}media
                                                    WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                                                    AND {$prefix}media.object_id = {$prefix}news_translations.news_translation_id)
                                                END AS cdn_url
                                            ")
                                        )
                                        ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                                            $q->on('news_translations.news_id', '=', 'news.news_id')
                                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                                        })
                                        ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                                            $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                                              ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                                        })
                                        ->where('news.news_id', $rating->object_id)
                                        ->where('news.object_type', '=', 'news')
                                        ->first();

                            $getName = $news->news_name;
                            $getImage = $news->cdn_url;
                            if (isset($rating->store_name) && isset($rating->mall_name)) {
                                $getLocation = $rating->store_name.' at '.$rating->mall_name;
                            }

                            break;

                        case 'coupon':
                            $coupon = Coupon::select(
                                                DB::Raw("
                                                        CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                                        CASE WHEN (SELECT {$image}
                                                            FROM {$prefix}media
                                                            WHERE {$prefix}media.media_name_long = 'coupon_translation_image_orig'
                                                            AND {$prefix}media.object_id = {$prefix}coupon_translations.coupon_translation_id
                                                            AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($valid_language->language_id)}
                                                            LIMIT 1
                                                            ) is null
                                                        THEN
                                                            (SELECT {$image}
                                                            FROM {$prefix}media
                                                            WHERE {$prefix}media.media_name_long = 'coupon_translation_image_orig'
                                                            AND {$prefix}media.object_id = default_translation.coupon_translation_id
                                                            AND default_translation.merchant_language_id = {$this->quote($valid_language->language_id)}
                                                            LIMIT 1
                                                            )
                                                        ELSE
                                                            (SELECT {$image}
                                                            FROM {$prefix}media
                                                            WHERE {$prefix}media.media_name_long = 'coupon_translation_image_orig'
                                                            AND {$prefix}media.object_id = {$prefix}coupon_translations.coupon_translation_id
                                                            AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($valid_language->language_id)}
                                                            LIMIT 1)
                                                        END AS cdn_url
                                                    ")
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
                                            ->where('promotions.promotion_id', $rating->object_id)
                                            ->first();

                            $getName = $coupon->promotion_name;
                            $getImage = $coupon->cdn_url;
                            if (isset($rating->store_name) && isset($rating->mall_name)) {
                                $getLocation = $rating->store_name.' at '.$rating->mall_name;
                            }
                            break;
                    }

                    $rating->display_name = $getName;
                    $rating->display_image = $getImage;
                    $rating->display_location = $getLocation;
                    $rating->total_reply = isset($rating->total_reply) ? $rating->total_reply : 0;
                    $rating->images = isset($rating->images) ? $rating->images : null;
                }
            }

            $data = new \stdclass();
            $data->returned_records = $listOfRec->returned_records;
            $data->total_records = $listOfRec->total_records;
            $data->records = $listOfRec->records;

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
}