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

            $image = "CONCAT({$this->quote($urlPrefix)}, media.path) as original_media_path";
            if ($usingCdn) {
                $image = "CASE WHEN (media.cdn_url is null or media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, media.path) ELSE media.cdn_url END as original_media_path";
            }

            $news = News::select(
                            'news.news_id as news_id',
                            DB::Raw("
                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                CASE WHEN ({$prefix}news_translations.description = '' or {$prefix}news_translations.description is null) THEN {$prefix}news.description ELSE {$prefix}news_translations.description END as description,
                                {$image}
                            "),
                            'news.object_type',
                            'news.end_date',
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
                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin(DB::raw("(SELECT {$prefix}news_translations.news_id, {$prefix}media.path,
                                (CASE WHEN ({$prefix}media.path = '' OR {$prefix}media.path is null) THEN 0 ELSE 1 END) AS totalRow,
                                {$prefix}media.cdn_url
                            FROM {$prefix}media
                            LEFT JOIN {$prefix}news_translations ON {$prefix}media.object_id = {$prefix}news_translations.news_translation_id
                                AND {$prefix}media.media_name_long = 'news_translation_image_orig'
                            GROUP BY object_id
                            HAVING totalRow > 0
                            ORDER BY {$prefix}media.object_id = (SELECT news_translation_id FROM {$prefix}news_translations WHERE merchant_language_id = {$this->quote($valid_language->language_id)} and news_id = {$this->quote($newsId)}) DESC) as media"), DB::raw("media.news_id"), '=', 'news.news_id')
                        ->where('news.news_id', $newsId)
                        ->where('news.object_type', '=', 'news')
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->with(['keywords' => function ($q) {
                                $q->addSelect('keyword', 'object_id');
                            }])
                        ->first();

            $message = 'Request Ok';
            if (! is_object($news)) {
                OrbitShopAPI::throwInvalidArgument('News that you specify is not found');
            }

            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

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
            $news->facebook_share_url = SocMedAPIController::getSharedUrl('news', $news->news_id, $news->news_name);

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

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getMessage(), $e->getFile(), $e->getLine()];
            $httpCode = 500;

        }

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}