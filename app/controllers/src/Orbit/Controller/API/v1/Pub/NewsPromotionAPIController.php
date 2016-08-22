<?php namespace Orbit\Controller\API\v1\Pub;

/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for promotion Mobile CI Angular
 */

use Orbit\Controller\API\v1\Customer\BaseAPIController;
use OrbitShop\API\v1\ResponseProvider;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use News;
use NewsMerchant;
use Mall;
use OrbitShop\API\v1\OrbitShopAPI;
use Activity;
use Language;
use URL;
use App;
use Tenant;
use Orbit\Helper\Util\PaginationNumber;

class NewsPromotionAPIController extends BaseAPIController
{
	protected $validRoles = ['super admin', 'consumer', 'guest'];

    /**
     * GET - get active news or promotion in all mall, and also provide for searching
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string object_type
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string keyword
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchNewsPromotion()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $objectType = null;
        $keyword = null;

        try{
            $objectType = OrbitInput::get('object_type', null);

            // Get language_if of english
            $languageEnId = null;
            $language = Language::where('name', 'en')->first();

            if (! empty($language)) {
                $languageEnId = $language->language_id;
            }

            $prefix = DB::getTablePrefix();

            $news = News::select('news.news_id as news_promotion_id', 'news_translations.news_name as news_promotion_name', 'news.object_type',
                        // query for get status active based on timezone
                        DB::raw("
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                        THEN {$prefix}campaign_status.campaign_status_name
                                        ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                FROM {$prefix}merchants om
                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                WHERE om.merchant_id = {$prefix}news.mall_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                            ")
                )
                ->join('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                ->where('news.status', '=', 'active')
                ->where('news_translations.merchant_language_id', '=', $languageEnId)
                ->where('news.object_type', '=', $objectType)
                ->having('campaign_status', '=', 'ongoing');

            OrbitInput::get('keyword', function($keyword) use ($news) {
                 if (! empty($keyword)) {
                    $news = $news->leftJoin('keyword_object', 'news.news_id', '=', 'keyword_object.object_id')
                                ->leftJoin('keywords', 'keyword_object.keyword_id', '=', 'keywords.keyword_id')
                                ->where(function($query) use ($keyword){

                                    //Search per word
                                    $words = explode(' ', $keyword);
                                    foreach ($words as $key => $word) {
                                        $query->orWhere('news_translations.news_name', 'like', '%' . $word . '%')
                                            ->orWhere('news_translations.description', 'like', '%' . $word . '%')
                                            ->orWhere('keywords.keyword', '=', $word);
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

            $activityNotes = sprintf('Failed to view Page: %s List. Err: %s', ucwords($objectType), $e->getMessage());
            $activity->setUser($user)
                ->setActivityName(sprintf('view_%s_list', $objectType))
                ->setActivityNameLong(sprintf('View %s List Failed', ucwords($objectType)))
                ->setObject(null)
                ->setModuleName(ucwords($objectType))
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

            $activityNotes = sprintf('Failed to view Page: %s List. Err: %s', ucwords($objectType), $e->getMessage());
            $activity->setUser($user)
                ->setActivityName(sprintf('view_%s_list', $objectType))
                ->setActivityNameLong(sprintf('View %s List Failed', ucwords($objectType)))
                ->setObject(null)
                ->setModuleName(ucwords($objectType))
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
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

            $activityNotes = sprintf('Failed to view Page: %s List. Err: %s', ucwords($objectType), $e->getMessage());
            $activity->setUser($user)
                ->setActivityName(sprintf('view_%s_list', $objectType))
                ->setActivityNameLong(sprintf('View %s List Failed', ucwords($objectType)))
                ->setObject(null)
                ->setModuleName(ucwords($objectType))
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activityNotes = sprintf('Failed to view Page: %s List. Err: %s', ucwords($objectType), $e->getMessage());
            $activity->setUser($user)
                ->setActivityName(sprintf('view_%s_list', $objectType))
                ->setActivityNameLong(sprintf('View %s List Failed', ucwords($objectType)))
                ->setObject(null)
                ->setModuleName(ucwords($objectType))
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        }

        return $this->render($httpCode);
    }

    public function getMallPerNewsPromotion()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $objectType = null;

        try{
            $newsPromotionId = OrbitInput::get('news_promotion_id', null);
            $objectType = OrbitInput::get('object_type', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');

            $prefix = DB::getTablePrefix();

            $newsPromotionLink = null;

            if ($objectType === 'news') {
                $newsPromotionLink = 'mallnews';
            } elseif ($objectType === 'promotion'){
                $newsPromotionLink = 'mallpromotions';
            }

            $prefix = DB::getTablePrefix();

            $news = NewsMerchant::select(
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END as merchant_id"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END as name"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.description ELSE {$prefix}merchants.description END as description"),
                                            DB::raw("CONCAT(IF({$prefix}merchants.object_type = 'tenant', oms.ci_domain, {$prefix}merchants.ci_domain), '/customer/" . $newsPromotionLink . "?id=', {$prefix}news_merchant.news_id) as news_promotion_url")
                                        )
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('news_merchant.news_id', '=', $newsPromotionId)
                                    ->groupBy('merchant_id');


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

            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: %s List Page', ucwords($objectType));
                $activity->setUser($user)
                    ->setActivityName(sprintf('view_%s_list', $objectType))
                    ->setActivityNameLong(sprintf('View %s List', ucwords($objectType)))
                    ->setObject(null)
                    ->setModuleName(ucwords($objectType))
                    ->setNotes($activityNotes)
                    // ->setLocation($mall)
                    ->responseOK()
                    ->save();
            }

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

            $activityNotes = sprintf('Failed to view Page: %s List. Err: %s', ucwords($objectType), $e->getMessage());
            $activity->setUser($user)
                ->setActivityName(sprintf('view_%s_list', $objectType))
                ->setActivityNameLong(sprintf('View %s List Failed', ucwords($objectType)))
                ->setObject(null)
                ->setModuleName(ucwords($objectType))
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

            $activityNotes = sprintf('Failed to view Page: %s List. Err: %s', ucwords($objectType), $e->getMessage());
            $activity->setUser($user)
                ->setActivityName(sprintf('view_%s_list', $objectType))
                ->setActivityNameLong(sprintf('View %s List Failed', ucwords($objectType)))
                ->setObject(null)
                ->setModuleName(ucwords($objectType))
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
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

            $activityNotes = sprintf('Failed to view Page: %s List. Err: %s', ucwords($objectType), $e->getMessage());
            $activity->setUser($user)
                ->setActivityName(sprintf('view_%s_list', $objectType))
                ->setActivityNameLong(sprintf('View %s List Failed', ucwords($objectType)))
                ->setObject(null)
                ->setModuleName(ucwords($objectType))
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activityNotes = sprintf('Failed to view Page: %s List. Err: %s', ucwords($objectType), $e->getMessage());
            $activity->setUser($user)
                ->setActivityName(sprintf('view_%s_list', $objectType))
                ->setActivityNameLong(sprintf('View %s List Failed', ucwords($objectType)))
                ->setObject(null)
                ->setModuleName(ucwords($objectType))
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted('merchants')
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return false;
            }

            App::instance('orbit.empty.mall', $mall);

            return true;
        });

        // Check the existance of tenant id
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $tenant = Tenant::excludeDeleted('merchants')
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($tenant)) {
                return false;
            }

            App::instance('orbit.empty.tenant', $tenant);

            return true;
        });

        // Check the existance of promotion id
        Validator::extend('orbit.empty.promotion', function ($attribute, $value, $parameters) {
            $promotion = News::excludeDeleted('news')
                        ->where('news_id', $value)
                        ->first();

            if (empty($promotion)) {
                return false;
            }

            App::instance('orbit.empty.promotion', $promotion);

            return true;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}