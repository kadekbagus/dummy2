<?php namespace Orbit\Controller\API\v1\Pub\PromotionalEvent;

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
use Validator;
use Activity;
use Mall;
use Partner;
use News;
use App;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use \Orbit\Helper\Exception\OrbitCustomException;
use Orbit\Helper\PromotionalEvent\PromotionalEventProcessor;
use Lang;
use Orbit\Helper\MongoDB\Client as MongoClient;
use TotalObjectPageView;
use Redis;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;

class PromotionalEventDetailAPIController extends PubControllerAPI
{
    /**
     * GET - get detail page of promotional event
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string news_id
     * @param string sortby
     * @param string sortmode
     * @param string language
     * @param string country
     * @param string cities
     * @param string token
     *
     * @return Illuminate\Support\Facades\Response
     */
     public function getPromotionalEventItem()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = null;

        try{
            $user = $this->getUser();
            $role = $user->role->role_name;

            $newsId = OrbitInput::get('news_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $mallId = OrbitInput::get('mall_id', null);
            $language = OrbitInput::get('language', 'id');
            $country = OrbitInput::get('country', null);
            $cities = OrbitInput::get('cities', null);
            $partnerToken = OrbitInput::get('token', null);
            $firstTime = OrbitInput::get('first_time', FALSE);
            $mongoConfig = Config::get('database.mongodb');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'news_id' => $newsId,
                    'language' => $language,
                ),
                array(
                    'news_id' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'News ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->getValidLanguage();

            $prefix = DB::getTablePrefix();
            App::setLocale($language);

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $location = $mallId;
            if (empty($location)) {
                $location = 0;
            }

            $promotionalEvent = News::select(
                            'news.news_id as news_id',
                            'reward_details.is_new_user_only',
                            'reward_details.reward_type',
                            DB::Raw("
                                CASE WHEN ({$prefix}reward_detail_translations.guest_button_label = '' or {$prefix}reward_detail_translations.guest_button_label is null) THEN default_translation_button.guest_button_label ELSE {$prefix}reward_detail_translations.guest_button_label END as guest_button_label,
                                CASE WHEN ({$prefix}reward_detail_translations.logged_in_button_label = '' or {$prefix}reward_detail_translations.logged_in_button_label is null) THEN default_translation_button.logged_in_button_label ELSE {$prefix}reward_detail_translations.logged_in_button_label END as logged_in_button_label,
                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                CASE WHEN ({$prefix}news_translations.description = '' or {$prefix}news_translations.description is null) THEN default_translation.description ELSE {$prefix}news_translations.description END as description,
                                CASE WHEN (SELECT m.path
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = {$prefix}news_translations.news_translation_id) is null
                                THEN
                                    (SELECT m.path
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = default_translation.news_translation_id)
                                ELSE
                                    (SELECT m.path
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = {$prefix}news_translations.news_translation_id)
                                END AS original_media_path
                            "),
                            'news.object_type',
                            'news.end_date',
                            'news.is_exclusive',
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
                            "),
                            // query for getting timezone for countdown on the frontend
                            DB::raw("
                                (SELECT
                                    ot.timezone_name
                                FROM {$prefix}news_merchant onm
                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                    LEFT JOIN {$prefix}merchants oms ON oms.merchant_id = om.parent_id
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                WHERE onm.news_id = {$prefix}news.news_id
                                ORDER BY CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) ASC
                                LIMIT 1
                                ) as timezone
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
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        // For get button label
                        ->join('reward_details', 'reward_details.object_id', '=', 'news.news_id')
                        ->leftJoin('reward_detail_translations', function ($q) use ($valid_language) {
                            $q->on('reward_detail_translations.reward_detail_id', '=', 'reward_details.reward_detail_id')
                              ->on('reward_detail_translations.language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('reward_detail_translations as default_translation_button', function ($q) use ($valid_language) {
                            $q->on(DB::raw("default_translation_button.reward_detail_id"), '=', 'reward_details.reward_detail_id')
                              ->on(DB::raw("default_translation_button.language_id"), '=', 'languages.language_id');
                        })
                        ->where('news.news_id', $newsId)
                        ->where('news.object_type', '=', 'news')
                        ->where('news.is_having_reward', '=', 'Y')
                        ->with(['keywords' => function ($q) {
                                $q->addSelect('keyword', 'object_id');
                                $q->groupBy('keyword');
                            }])
                        ->with(['product_tags' => function ($pt) {
                                $pt->addSelect('product_tag', 'object_id');
                                $pt->groupBy('product_tag');
                            }])
                        ->first();

            $message = 'Request Ok';
            if (! is_object($promotionalEvent)) {
                throw new OrbitCustomException('Promotion that you specify is not found', News::NOT_FOUND_ERROR_CODE, NULL);
            }

            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::excludeDeleted()->where('merchant_id', '=', $mallId)->first();
            }

            $promotionalEvent->category_ids = $this->getNewsCategory($newsId);

            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');
            $promotionalEvent->original_media_path = $imgUrl->getImageUrl($promotion->original_media_path);

            // Only campaign having status ongoing and is_started true can going to detail page
            if (! in_array($promotionalEvent->campaign_status, ['ongoing', 'expired']) || ($promotionalEvent->campaign_status == 'ongoing' && $promotionalEvent->is_started == 'false')) {
                $mallName = 'gtm';
                if (! empty($mall)) {
                    $mallName = $mall->name;
                }

                $customData = new \stdClass;
                $customData->type = 'news';
                $customData->location = $location;
                $customData->mall_name = $mallName;
                throw new OrbitCustomException('News is inactive', News::INACTIVE_ERROR_CODE, $customData);
            }

            // Config page_views
            $configPageViewSource = Config::get('orbit.page_view.source', FALSE);
            $configPageViewRedisDb = Config::get('orbit.page_view.redis.connection', FALSE);
            $totalPageViews = 0;

            // Get total page views, depend of config what DB used
            if ($configPageViewSource === 'redis') {
                $keyRedis = 'news||' . $newsId . '||' . $location;
                $redis = Redis::connection($configPageViewRedisDb);
                $totalPageViewRedis = $redis->get($keyRedis);

                if (! empty($totalPageViewRedis)) {
                    $totalPageViews = $totalPageViewRedis;
                } else {
                    $totalObjectPageView = TotalObjectPageView::where('object_type', 'news')
                                                                 ->where('object_id', $newsId)
                                                                 ->where('location_id', $location)
                                                                 ->first();

                    if (! empty($totalObjectPageView->total_view)) {
                        $totalPageViews = $totalObjectPageView->total_view;
                    }
                }
            } else {
                $totalObjectPageView = TotalObjectPageView::where('object_type', 'news')
                                                             ->where('object_id', $newsId)
                                                             ->where('location_id', $location)
                                                             ->first();

                if (! empty($totalObjectPageView->total_view)) {
                    $totalPageViews = $totalObjectPageView->total_view;
                }
            }
            $promotionalEvent->total_view = $totalPageViews;

            // ---- START RATING ----
            $mongoClient = MongoClient::create($mongoConfig);
            $endPoint = "reviews";
            $queryString = [
                'object_id'   => $newsId,
                'object_type' => 'news'
            ];
            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $listOfRecLocation = $response->data;
            $averageGeneralRating = 0;
            $totalGeneralReviews = 0;
            if (! empty($listOfRecLocation->records)) {
                foreach ($listOfRecLocation->records as $rating) {
                    $averageGeneralRating = $averageGeneralRating + $rating->rating;
                    $totalGeneralReviews = $totalGeneralReviews + 1;
                }
                $averageGeneralRating = $averageGeneralRating / $totalGeneralReviews;
            }

            $promotionalEvent->rating_average = ($averageGeneralRating != 0) ? number_format(round($averageGeneralRating, 1), 1) : 0;
            $promotionalEvent->review_counter = round($totalGeneralReviews, 1);
            // ---- END OF RATING ----

            $role = $user->role->role_name;
            $userRating = FALSE;
            if (strtolower($role) === 'consumer') {
                $queryString = [
                    'object_id'   => $newsId,
                    'object_type' => 'news',
                    'user_id'     => $user->user_id
                ];

                $mongoClient = MongoClient::create($mongoConfig);
                $endPoint = "reviews";
                $response = $mongoClient->setQueryString($queryString)
                                        ->setEndPoint($endPoint)
                                        ->request('GET');

                $listOfRec = $response->data;

                if (count($listOfRec->records) > 0) {
                    $userRating = TRUE;
                }
            }
            $promotionalEvent->user_rating = $userRating;

            // check promotional event access and get lucky_draw/promotion code
            $promotionalEvent->code = null;
            $promotionalEvent->message_title = null;
            $promotionalEvent->message_content = null;
            $promotionalEvent->code_message = null;
            $promotionalEvent->with_button = true;
            $promotionalEvent->button_label = $promotionalEvent->guest_button_label;
            $promotionalEvent->user_role = 'guest';
            $promotionalEvent->user_status = 'active';
            $promotionalEvent->disable_button = false;

            $pe = PromotionalEventProcessor::create($user->user_id, $newsId, 'news', $language);
            $promotionalEventData = $pe->format($user->user_id, $newsId, 'news', $language, $firstTime);

            // No need token when user open detail page in promotioanal event history
            if ($promotionalEvent->is_exclusive === 'Y' && $promotionalEventData['code'] === '') {
                // check token
                $partnerTokens = Partner::leftJoin('object_partner', 'partners.partner_id', '=', 'object_partner.partner_id')
                                    ->where('object_partner.object_type', 'news')
                                    ->where('object_partner.object_id', $promotionalEvent->news_id)
                                    ->where('partners.is_exclusive', 'Y')
                                    ->where('partners.token', $partnerToken)
                                    ->first();

                if (! is_object($partnerTokens)) {
                    throw new OrbitCustomException('Promotion is exclusive, please specify partner token', News::IS_EXCLUSIVE_ERROR_CODE, NULL);
                }

                $promotionalEvent->is_exclusive = 'N';
            }

            if ($role != 'Guest') {
                $promotionalEvent->code = $promotionalEventData['code'];
                $promotionalEvent->message_title = $promotionalEventData['message_title'];
                $promotionalEvent->message_content = $promotionalEventData['message_content'];
                $promotionalEvent->code_message = $promotionalEventData['code_message'];
                $promotionalEvent->with_button = false;
                $promotionalEvent->button_label = null;
                $promotionalEvent->user_role = 'user';
                $promotionalEvent->user_status = 'active';

                switch ($promotionalEventData['status']) {
                    case 'play_button':
                        $promotionalEvent->with_button = true;
                        $promotionalEvent->button_label = $promotionalEvent->logged_in_button_label;
                        break;

                    case 'reward_ok':
                        DB::beginTransaction();
                        $updateReward = $pe->insertRewardCode($user->user_id, $newsId, 'news', $language);
                        DB::commit();
                        break;

                    case 'inactive_user':
                        $promotionalEvent->user_status = 'pending';
                        break;

                    case 'new_user_only':
                        $promotionalEvent->with_button = true;
                        $promotionalEvent->button_label = $promotionalEvent->logged_in_button_label;
                        $promotionalEvent->disable_button = true;
                        break;
                }
            }

            if (is_object($mall)) {
                $activityNotes = sprintf('Page viewed: View Promotional Event');
                $activity->setUser($user)
                    ->setActivityName('view_mall_promotional_event_detail')
                    ->setActivityNameLong('View Promotional Event Detail')
                    ->setObject($promotionalEvent)
                    ->setNews($promotionalEvent)
                    ->setLocation($mall)
                    ->setModuleName('Application')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $activityNotes = sprintf('Page viewed: View Promotional Event Detail');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_promotional_event_detail')
                    ->setActivityNameLong('View Promotional Event Detail')
                    ->setObject($promotionalEvent)
                    ->setNews($promotionalEvent)
                    ->setLocation($mall)
                    ->setModuleName('Application')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            // add facebook share url dummy page
            $promotionalEvent->facebook_share_url = SocMedAPIController::getSharedUrl('promotional-event', $promotionalEvent->news_id, $promotionalEvent->news_name, $country, $cities);

            $this->response->data = $promotionalEvent;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = $message;

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

        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getCustomData();
            if ($this->response->code === 4040) {
                $httpCode = 404;
            } else {
                $httpCode = 500;
            }

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });
    }

    public function getValidLanguage()
    {
        return $this->valid_language;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    /**
     * Get news/promotion categories.
     *
     * @param  string $newsId [description]
     * @return [type]         [description]
     */
    private function getNewsCategory($newsId = '')
    {
        return News::select('categories.category_id')
                     ->leftJoin('news_merchant', 'news.news_id', '=', 'news_merchant.news_id')
                     ->leftJoin('category_merchant', 'news_merchant.merchant_id', '=', 'category_merchant.merchant_id')
                     ->join('categories', 'category_merchant.category_id', '=', 'categories.category_id')
                     ->where('categories.merchant_id', 0)
                     ->where('categories.status', 'active')
                     ->where('news.news_id', $newsId)
                     ->groupBy('categories.category_id')
                     ->get()->lists('category_id');
    }
}
