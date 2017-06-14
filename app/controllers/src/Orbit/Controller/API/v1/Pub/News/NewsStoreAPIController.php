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
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;
use Mall;

class NewsStoreAPIController extends PubControllerAPI
{

    /**
     * GET - get store list inside news/events detil
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string mall_id
     * @param string news_id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getNewsStore()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try{
            $user = $this->getUser();

            $newsId = OrbitInput::get('news_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $mallId = OrbitInput::get('mall_id', null);
            $is_detail = OrbitInput::get('is_detail', 'n');
            $mall = null;
            $skipMall = OrbitInput::get('skip_mall', 'N');

            $validator = Validator::make(
                array(
                    'news_id' => $newsId,
                    'skip_mall' => $skipMall,
                ),
                array(
                    'news_id' => 'required',
                    'skip_mall' => 'in:Y,N',
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

            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $merchantLogo = "CONCAT({$this->quote($urlPrefix)}, img.path) as merchant_logo";
            if ($usingCdn) {
                $merchantLogo = "CASE WHEN (img.cdn_url is null or img.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, img.path) ELSE img.cdn_url END as merchant_logo";
            }

            $newsLocations = NewsMerchant::select(
                                            "merchants.merchant_id",
                                            DB::raw("{$prefix}merchants.name as name"),
                                            "merchants.object_type",
                                            DB::raw("{$merchantLogo}"),
                                            DB::raw("CASE WHEN oms.object_type = 'mall' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END as mall_id"),
                                            DB::raw("oms.merchant_id as parent_id"),
                                            DB::raw("oms.object_type as parent_type"),
                                            DB::raw("oms.name as parent_name")
                                        )
                                    ->join('news', function ($q) {
                                        $q->on('news_merchant.news_id', '=', 'news.news_id')
                                          ->on('news.object_type', '=', DB::raw("'news'"));
                                    })
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    // Logo
                                    ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix){
                                        $q->on(DB::raw('img.object_id'), '=', 'merchants.merchant_id')
                                          ->on(DB::raw('img.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                                    })
                                    ->where('news_merchant.news_id', '=', $newsId);

            OrbitInput::get('cities', function($cities) use ($newsLocations, $prefix) {
                foreach ($cities as $key => $value) {
                    if (empty($value)) {
                       unset($cities[$key]);
                    }
                }
                if (! empty($cities)) {
                    $newsLocations->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.city ELSE oms.city END)"), $cities);
                }
            });

            OrbitInput::get('country', function($country) use ($newsLocations, $prefix) {
                if (! empty($country)) {
                    $newsLocations->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.country ELSE oms.country END)"), $country);
                }
            });

            // get all record with mall id
            $numberOfMall = 0;
            $numberOfStore = 0;
            $numberOfStoreRelatedMall = 0;

            // get number of store and number of mall
            $_numberOfLocation = clone($newsLocations);
            $_numberOfLocation = $_numberOfLocation->groupBy('merchants.name');

            $numberOfLocationSql = $_numberOfLocation->toSql();
            $_numberOfLocation = DB::table(DB::Raw("({$numberOfLocationSql}) as sub_query"))->mergeBindings($_numberOfLocation->getQuery())
                            ->select(
                                    DB::raw("object_type, count(merchant_id) as total")
                                )
                            ->groupBy(DB::Raw("sub_query.parent_id"))
                            ->get();

            foreach ($_numberOfLocation as $_data) {
                if ($_data->object_type === 'tenant') {
                    $numberOfStore += $_data->total;
                    $numberOfStoreRelatedMall++;
                } else {
                    $numberOfMall += $_data->total;
                }
            }

            if ($skipMall === 'Y') {
                // filter news skip by mall id
                OrbitInput::get('mall_id', function($mallid) use ($is_detail, $newsLocations, &$group_by) {
                    if ($is_detail != 'y') {
                        $newsLocations->where(DB::raw('oms.merchant_id'), '!=', $mallid);
                    }
                });
            } else {
                // filter news by mall id
                OrbitInput::get('mall_id', function($mallid) use ($is_detail, $newsLocations, &$group_by) {
                    if ($is_detail != 'y') {
                        $newsLocations->where('merchants.parent_id', $mallid)
                                    ->where('merchants.object_type', 'tenant');
                    }
                });
            }

            $newsLocations = $newsLocations->groupBy('merchants.name');

            $_newsLocations = clone($newsLocations);

            $take = PaginationNumber::parseTakeFromGet('news');
            $newsLocations->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $newsLocations->skip($skip);

            $newsLocations->orderBy('name', 'asc');

            $listOfRec = $newsLocations->get();

            // moved from generic activity number 34
            if (empty($skip) && OrbitInput::get('is_detail', 'n') === 'y'  ) {
                $news = News::excludeDeleted()
                    ->where('news_id', $newsId)
                    ->first();

                $activityNotes = sprintf('Page viewed: News location list');
                $activity->setUser($user)
                    ->setActivityName('view_news_location')
                    ->setActivityNameLong('View News Location Page')
                    ->setObject($news)
                    ->setLocation($mall)
                    ->setModuleName('News')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_newsLocations)->count();
            $data->numberOfMall = $numberOfMall;
            $data->numberOfStore = $numberOfStore;
            $data->numberOfStoreRelatedMall = $numberOfStoreRelatedMall;
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