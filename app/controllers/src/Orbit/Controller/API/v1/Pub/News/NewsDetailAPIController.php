<?php namespace Orbit\Controller\API\v1\Pub\News;

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
use News;
use NewsMerchant;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Mall;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;
use OrbitShop\API\v1\ResponseProvider;
use Partner;
use \Orbit\Helper\Exception\OrbitCustomException;
use TotalObjectPageView;
use Redis;
use Orbit\Helper\MongoDB\Client as MongoClient;

class NewsDetailAPIController extends PubControllerAPI
{

	/**
     * GET - get the news detail
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string news_id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getNewsItem()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;

        try{
            $user = $this->getUser();

            $newsId = OrbitInput::get('news_id', null);
            $language = OrbitInput::get('language', 'id');
            $mallId = OrbitInput::get('mall_id', null);
            $country = OrbitInput::get('country', null);
            $cities = OrbitInput::get('cities', null);
            $partnerToken = OrbitInput::get('token', null);
            $notificationId = OrbitInput::get('notification_id', null);

            $newsHelper = NewsHelper::create();
            $newsHelper->registerCustomValidation();
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

            $valid_language = $newsHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

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

            if (! empty($notificationId)) {
                $mongoConfig = Config::get('database.mongodb');
                $mongoClient = MongoClient::create($mongoConfig);

                $bodyUpdate = [
                    '_id'     => $notificationId,
                    'is_read' => true
                ];

                $response = $mongoClient->setFormParam($bodyUpdate)
                                        ->setEndPoint('user-notifications') // express endpoint
                                        ->request('PUT');
            }

            $news = News::select(
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
                            'news.begin_date',
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
                        ->where('news.news_id', $newsId)
                        ->where('news.object_type', '=', 'news')
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
            if (! is_object($news)) {
                throw new OrbitCustomException('News that you specify is not found', News::NOT_FOUND_ERROR_CODE, NULL);
            }

            $news->category_ids = $this->getNewsCategory($newsId);

            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::excludeDeleted()->where('merchant_id', '=', $mallId)->first();
            }

            // Only campaign having status ongoing and is_started true can going to detail page
            if (! in_array($news->campaign_status, ['ongoing', 'expired']) || ($news->campaign_status == 'ongoing' && $news->is_started == 'false')) {
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
            $news->total_view = $totalPageViews;

            if ($news->is_exclusive === 'Y') {
                // check token
                $partnerTokens = Partner::leftJoin('object_partner', 'partners.partner_id', '=', 'object_partner.partner_id')
                                    ->where('object_partner.object_type', 'news')
                                    ->where('object_partner.object_id', $news->news_id)
                                    ->where('partners.is_exclusive', 'Y')
                                    ->where('partners.token', $partnerToken)
                                    ->first();

                if (! is_object($partnerTokens)) {
                    throw new OrbitCustomException('News is exclusive, please specify partner token', News::IS_EXCLUSIVE_ERROR_CODE, NULL);
                }

                $news->is_exclusive = 'N';
            }

            // ---- START RATING ----
            $reviewCounter = \Orbit\Helper\MongoDB\Review\ReviewCounter::create(Config::get('database.mongodb'))
                ->setObjectId($news->news_id)
                ->setObjectType('news')
                ->setMall($mall)
                ->request();

            $news->rating_average = $reviewCounter->getAverage();
            $news->review_counter = $reviewCounter->getCounter();
            // ---- END OF RATING ----

            if (is_object($mall)) {
                $activityNotes = sprintf('Page viewed: View mall event detail');
                $activity->setUser($user)
                    ->setActivityName('view_mall_event_detail')
                    ->setActivityNameLong('View mall event detail')
                    ->setObject($news)
                    ->setNews($news)
                    ->setLocation($mall)
                    ->setModuleName('News')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $activityNotes = sprintf('Page viewed: Landing Page News Detail Page');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_news_detail')
                    ->setActivityNameLong('View GoToMalls News Detail')
                    ->setObject($news)
                    ->setLocation($mall)
                    ->setNews($news)
                    ->setModuleName('News')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            // add facebook share url dummy page
            $news->facebook_share_url = SocMedAPIController::getSharedUrl('news', $news->news_id, $news->news_name, $country, $cities);

            $this->response->data = $news;
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
