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

            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $prefix = DB::getTablePrefix();

            $newsLocations = NewsMerchant::select(
                                            "merchants.merchant_id",
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.name ELSE oms.name END as name"),
                                            "merchants.object_type"
                                        )
                                    ->join('news', function ($q) {
                                        $q->on('news_merchant.news_id', '=', 'news.news_id')
                                          ->on('news.object_type', '=', DB::raw("'news'"));
                                    })
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('news_merchant.news_id', '=', $newsId)
                                    ->groupBy("name")
                                    ->groupBy("object_type")
                                    ->orderBy($sort_by, $sort_mode);

            // filter news by mall id
            OrbitInput::get('mall_id', function($mallid) use ($is_detail, $newsLocations, &$group_by) {
                if ($is_detail != 'y') {
                    $newsLocations->where('merchants.parent_id', '=', $mallid);
                }
            });

            $_newsLocations = clone($newsLocations);

            $take = PaginationNumber::parseTakeFromGet('news');
            $newsLocations->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $newsLocations->skip($skip);

            $newsLocations->orderBy($sort_by, $sort_mode);

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
}