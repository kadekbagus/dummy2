<?php namespace Orbit\Controller\API\v1\Pub;

/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for promotion list and search in landing page
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
use \URL;
use News;
use NewsMerchant;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;

class PromotionAPIController extends ControllerAPI
{
    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $valid_language = NULL;

    /**
     * GET - get active promotion in all mall, and also provide for searching
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
    public function getSearchPromotion()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $keyword = null;

        try{
            $sort_by = OrbitInput::get('sortby', 'news_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $promotions = News::select(
                            'news.news_id as news_id',
                            DB::Raw("
                                CASE WHEN {$prefix}news_translations.news_name = '' THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                CASE WHEN {$prefix}news_translations.description = '' THEN {$prefix}news.description ELSE {$prefix}news_translations.description END as description,
                                CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}news_translations nt
                                        join {$prefix}media m
                                            on m.object_id = nt.news_translation_id
                                            and m.media_name_long = 'news_translation_image_orig'
                                        where nt.news_id = {$prefix}news.news_id
                                        group by nt.news_id
                                    ) ELSE {$prefix}media.path END as image_url
                            "),
                            'news.object_type',
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
                            ->leftJoin('media', function($q) {
                                $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                            })
                            ->where('news_translations.merchant_language_id', '=', $valid_language->language_id)
                            ->where('news.object_type', '=', 'promotion')
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->orderBy('news.news_id');

            $querySql = $promotions->toSql();

            $promotion = DB::table(DB::Raw("({$querySql}) as sub_query"))->mergeBindings($promotions->getQuery())
                            ->select(DB::raw("sub_query.news_id"), 'news_name', 'description', DB::raw("sub_query.object_type"), 'image_url', 'campaign_status', 'is_started')
                            ->groupBy(DB::Raw("sub_query.news_id"))
                            ->orderBy($sort_by, $sort_mode);

            OrbitInput::get('keyword', function($keyword) use ($promotion, $prefix) {
                 if (! empty($keyword)) {
                    $promotion = $promotion->leftJoin('keyword_object', DB::Raw("sub_query.news_id"), '=', 'keyword_object.object_id')
                                ->leftJoin('keywords', 'keyword_object.keyword_id', '=', 'keywords.keyword_id')
                                ->where(function($query) use ($keyword, $prefix){
                                    //Search per word
                                    $words = explode(' ', $keyword);
                                    foreach ($words as $key => $word) {
                                        if (strlen($word) === 1 && $word === '%') {
                                            $query->orWhere(function($q) use ($word, $prefix){
                                                $q->whereRaw("sub_query.news_name like '%|{$word}%' escape '|'")
                                                  ->orWhereRaw("sub_query.description like '%|{$word}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}keywords.keyword like '%|{$word}%' escape '|'");
                                            });
                                        } else {
                                            $query->orWhere(function($q) use ($word, $prefix){
                                                $q->where(DB::raw('sub_query.news_name'), 'like', '%' . $word . '%')
                                                  ->orWhere(DB::raw('sub_query.description'), 'like', '%' . $word . '%')
                                                  ->orWhere('keywords.keyword', 'like', '%' . $word . '%');
                                            });
                                        }
                                    }
                                });
                 }
            });

            OrbitInput::get('filter_name', function ($filterName) use ($promotion, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $promotion->whereRaw("SUBSTR(sub_query.news_name,1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $promotion->whereRaw("SUBSTR(sub_query.news_name,1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            $_promotion = clone($promotion);

            $take = PaginationNumber::parseTakeFromGet('promotion');
            $promotion->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $promotion->skip($skip);

            $totalRec = count($_promotion->get());
            $listOfRec = $promotion->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;
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

    public function getMallPerPromotion()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $promotionId = OrbitInput::get('promotion_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');


            $validator = Validator::make(
                array(
                    'promotion_id' => $promotionId,
                ),
                array(
                    'promotion_id' => 'required',
                ),
                array(
                    'required' => 'Promotion ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            $replaceIdPattern = '---REPLACE_ME_WITH_ID---';
            $urlToCI = URL::route('ci-promotion-detail', array('id' => $replaceIdPattern), false);
            $prefix = DB::getTablePrefix();
            $promotion = NewsMerchant::select('news.begin_date as begin_date', 'news.end_date as end_date',
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END as merchant_id"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END as name"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.description ELSE {$prefix}merchants.description END as description"),
                                            DB::raw("CONCAT(IF({$prefix}merchants.object_type = 'tenant', oms.ci_domain, {$prefix}merchants.ci_domain), REPLACE('{$urlToCI}', '$replaceIdPattern', {$prefix}news_merchant.news_id)) as promotion_url"),
                                            DB::raw("( SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                        FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                        WHERE om.merchant_id = (CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END)
                                                    ) as tz")
                                        )
                                    ->leftJoin('news', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('news_merchant.news_id', '=', $promotionId)
                                    ->groupBy('merchant_id')
                                    ->havingRaw('tz <= end_date AND tz >= begin_date');

            $_promotion = clone($promotion);

            $take = PaginationNumber::parseTakeFromGet('promotion');
            $promotion->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $promotion->skip($skip);

            $promotion->orderBy('name', 'asc');

            $totalRec = count($_promotion->get());
            $listOfRec = $promotion->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_promotion)->count();
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

     public function getPromotionItem()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;

        try{
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $promotionId = OrbitInput::get('promotion_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'promotion_id' => $promotionId,
                    'language' => $language,
                ),
                array(
                    'promotion_id' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'Promotion ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $promotion = News::select(
                            'news.news_id as news_id',
                            DB::Raw("
                                CASE WHEN {$prefix}news_translations.news_name = '' THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                CASE WHEN {$prefix}news_translations.description = '' THEN {$prefix}news.description ELSE {$prefix}news_translations.description END as description,
                                CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}news_translations nt
                                        join {$prefix}media m
                                            on m.object_id = nt.news_translation_id
                                            and m.media_name_long = 'news_translation_image_orig'
                                        where nt.news_id = {$prefix}news.news_id
                                        group by nt.news_id
                                    ) ELSE {$prefix}media.path END as original_media_path
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
                            ")
                        )
                        ->join('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('media', function($q) {
                            $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                        })
                        ->where('news.news_id', $promotionId)
                        ->where('news_translations.merchant_language_id', '=', $valid_language->language_id)
                        ->where('news.object_type', '=', 'promotion')
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->first();

            $message = 'Request Ok';
            if (! is_object($promotion)) {
                OrbitShopAPI::throwInvalidArgument('Promotion that you specify is not found');
            }

            $activityNotes = sprintf('Page viewed: Landing Page Promotion Detail Page');
            $activity->setUser($user)
                ->setActivityName('view_landing_page_promotion_detail')
                ->setActivityNameLong('View GoToMalls Promotion Detail')
                ->setObject($promotion)
                ->setNews($promotion)
                ->setModuleName('Promotion')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

            // add facebook share url dummy page
            $promotion->facebook_share_url = SocMedAPIController::getSharedUrl('promotion', $promotion->news_id, $promotion->news_name);

            $this->response->data = $promotion;
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

    public function getPromotionLocations()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $promotionId = OrbitInput::get('promotion_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');


            $validator = Validator::make(
                array(
                    'promotion_id' => $promotionId,
                ),
                array(
                    'promotion_id' => 'required',
                ),
                array(
                    'required' => 'Promotion ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $promotionLocation = NewsMerchant::select(
                                        DB::raw("{$prefix}merchants.merchant_id as merchant_id"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN CONCAT({$prefix}merchants.name, ' at ', oms.name) ELSE {$prefix}merchants.name END as name"),
                                        DB::raw("{$prefix}merchants.object_type as location_type"),
                                        DB::raw("CONCAT(IF({$prefix}merchants.object_type = 'tenant', oms.ci_domain, {$prefix}merchants.ci_domain), '/customer/mallpromotion?id=', {$prefix}news_merchant.news_id) as url"),
                                        'news.begin_date as begin_date',
                                        'news.end_date as end_date',
                                        DB::raw("( SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                    FROM {$prefix}merchants om
                                                    LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                    WHERE om.merchant_id = (CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END)
                                                ) as tz"),
                                            DB::Raw("img.path as location_logo"),
                                            DB::Raw("{$prefix}merchants.phone as phone")
                                    )
                                    ->leftJoin('news', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix){
                                        $q->on(DB::raw('img.object_id'), '=', DB::Raw("
                                                        (select CASE WHEN t.object_type = 'tenant'
                                                                    THEN m.merchant_id
                                                                    ELSE t.merchant_id
                                                                END as mall_id
                                                        from orb_merchants t
                                                        join orb_merchants m
                                                            on m.merchant_id = t.parent_id
                                                        where t.merchant_id = {$prefix}merchants.merchant_id)
                                            "))
                                            ->on(DB::raw('img.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                                    })
                                    ->where('news_merchant.news_id', '=', $promotionId)
                                    ->groupBy('merchant_id')
                                    ->havingRaw('tz <= end_date AND tz >= begin_date');

            $_promotionLocation = clone($promotionLocation);

            $take = PaginationNumber::parseTakeFromGet('news');
            $promotionLocation->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $promotionLocation->skip($skip);

            $promotionLocation->orderBy($sort_by, $sort_mode);

            $listOfRec = $promotionLocation->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_promotionLocation)->count();
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

    protected function registerCustomValidation() {
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}