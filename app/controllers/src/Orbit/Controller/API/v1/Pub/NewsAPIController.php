<?php namespace Orbit\Controller\API\v1\Pub;

/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for news list and search in landing page
 */

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\ResponseProvider;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use News;
use NewsMerchant;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;

class NewsAPIController extends ControllerAPI
{
	protected $validRoles = ['super admin', 'consumer', 'guest'];

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
    public function getSearchNews()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $keyword = null;

        try{
            // Get language_if of english
            $languageEnId = null;
            $language = Language::where('name', 'en')->first();

            if (! empty($language)) {
                $languageEnId = $language->language_id;
            }

            $prefix = DB::getTablePrefix();

            $news = News::select('news.news_id as news_id', 'news_translations.news_name as news_name', 'news.object_type',
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
                            "))
                        ->join('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->where('news_translations.merchant_language_id', '=', $languageEnId)
                        ->where('news.object_type', '=', 'news')
                        ->where('news_translations.news_name', '!=', '')
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'");

            OrbitInput::get('keyword', function($keyword) use ($news, $prefix) {
                 if (! empty($keyword)) {
                    $news = $news->leftJoin('keyword_object', 'news.news_id', '=', 'keyword_object.object_id')
                                ->leftJoin('keywords', 'keyword_object.keyword_id', '=', 'keywords.keyword_id')
                                ->where(function($query) use ($keyword, $prefix){
                                    //Search per word
                                    $words = explode(' ', $keyword);
                                    foreach ($words as $key => $word) {
                                        if (strlen($word) === 1 && $word === '%') {
                                            $query->orWhere(function($q) use ($word, $prefix){
                                                $q->whereRaw("{$prefix}news_translations.news_name like '%|{$word}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}news_translations.description like '%|{$word}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}keywords.keyword like '%|{$word}%' escape '|'");
                                            });
                                        } else {
                                            $query->orWhere(function($q) use ($word, $prefix){
                                                $q->where('news_translations.news_name', 'like', '%' . $word . '%')
                                                  ->orWhere('news_translations.description', 'like', '%' . $word . '%')
                                                  ->orWhere('keywords.keyword', 'like', '%' . $word . '%');
                                            });
                                        }
                                    }
                                });
                 }
            });

            OrbitInput::get('filter_name', function ($filterName) use ($news, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $news->whereRaw("SUBSTR({$prefix}news_translations.news_name,1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $news->whereRaw("SUBSTR({$prefix}news_translations.news_name,1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            $news = $news->groupBy('news.news_id');

            $_news = clone($news);

            $take = PaginationNumber::parseTakeFromGet('news');
            $news->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $news->skip($skip);

            $news->orderBy('news_translations.news_name', 'asc');

            $totalRec = count($_news->get());
            $listOfRec = $news->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_news)->count();
            $data->records = $listOfRec;

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

    public function getMallPerNews()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $newsId = OrbitInput::get('news_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');


            $validator = Validator::make(
                array(
                    'news_id' => $newsId,
                ),
                array(
                    'news_id' => 'required',
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

            $prefix = DB::getTablePrefix();

            $news = NewsMerchant::select('news.begin_date as begin_date', 'news.end_date as end_date',
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END as merchant_id"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END as name"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.description ELSE {$prefix}merchants.description END as description"),
                                            DB::raw("CONCAT(IF({$prefix}merchants.object_type = 'tenant', oms.ci_domain, {$prefix}merchants.ci_domain), '/customer/mallnewsdetail?id=', {$prefix}news_merchant.news_id) as news_url"),
                                            DB::raw("( SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                        FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                        WHERE om.merchant_id = (CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END)
                                                    ) as tz")
                                        )
                                    ->leftJoin('news', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('news_merchant.news_id', '=', $newsId)
                                    ->groupBy('merchant_id')
                                    ->havingRaw('tz <= end_date AND tz >= begin_date');

            $_news = clone($news);

            $take = PaginationNumber::parseTakeFromGet('news');
            $news->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $news->skip($skip);

            $news->orderBy('name', 'asc');

            $totalRec = count($_news->get());
            $listOfRec = $news->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_news)->count();
            $data->records = $listOfRec;

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

    public function getNewsItem()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;

        try{
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            // Get language_if of english
            $languageEnId = null;
            $language = Language::where('name', 'en')->first();

            if (! empty($language)) {
                $languageEnId = $language->language_id;
            }

            $newsId = OrbitInput::get('news_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');


            $validator = Validator::make(
                array(
                    'news_id' => $newsId,
                ),
                array(
                    'news_id' => 'required',
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

            $prefix = DB::getTablePrefix();

            $news = News::with(['translations' => function($q) use ($languageEnId) {
                            $q->addSelect(['news_translation_id', 'news_id']);
                            $q->with(['media' => function($q2) {
                                $q2->addSelect(['object_id', 'media_name_long', 'path']);
                            }]);
                            $q->where('merchant_language_id', $languageEnId);
                        }])
                        ->select(
                            'news.news_id as news_id',
                            'news_translations.news_name as news_name',
                            'news.object_type',
                            'news_translations.description as description',
                            'news.end_date',
                            'media.path as original_media_path',
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
                        ->join('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('media', 'media.object_id', '=', 'news_translations.news_translation_id')
                        ->where('news.news_id', $newsId)
                        ->where('news_translations.merchant_language_id', '=', $languageEnId)
                        ->where('media.media_name_long', 'news_translation_image_orig')
                        ->where('news.object_type', '=', 'news')
                        ->where('news_translations.news_name', '!=', '')
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->first();

            $message = 'Request Ok';
            if (! is_object($news)) {
                OrbitShopAPI::throwInvalidArgument('News that you specify is not found');
            }

            $activityNotes = sprintf('Page viewed: Landing Page News Detail Page');
            $activity->setUser($user)
                ->setActivityName('view_landing_page_news_detail')
                ->setActivityNameLong('View GoToMalls News Detail')
                ->setObject($news)
                ->setNews($news)
                ->setModuleName('News')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

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
            $this->response->data = null;
            $httpCode = 500;

        }

        return $this->render($httpCode);
    }

    public function getNewsLocations()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $newsId = OrbitInput::get('news_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');


            $validator = Validator::make(
                array(
                    'news_id' => $newsId,
                ),
                array(
                    'news_id' => 'required',
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

            $prefix = DB::getTablePrefix();

            $newsLocations = NewsMerchant::select(
                                            DB::raw("{$prefix}merchants.merchant_id as merchant_id"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN CONCAT({$prefix}merchants.name, ' at ', oms.name) ELSE CONCAT('Customer Service at ', {$prefix}merchants.name) END as name"),
                                            DB::raw("{$prefix}merchants.object_type as location_type"),
                                            DB::raw("CONCAT(IF({$prefix}merchants.object_type = 'tenant', oms.ci_domain, {$prefix}merchants.ci_domain), '/customer/mallnewsdetail?id=', {$prefix}news_merchant.news_id) as url"),
                                            'news.begin_date as begin_date',
                                            'news.end_date as end_date',
                                            DB::raw("( SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                        FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                        WHERE om.merchant_id = (CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END)
                                                    ) as tz")
                                        )
                                    ->leftJoin('news', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('news_merchant.news_id', '=', $newsId)
                                    ->groupBy('merchant_id')
                                    ->havingRaw('tz <= end_date AND tz >= begin_date');

            $_newsLocations = clone($newsLocations);

            $take = PaginationNumber::parseTakeFromGet('news');
            $newsLocations->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $newsLocations->skip($skip);

            $newsLocations->orderBy('name', 'asc');

            $listOfRec = $newsLocations->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_newsLocations)->count();
            $data->records = $listOfRec;

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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}