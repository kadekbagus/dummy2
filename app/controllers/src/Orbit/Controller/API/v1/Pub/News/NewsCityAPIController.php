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
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Mall;

class NewsCityAPIController extends PubControllerAPI
{

    /**
     * GET - Get list of city for each news
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string news_id
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getNewsCity()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try{
            $user = $this->getUser();

            $newsId = OrbitInput::get('news_id', null);
            $sort_by = OrbitInput::get('sortby', 'city');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $mall = null;
            $storeName = OrbitInput::get('store_name');
            $mallId = OrbitInput::get('mall_id');
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

            $newsLocation = NewsMerchant::select(
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city")
                                    )
                                    ->leftJoin('news', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('news_merchant.news_id', '=', $newsId)
                                    ->where('merchants.status', '=', 'active');

            // filter by store name
            OrbitInput::get('store_name', function($storeName) use ($newsLocation) {
                $newsLocation->where('merchants.name', $storeName);
            });

            if ($skipMall === 'Y') {
                OrbitInput::get('mall_id', function($mallId) use ($newsLocation) {
                    $newsLocation->where(function($q) use ($mallId) {
                                        $q->where('merchants.parent_id', '!=', $mallId)
                                          ->where('merchants.merchant_id', '!=', $mallId);
                                    });
                    });
            } else {
                OrbitInput::get('mall_id', function($mallId) use ($newsLocation) {
                    $newsLocation->where(function($q) use ($mallId) {
                                        $q->where('merchants.parent_id', '=', $mallId)
                                          ->orWhere('merchants.merchant_id', '=', $mallId);
                                    });
                    });
            }

            $newsLocation = $newsLocation->groupBy('city');

            $_newsLocation = clone($newsLocation);

            $take = PaginationNumber::parseTakeFromGet('city_location');
            $newsLocation->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $newsLocation->skip($skip);

            $newsLocation->orderBy($sort_by, $sort_mode);

            $listOfRec = $newsLocation->get();

            // moved from generic activity number 36
            if (empty($skip) && OrbitInput::get('is_detail', 'n') === 'y'  ) {
                $news = News::excludeDeleted()
                    ->where('news_id', $newsId)
                    ->first();

                $activityNotes = sprintf('Page viewed: News city list');
                $activity->setUser($user)
                    ->setActivityName('view_news_city')
                    ->setActivityNameLong('View News City Page')
                    ->setObject($news)
                    ->setLocation($mall)
                    ->setModuleName('News')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_newsLocation)->count();
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